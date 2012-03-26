<?php

namespace FuseSource\Stomp;

use FuseSource\Stomp\Exception\TransportException;
use FuseSource\Stomp\Exception\FrameException;
use FuseSource\Stomp\Value\Uri;
use FuseSource\Stomp\Value\Frame;
use FuseSource\Stomp\Event\SystemEventType;
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

            $this->logger->debug('Read callback triggered', array('data' => $data));

            $frame = Frame::unserializeFrom($data);
            $this->dispatcher->dispatch($frame->getEventName(), new FrameEvent($this, $frame));
        };
        
        $errorCallback = function($buf, $what, $arg) {
        	$this->logger->debug('Error callback triggered', array('what' => $what, 'arg' => $arg));

            $this->dispatcher->dispatch(SystemEventType::TRANSPORT_ERROR, new ErrorEvent($what));
        };

        $this->logger->info('Starting event loop');
        
        $this->base = event_base_new();
        $eb = event_buffer_new($this->_socket, $readCallback, NULL, $errorCallback, $this->base);

        event_buffer_base_set($eb, $this->base);
        event_buffer_enable($eb, EV_READ);

        event_base_loop($this->base);
    }

    public function addDataListener($eventName, callable $listener)
    {
        if (!preg_match('#^\/(queue|topic|temp-queue|temp-topic)\/#i', $eventName)) {
            throw new InvalidArgumentException(sprintf('Event Name for data listener must begin with one of /queue/ /topic/ /temp-queue/ /temp-topic/ %s given', $eventName));
        }

        $this->dataListeners[] = [$eventName, $listener];

        return $this;
    }

    public function addSystemListener($eventName, callable $listener)
    {
        if (!in_array($eventName, SystemEventType::getValidEventTypes(), true)) {
            throw new InvalidArgumentException(sprintf('Unknown system event %s given', $eventName));
        }

        $this->dispatcher->addListener($eventName, $listener);

        return $this;
    }

    public function unsubscribe($eventName, callable $listener)
    {
        $this->dispatcher->removeListener($eventName, $listener);

        if (preg_match('#^\/(queue|topic|temp-queue|temp-topic)\/#i', $eventName)) {
            foreach ($this->dataListeners as $dataListener) {
                list($theEventName, $theListener) = $dataListener;

                if ($theEventName !== $eventName || $dataListener !== $listener) {
                    continue;
                }

                $frame = Frame::createNew('UNSUBSCRIBE', ['destination' => $eventName]);
                $this->_writeFrame($frame);
            }
        }

        return $this;
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

                $this->logger->debug('Subscribed', array('eventName' => $eventName));   
            }

	        $this->dispatcher->addListener($eventName, $listener);
        }
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

   public function listen()
   {
   		$this->logger->info('About to open socket and listen to socket connection');

   		$this->openSocket();

        $this->addSystemListener(SystemEventType::FRAME_CONNECTED, function(FrameEvent $event) {
        	$this->logger->info('Successfully connected to server');

            $this->_sessionId = $event->getFrame()->getHeaders()['session'];
            $this->addPreRegisteredListeners();
        });

        $this->addSystemListener(SystemEventType::FRAME_ERROR, function(FrameEvent $event) {
            $this->logger->info('Got frame error');

            throw new FrameException('Frame error', 0, null, $event->getFrame());
        });

        $this->addSystemListener(SystemEventType::TRANSPORT_ERROR, function(ErrorEvent $event) {
        	$this->logger->info('Got transport error');

        	throw new TransportException($event->getMessage());
        });

        $connectionFrame = Frame::createNew('CONNECT', ['login' => $this->options['username'], 'passcode' => $this->options['password']]);
        $this->_writeFrame($connectionFrame);

        $this->startEventLoop();
    }

    public function disconnect()
    {
    	if (is_resource($this->base)) {
    		event_base_loopbreak($this->base);
    	}

    	parent::disconnect();
    }
}