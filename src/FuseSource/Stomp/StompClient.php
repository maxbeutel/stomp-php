<?php

namespace FuseSource\Stomp;

use FuseSource\Stomp\Exception\ConnectionException;
use FuseSource\Stomp\Exception\FrameException;
use FuseSource\Stomp\Exception\ReceiptException;
use FuseSource\Stomp\Exception\TransportException;
use FuseSource\Stomp\Value\Uri;
use FuseSource\Stomp\Value\Frame;
use FuseSource\Stomp\Event\SystemEventType;
use FuseSource\Stomp\Event\FrameEvent;
use FuseSource\Stomp\Event\ErrorEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Event;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use InvalidArgumentException;
use BadMethodCallException;

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

/**
 * A Stomp Connection
 *
 * @author Hiram Chirino <hiram@hiramchirino.com>
 * @author Dejan Bosanac <dejan@nighttale.net> 
 * @author Michael Caplan <mcaplan@labnet.net>
 */
class StompClient
{
    protected $brokerUri;
    protected $options;
    protected $dispatcher;
    protected $logger;

    protected $connected = false;
    protected $attempts = 10;
    protected $socket;
    protected $sessionId;

    protected $base;
    protected $subscribedEventNames = [];    

    public function __construct($uriString, array $options = [])
    {
        $defaultOptions = [
            'username'       => null,
            'password'       => null,
            'clientId'       => null,
            'prefetchSize'   => 1,
            'connectTimeout' => 60,
            'waitForReceipt' => false,
        ];

        $this->brokerUri = new Uri($uriString);
        $this->options = array_merge($defaultOptions, $options);

        $this->dispatcher = new EventDispatcher();

        $this->logger = new Logger('StompClient');
        $this->logger->pushHandler(new StreamHandler('php://stdout'));
    }

    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
        return $this;
    }

    public function getSessionId()
    {
        return $this->sessionId;
    }    

    protected function openSocket()
    {
        $connected = false;
        $connectionAttempts = 0;

        while (!$connected && $connectionAttempts < $this->attempts) {
            $connectionErrorNumber = $connectionError = null;

            $this->socket = @fsockopen(
                'tcp://' . $this->brokerUri->getHost(), 
                $this->brokerUri->getPort(), 
                $connectionErrorNumber, 
                $connectionError, 
                $this->options['connectTimeout']
            );
            
            if (is_resource($this->socket)) {
                $this->logger->info(sprintf('Successfully connected to socket at attempt %d', $connectionAttempts));

                return;
            }
            
            if ($this->socket) {
                @fclose($this->socket);
            }

            $connectionAttempts++;
        }
        
        throw new ConnectionException(sprintf('Could not connect to broker at attempt %d', $connectionAttempts));
    }

    protected function writeFrame(Frame $frame)
    {
        $data = (string) $frame;

        $this->logger->debug('Writing frame data', array('data' => $data));

        $success = (bool) fwrite($this->socket, $data, strlen($data));

        $this->logger->debug('Frame written to socket', array('success' => $success));

        if (!$success) {
            $this->openSocket();
            $this->writeFrame($frame);
        } else {
            if ($frame->waitForReceipt()) {
                $this->logger->debug('Waiting for frame receipt');

                $listener = function(FrameEvent $event) use($frame, &$listener) {
                    $this->unsubscribe(SystemEventType::FRAME_RECEIPT, $listener);
                    $this->breakEventLoop();

                    $expected = $frame->getHeaders()['receipt'];
                    $actual = $event->getFrame()->getHeaders()['receipt-id'];

                    $this->logger->debug('Got receipt', ['expected' => $expected, 'actual' => $actual]);

                    if ($expected !== $actual) {
                        throw new ReceiptException(sprintf('Expected receipt "%s" but got "%s"', $expected, $actual));
                    }
                };

                $this->dispatcher->addListener(SystemEventType::FRAME_RECEIPT, $listener);

                $this->startEventLoop();
            }
        }
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

            $this->dispatcher->dispatch(SystemEventType::TRANSPORT_ERROR, new ErrorEvent($this, $what));
        };

        $this->logger->info('Starting event loop');
        
        $this->base = event_base_new();
        $eb = event_buffer_new($this->socket, $readCallback, NULL, $errorCallback, $this->base);

        event_buffer_base_set($eb, $this->base);
        event_buffer_enable($eb, EV_READ);

        event_base_loop($this->base);
    }

    public function subscribe($eventName, callable $listener)
    {
        if (!$this->connected) {
            throw new BadMethodCallException('Cant subscribe before connecting');
        }

        if (!preg_match('#^\/(queue|topic|temp-queue|temp-topic)\/#i', $eventName)) {
            throw new InvalidArgumentException(sprintf('Event name must begin with one of /queue /topic /temp-queue /temp-topic, got "%s"', $eventName));     
        }

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
            $this->writeFrame($frame);

            $this->logger->debug('Subscribed', ['eventName' => $eventName]);   
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

                if ($theEventName !== $eventName || ($listener && $dataListener !== $listener)) {
                    continue;
                }

                $this->logger->debug('Sending unsubscribe frame', ['eventName' => $eventName]);

                $frame = Frame::createNew('UNSUBSCRIBE', ['destination' => $eventName]);
                $this->writeFrame($frame);

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
        $this->writeFrame($frame);
        
        return true;
    }

    public function connect()
    {
        $this->logger->info('About to open socket and listen to socket connection');
        $this->openSocket();

        $this->dispatcher->addListener(SystemEventType::FRAME_ERROR, function(FrameEvent $event) {
            $this->logger->err('Got frame error');

            $this->breakEventLoop();

            throw new FrameException('Frame error', 0, null, $event->getFrame());
        });

        $this->dispatcher->addListener(SystemEventType::TRANSPORT_ERROR, function(ErrorEvent $event) {
            $this->logger->err('Got transport error');

            $this->breakEventLoop();

            throw new TransportException($event->getMessage());
        });

        $this->dispatcher->addListener(SystemEventType::FRAME_CONNECTED, function(FrameEvent $event) {
            $this->sessionId = $event->getFrame()->getHeaders()['session'];

            $this->logger->info('Successfully connected to server', ['sessionId' => $this->sessionId]);

            $this->breakEventLoop();

            $this->connected = true;
        });

        $connectionFrame = Frame::createNew('CONNECT', ['login' => $this->options['username'], 'passcode' => $this->options['password']]);
        $this->writeFrame($connectionFrame);

        $this->startEventLoop();                   
    }

   public function listen()
   {
        $this->logger->debug('About to start event loop');

        $this->startEventLoop();
    }

    public function send($destination, $msg, $properties = [])
    {
        $this->logger->debug('About to send message frame');

        $frame = Frame::createNew('SEND', array_merge(['destination' => $destination], $properties), $msg, $this->options['waitForReceipt']);
        $this->writeFrame($frame);
    }

    public function begin($transactionId = null)
    {
        $this->logger->debug('About to start transaction', ['transactionId' => $transactionId]);

        $headers = [];

        if ($transactionId) {
            $headers['transaction'] = $transactionId;
        }

        $frame = Frame::createNew('BEGIN', $headers,'', $this->options['waitForReceipt']);
        $this->writeFrame($frame);
    }

    public function commit($transactionId = null)
    {
        $this->logger->debug('About to commit transaction', ['transactionId' => $transactionId]);

        $headers = [];
        
        if ($transactionId) {
            $headers['transaction'] = $transactionId;
        }
        
        $frame = Frame::createNew('COMMIT', $headers, '', $this->options['waitForReceipt']);
        $this->writeFrame($frame);
    }

    public function abort($transactionId = null)
    {
        $this->logger->debug('About to abort transaction', ['transactionId' => $transactionId]);

        $headers = [];

        if ($transactionId) {
            $headers['transaction'] = $transactionId;
        }

        $frame = Frame::createNew('ABORT', $headers, '', $this->options['waitForReceipt']);
        $this->writeFrame($frame);
    }

    protected function breakEventLoop()
    {
        $this->logger->debug('Breaking event loop');

        if (is_resource($this->base)) {
            event_base_loopbreak($this->base);
        }

        unset($this->base);
    } 

    public function disconnect()
    {
        $this->logger->info('Shutting down gracefully');

        $this->breakEventLoop();

        $headers = [];

        if ($this->options['clientId']) {
            $headers['client-id'] = $this->options['clientId'];
        }

        if (is_resource($this->socket)) {
            $this->writeFrame(Frame::createNew('DISCONNECT', $headers));
            fclose($this->socket);
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}