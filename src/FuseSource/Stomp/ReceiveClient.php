<?php

namespace FuseSource\Stomp;

use FuseSource\Stomp\Exception\TransportException;
use FuseSource\Stomp\Exception\FrameException;
use FuseSource\Stomp\Value\Uri;
use FuseSource\Stomp\Value\Frame;
use FuseSource\Stomp\Event\SystemEventType;
use FuseSource\Stomp\Exception\UnexpectedReceiptException;
use FuseSource\Stomp\Event\FrameEvent;
use FuseSource\Stomp\Event\ErrorEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Event;
use RuntimeException;
use InvalidArgumentException;

/**
 *
 * Copyright 2005-2006 The Apache Software Foundation
 * Source Code modified 2012 by Max Beutel <me@maxbeutel.de>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 */

class ReceiveClient extends AbstractStompClient
{
	protected $dispatcher;

	protected $base;
	protected $subscribedEventNames = [];

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

            $this->logger->debug('Read callback triggered', ['data' => $data]);

            $frame = Frame::unserializeFrom($data);
            $this->dispatcher->dispatch($frame->getEventName(), new FrameEvent($this, $frame));
        };
        
        $errorCallback = function($buf, $what, $arg) {
        	$this->logger->debug('Error callback triggered', ['what' => $what, 'arg' => $arg]);

            $this->dispatcher->dispatch(SystemEventType::TRANSPORT_ERROR, new ErrorEvent($what));
        };

        $this->logger->info('Starting event loop');
        
        $this->base = event_base_new();
        $eb = event_buffer_new($this->_socket, $readCallback, NULL, $errorCallback, $this->base);

        event_buffer_base_set($eb, $this->base);
        event_buffer_enable($eb, EV_READ);

        event_base_loop($this->base);
    }

    public function subscribe($eventName, callable $listener)
    {
        if (in_array($eventName, SystemEventType::getValidEventTypes(), true)) {
            return $this->addSystemListener($eventName, $listener);
        }

        if (preg_match('#^\/(queue|topic|temp-queue|temp-topic)\/#i', $eventName)) {
            return $this->addDataListener($eventName, $listener);    
        }
        
        throw new InvalidArgumentException('Given event name "%s" can neither be system listener nor data listener');
    }

    protected function addDataListener($eventName, callable $listener)
    {
        if (!in_array($eventName, $this->subscribedEventNames, true)) {
            $this->subscribedEventNames[] = $eventName;

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

            $this->logger->debug('Subscribed', ['eventName' => $eventName]);   
        }

        $this->dispatcher->addListener($eventName, $listener);

        return $this;
    }

    protected function addSystemListener($eventName, callable $listener)
    {
        $this->dispatcher->addListener($eventName, $listener);

        return $this;
    }

    public function unsubscribe($eventName, callable $listener)
    {
        $this->dispatcher->removeListener($eventName, $listener);

        if (preg_match('#^\/(queue|topic|temp-queue|temp-topic)\/#i', $eventName)) {
            foreach ($this->dataListeners as $dataListener) {
                list($theEventName, $theListener) = $dataListener;

                if ($theEventName !== $eventName || ($listener && $dataListener !== $listener)) {
                    continue;
                }

                $this->logger->debug('Sending unsubscribe frame', ['eventName' => $eventName]);

                $frame = Frame::createNew('UNSUBSCRIBE', ['destination' => $eventName]);
                $this->_writeFrame($frame);

                unset($this->subscribedEventNames[array_search($eventName, $this->subscribedEventNames, true)]);
            }
        }

        $this->logger->debug('Unsubscribed', ['eventName' => $eventName]);

        return $this;
     }    

    public function ack(Frame $frame, $transactionId = null)
    {
    	$this->logger->debug('Acking frame');

        $headers = $frame->getHeaders();
        
        if ($transactionId) {
            $headers['transaction'] = $transactionId;
        }           

        $frame = Frame::createNew('ACK', $headers);
        $this->_writeFrame($frame);
        
        return true;
    }

    /*
TODO: 
take connect() in listen into account, 
always take waitForReceipt into account (it should be allowed to call listen multiple times),
bring ReceiveClient and SendClient together again?,
break event loop on exception,
    */



    public function connect()
    {
        $this->logger->info('About to open socket and listen to socket connection');
        $this->openSocket();

        $this->subscribe(SystemEventType::FRAME_ERROR, function(FrameEvent $event) {
            $this->logger->info('Got frame error');

            throw new FrameException('Frame error', 0, null, $event->getFrame());
        });

        $this->subscribe(SystemEventType::TRANSPORT_ERROR, function(ErrorEvent $event) {
            $this->logger->info('Got transport error');

            throw new TransportException($event->getMessage());
        });

        $this->subscribe(SystemEventType::FRAME_CONNECTED, function(FrameEvent $event) {
            $this->_sessionId = $event->getFrame()->getHeaders()['session'];

            $this->logger->info('Successfully connected to server', ['sessionId' => $this->_sessionId]);

            $this->breakEventLoop();
        });

        $connectionFrame = Frame::createNew('CONNECT', ['login' => $this->options['username'], 'passcode' => $this->options['password']]);
        $this->_writeFrame($connectionFrame);

        $this->startEventLoop();                   
    }

   public function listen()
   {
        $this->addPreRegisteredListeners();
        $this->startEventLoop();
    }

    /* ############### */

    // @TODO implement common waitForReceiptLogic in _writeFrameMethod() ?
    public function send($destination, $msg, $properties = [])
    {
        $frame = Frame::createNew('SEND', array_merge(['destination' => $destination], $properties), $msg, $this->options['waitForReceipt']);

        $this->logger->debug('About to write message frame');

        if ($this->options['waitForReceipt']) {
            $listener = function(FrameEvent $event) use($frame, &$listener) {
                $this->unsubscribe(SystemEventType::FRAME_RECEIPT, $listener);
                $this->breakEventLoop();

                $expected = $frame->getHeaders()['receipt'];
                $actual = $event->getFrame()->getHeaders()['receipt-id'];

                $this->logger->debug('Got receipt', ['expected' => $expected, 'actual' => $actual]);

                if ($expected !== $actual) {
                    throw new UnexpectedReceiptException('Unexpected receipt', 0, null, $event->getFrame());
                }
            };

            $this->subscribe(SystemEventType::FRAME_RECEIPT, $listener);
        }

        $this->_writeFrame($frame);

        if ($this->options['waitForReceipt']) {
            $this->startEventLoop();
        }
    }

    public function begin($transactionId = null)
    {
        // @TODO wait for receipt if needed
        $headers = [];

        if ($transactionId) {
            $headers['transaction'] = $transactionId;
        }

        $frame = Frame::createNew('BEGIN', $headers);
        $this->_writeFrame($frame);
    }

    public function commit($transactionId = null)
    {
        // @TODO wait for receipt if needed
        $headers = [];
        
        if ($transactionId) {
            $headers['transaction'] = $transactionId;
        }
        
        $frame = Frame::createNew('COMMIT', $headers);
        $this->_writeFrame($frame);
    }

    public function abort($transactionId = null)
    {
        // @TODO wait for receipt if needed
        $headers = [];

        if ($transactionId) {
            $headers['transaction'] = $transactionId;
        }

        $frame = Frame::createNew('ABORT', $headers);
        $this->_writeFrame($frame);
    }
    /* ######### */

    protected function breakEventLoop()
    {
        if (is_resource($this->base)) {
            event_base_loopbreak($this->base);
        }

        unset($this->base);
    }

    public function disconnect()
    {
        $this->breakEventLoop();

        parent::disconnect();
    }
}