<?php

namespace FuseSource\Stomp;

use FuseSource\Stomp\Exception\StompException;
use FuseSource\Stomp\Value\Uri;
use FuseSource\Stomp\Value\Frame;
use FuseSource\Stomp\Event\EventType;
use FuseSource\Stomp\Event\FrameEvent;
use FuseSource\Stomp\Event\ErrorEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Event;
use RuntimeException;
use InvalidArgumentException;

class ReceiveClient extends AbstractStompClient
{
	protected $dispatcher;

	protected $base;
	protected $dataListeners = [];

	public function __construct($uriString, array $options = [])
	{
		parent::__construct($uriString, $options);

        $this->dispatcher = new EventDispatcher();
	}

    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
        return $this;
    }

    protected function startEventLoop()
    {
        $readCallback = function($buf, $arg) {
            $readLength = 1024;
            $data = '';
            $end = false;

            do {
                $data .= event_buffer_read($buf, $readLength);
                
                if (strpos($data, "\x00") !== false) {
                    $end = true;
                    $data = rtrim($data, "\n");
                }
                
                $dataLength = strlen($data);
            } while ($dataLength < 2 || $end == false);

            $frame = Frame::unserializeFrom($data);
            $this->dispatcher->dispatch($frame->getEventName(), new FrameEvent($this, $frame));
        };
        
        $errorCallback = function($buf, $what, $arg) {
            $this->dispatcher->dispatch(EventType::TRANSPORT_ERROR, new ErrorEvent($what));
        };
        
        $this->base = event_base_new();
        $eb = event_buffer_new($this->_socket, $readCallback, NULL, $errorCallback, $this->base);

        event_buffer_base_set($eb, $this->base);
        event_buffer_enable($eb, EV_READ);

        event_base_loop($this->base);
    }

    public function addListener($eventName, callable $listener)
    {
        if (!preg_match('#^\/(queue|topic|temp-queue|temp-topic)\/#i', $eventName)) {
            throw new InvalidArgumentException('Event Name for data listener must begin with one of /queue/ /topic/ /temp-queue/ /temp-topic/');
        }

        $this->dataListeners[] = [$eventName, $listener];

        return $this;
    }

    public function unsubscribe($eventName, callable $listener)
    {
        // @TODO implement this
        if (preg_match('#^\/(queue|topic|temp-queue|temp-topic)\/#i', $eventName)) {

            $headers = array();
            if (isset($properties)) {
                foreach ($properties as $name => $value) {
                    $headers[$name] = $value;
                }
            }
            $headers['destination'] = $destination;
            $frame = new Frame('UNSUBSCRIBE', $headers);
            $this->_prepareReceipt($frame, $sync);
            $this->_writeFrame($frame);
        }

        $this->dispatcher->removeListener($eventName, $listener);        

        if ($this->_waitForReceipt($frame, $sync) == true) {
            unset($this->_subscriptions[$destination]);
            return true;
        } else {
            return false;
        }
     }    

    protected function addPreRegisteredListeners()
    {
        $subscribedEventNames = [];

        foreach ($this->dataListeners as $dataListener) {
            list($eventName, $listener) = $dataListener;

            if (!in_array($eventName, $subscribedEventNames, true)) {
                $subscribedEventNames[] = $eventName;

                $headers = [
                    'ack'                   => 'client', 
                    'destination'           => $eventName, 
                    'activemq.prefetchSize' => $this->options['prefetchSize'],
                ];

                if ($this->options['clientId']) {
                    $headers['activemq.subcriptionName'] = $this->options['clientId'];
                }

                $frame = Frame::createNew('SUBSCRIBE', $headers);
                $this->_writeFrame($frame);

                var_dump('### REGISTER', $eventName);   
            }

	        $this->dispatcher->addListener($eventName, $listener);
        }
    }

    public function ack(Frame $frame, $transactionId = null)
    {
    	var_dump("### ACK");

        $headers = $frame->getHeaders();
        
        if ($transactionId) {
            $headers['transaction'] = $transactionId;
        }           

        $frame = Frame::createNew('ACK', $headers);
        $this->_writeFrame($frame);
        
        return true;
    }

   public function listen()
   {
   		$this->openSocket();

        $this->dispatcher->addListener(EventType::FRAME_CONNECTED, function(FrameEvent $event) {
        	var_dump('### CONNECTED');
            $this->_sessionId = $event->getFrame()->getHeaders()['session'];
            $this->addPreRegisteredListeners();
        });

        $this->dispatcher->addListener(EventType::FRAME_ERROR, function(FrameEvent $event) {
            // @TODO log this
            var_dump('### ERROR', $event->getFrame()->getBody());
        });

        $this->dispatcher->addListener(EventType::TRANSPORT_ERROR, function(ErrorEvent $event) {
            // @TODO log this
            var_dump('### ERROR', $event->getMessage());
        });

        $connectionFrame = Frame::createNew('CONNECT', ['login' => $this->options['username'], 'passcode' => $this->options['password']]);
        $this->_writeFrame($connectionFrame);

        $this->startEventLoop();
    }

    public function disconnect()
    {
    	var_dump("### DISCONNECTING");

    	if (is_resource($this->base)) {
    		event_base_loopbreak($this->base);
    	}

    	parent::disconnect();
    }
}