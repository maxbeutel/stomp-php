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

/**
 *
 * Copyright 2005-2006 The Apache Software Foundation
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
 */

/* vim: set expandtab tabstop=3 shiftwidth=3: */

/**
 * A Stomp Connection
 *
 *
 * @package Stomp
 * @author Hiram Chirino <hiram@hiramchirino.com>
 * @author Dejan Bosanac <dejan@nighttale.net> 
 * @author Michael Caplan <mcaplan@labnet.net>
 * @version $Revision: 43 $
 */
class Stomp
{
    /**
     * Perform request synchronously
     *
     * @var boolean
     */
    public $sync = false;

    /**
     * Default prefetch size
     *
     * @var int
     */
	public $prefetchSize = 1;
    
	/**
     * Client id used for durable subscriptions
     *
     * @var string
     */
	public $clientId = null;
    
    
    protected $_hosts = array();
    protected $_params = array();
    protected $_subscriptions = array();
    protected $_attempts = 10;
    protected $_username = '';
    protected $_password = '';
    protected $_read_timeout_seconds = 60;
    protected $_read_timeout_milliseconds = 0;
    protected $_connect_timeout_seconds = 60;
    


    protected $brokerUri;
    protected $options;
    protected $dispatcher;

    protected $_socket;
    protected $_sessionId;
    protected $preRegisteredListeners = [];


    /**
     * Constructor
     *
     * @param string $brokerUri Broker URL
     * @throws StompException
     */
    public function __construct($brokerUri, array $options = [])
    {
        $defaultOptions = [
            'username' => '',
            'password' => '',
        ];

        $this->brokerUri = new Uri($brokerUri);
        $this->options = $options;
        $this->dispatcher = new EventDispatcher();
        $this->openSocket();
    }

    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
        return $this;
    }

    /**
     * Make socket connection to the server
     *
     * @throws StompException
     */
    protected function openSocket()
    {
     #   $this->disconnect();
        
        $connected = false;
        
        while (!$connected && $connectionAttempts ++ < $this->_attempts) {            
            $this->_socket = @fsockopen(
                'tcp://' . $this->brokerUri->getHost(), 
                $this->brokerUri->getPort(), 
                $connectionErrorNumber, 
                $connectionError, 
                $this->_connect_timeout_seconds
            );
            
            if (is_resource($this->_socket)) {
                return;
            }
            
            if ($this->_socket) {
                @fclose($this->_socket);
            }
        }
        
        throw new RuntimeException('Could not connect to broker');
    }

    protected function startEventLoop()
    {
        $readCallback = function($buf, $arg) {
            $readLength = 1024;
            $data = '';
            $end = false;

            do {
                $read = event_buffer_read($buf, $readLength);
                
                if ($read === false) {
                    $this->_reconnect();
                    return $this->readFrame();
                }
                
                $data .= $read;
                
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
            $this->dispatcher->dispatch(EventType::ERROR, new ErrorEvent($what));
        };
        
        $base = event_base_new();
        $eb = event_buffer_new($this->_socket, $readCallback, NULL, $errorCallback, $base);

        event_buffer_base_set($eb, $base);
        event_buffer_enable($eb, EV_READ);

        event_base_loop($base);        
    }
    
    // @TODO should rename those to something like "registerSystem/registerMessageListener" or so...
    public function registerListener($eventName, callable $listener)
    {
        $this->preRegisteredListeners[] = [$eventName, $listener];
        return $this;
    }

    public function addListener($eventName, callable $listener)
    {
        $this->dispatcher->addListener($eventName, $listener);
        return $this;
    }

    private function addPreRegisteredListeners()
    {
        $subscribedEventNames = [];

        foreach ($this->preRegisteredListeners as $preRegisteredListener) {
            list($eventName, $listener) = $preRegisteredListener;

            if (!in_array($eventName, $subscribedEventNames, true)) {
                $subscribedEventNames[] = $eventName;

                // @TODO take prefetchSize from options
                $headers = ['ack' => 'client', 'destination' => $eventName, 'activemq.prefetchSize' => $this->prefetchSize];
                
                if ($this->clientId) {
                    $headers["activemq.subcriptionName"] = $this->clientId;
                }

                // @TODO sync gibts net…
                //headers['receipt'] = md5(microtime());

                $frame = Frame::createNew('SUBSCRIBE', $headers);
                $this->_writeFrame($frame);                
            }

            $this->addListener($eventName, $listener);
        }
    }

    /**
     * Current stomp session ID
     *
     * @return string
     */
    public function getSessionId()
    {
        return $this->_sessionId;
    }   

    public function ack(Frame $frame, $transactionId = null)
    {
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
        $this->addListener(EventType::CONNECTED, function(FrameEvent $event) {
            $this->_sessionId = $event->getFrame()->getHeaders()['session'];
            $this->addPreRegisteredListeners();
        });

        $this->addListener(EventType::ERROR, function(ErrorEvent $event) {
            // @TODO log this
            var_dump('### ERROR', $event->getMessage());
        });

        $connectionFrame = Frame::createNew('CONNECT', ['login' => $this->options['username'], 'passcode' => $this->options['password']]);
        $this->_writeFrame($connectionFrame);

        $this->startEventLoop();
    }






    /**
     * Send a message to a destination in the messaging system 
     *
     * @param string $destination Destination queue
     * @param string|Frame $msg Message
     * @param array $properties
     * @param boolean $sync Perform request synchronously
     * @return boolean
     */
    public function send ($destination, $msg, $properties = array(), $sync = null)
    {
        if ($msg instanceof Frame) {
            $msg->headers['destination'] = $destination;
            if (is_array($properties)) $msg->headers = array_merge($msg->headers, $properties);
            $frame = $msg;
        } else {
            $headers = $properties;
            $headers['destination'] = $destination;
            $frame = new Frame('SEND', $headers, $msg);
        }
        $this->_prepareReceipt($frame, $sync);
        $this->_writeFrame($frame);
        return $this->_waitForReceipt($frame, $sync);
    }
    /**
     * Prepair frame receipt
     *
     * @param Frame $frame
     * @param boolean $sync
     */
    /*protected function _prepareReceipt (Frame $frame, $sync)
    {
        $receive = $this->sync;
        if ($sync !== null) {
            $receive = $sync;
        }
        if ($receive == true) {
            $frame->headers['receipt'] = md5(microtime());
        }
    }*/
    /**
     * Wait for receipt
     *
     * @param Frame $frame
     * @param boolean $sync
     * @return boolean
     * @throws StompException
     *//*
    protected function _waitForReceipt (Frame $frame, $sync)
    {

        $receive = $this->sync;
        if ($sync !== null) {
            $receive = $sync;
        }
        if ($receive == true) {
            $id = (isset($frame->headers['receipt'])) ? $frame->headers['receipt'] : null;
            if ($id == null) {
                return true;
            }
            $frame = $this->readFrame();
            if ($frame instanceof Frame && $frame->command == 'RECEIPT') {
                if ($frame->headers['receipt-id'] == $id) {
                    return true;
                } else {
                    throw new StompException("Unexpected receipt id {$frame->headers['receipt-id']}", 0, $frame->body);
                }
            } else {
                if ($frame instanceof Frame) {
                    throw new StompException("Unexpected command {$frame->command}", 0, $frame->body);
                } else {
                    throw new StompException("Receipt not received");
                }
            }
        }
        return true;
    }*/
    /**
     * Register to listen to a given destination
     *
     * @param string $destination Destination queue
     * @param array $properties
     * @param boolean $sync Perform request synchronously
     * @return boolean
     * @throws StompException
     *//*
    public function subscribe ($destination, $properties = null, $sync = null)
    {
        $headers = array('ack' => 'client');
        $headers['activemq.prefetchSize'] = $this->prefetchSize;
        if ($this->clientId != null) {
            $headers["activemq.subcriptionName"] = $this->clientId;
        }
        if (isset($properties)) {
            foreach ($properties as $name => $value) {
                $headers[$name] = $value;
            }
        }
        $headers['destination'] = $destination;
        $frame = new Frame('SUBSCRIBE', $headers);
        $this->_prepareReceipt($frame, $sync);
        $this->_writeFrame($frame);
        if ($this->_waitForReceipt($frame, $sync) == true) {
            $this->_subscriptions[$destination] = $properties;
            return true;
        } else {
            return false;
        }
    }*/
    /**
     * Remove an existing subscription
     *
     * @param string $destination
     * @param array $properties
     * @param boolean $sync Perform request synchronously
     * @return boolean
     * @throws StompException
     */
    public function unsubscribe ($destination, $properties = null, $sync = null)
    {
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
        if ($this->_waitForReceipt($frame, $sync) == true) {
            unset($this->_subscriptions[$destination]);
            return true;
        } else {
            return false;
        }
    }
    /**
     * Start a transaction
     *
     * @param string $transactionId
     * @param boolean $sync Perform request synchronously
     * @return boolean
     * @throws StompException
     */
    public function begin ($transactionId = null, $sync = null)
    {
        $headers = array();
        if (isset($transactionId)) {
            $headers['transaction'] = $transactionId;
        }
        $frame = new Frame('BEGIN', $headers);
        $this->_prepareReceipt($frame, $sync);
        $this->_writeFrame($frame);
        return $this->_waitForReceipt($frame, $sync);
    }
    /**
     * Commit a transaction in progress
     *
     * @param string $transactionId
     * @param boolean $sync Perform request synchronously
     * @return boolean
     * @throws StompException
     */
    public function commit ($transactionId = null, $sync = null)
    {
        $headers = array();
        if (isset($transactionId)) {
            $headers['transaction'] = $transactionId;
        }
        $frame = new Frame('COMMIT', $headers);
        $this->_prepareReceipt($frame, $sync);
        $this->_writeFrame($frame);
        return $this->_waitForReceipt($frame, $sync);
    }
    /**
     * Roll back a transaction in progress
     *
     * @param string $transactionId
     * @param boolean $sync Perform request synchronously
     */
    public function abort ($transactionId = null, $sync = null)
    {
        $headers = array();
        if (isset($transactionId)) {
            $headers['transaction'] = $transactionId;
        }
        $frame = new Frame('ABORT', $headers);
        $this->_prepareReceipt($frame, $sync);
        $this->_writeFrame($frame);
        return $this->_waitForReceipt($frame, $sync);
    }
    /**
     * Acknowledge consumption of a message from a subscription
	 * Note: This operation is always asynchronous
     *
     * @param string|Frame $messageMessage ID
     * @param string $transactionId
     * @return boolean
     * @throws StompException
     */


    /**
     * Write frame to server
     *
     * @param Frame $stompFrame
     */
    protected function _writeFrame (Frame $stompFrame)
    {
        if (!is_resource($this->_socket)) {
            throw new StompException('Socket connection hasn\'t been established');
        }

        $data = $stompFrame->__toString();
        $r = fwrite($this->_socket, $data, strlen($data));
        if ($r === false || $r == 0) {
            $this->_reconnect();
            $this->_writeFrame($stompFrame);
        }
    }
    
    /**
     * Set timeout to wait for content to read
     *
     * @param int $seconds_to_wait  Seconds to wait for a frame
     * @param int $milliseconds Milliseconds to wait for a frame
     */
    public function setReadTimeout($seconds, $milliseconds = 0) 
    {
        $this->_read_timeout_seconds = $seconds;
        $this->_read_timeout_milliseconds = $milliseconds;
    }

    /**
     * Check if there is a frame to read
     *
     * @return boolean
     */
    public function hasFrameToRead()
    {
        $read = array($this->_socket);
        $write = null;
        $except = null;
        
        $has_frame_to_read = @stream_select($read, $write, $except, $this->_read_timeout_seconds, $this->_read_timeout_milliseconds);
        
        if ($has_frame_to_read !== false)
            $has_frame_to_read = count($read);


        if ($has_frame_to_read === false) {
            throw new StompException('Check failed to determine if the socket is readable');
        } else if ($has_frame_to_read > 0) {
            return true;
        } else {
            return false; 
        }
    }
    
    /**
     * Reconnects and renews subscriptions (if there were any)
     * Call this method when you detect connection problems     
     */
    protected function _reconnect ()
    {
        $subscriptions = $this->_subscriptions;
        
        $this->connect($this->_username, $this->_password);
        foreach ($subscriptions as $dest => $properties) {
            $this->subscribe($dest, $properties);
        }
    }

    /**
     * Graceful disconnect from the server
     *
     */
    public function disconnect ()
    {
return;

        $headers = array();

        if ($this->clientId != null) {
            $headers["client-id"] = $this->clientId;
        }

        if (is_resource($this->_socket)) {
            $this->_writeFrame(new Frame('DISCONNECT', $headers));
            fclose($this->_socket);
        }
        $this->_socket = null;
        $this->_sessionId = null;
        $this->_currentHost = -1;
        $this->_subscriptions = array();
        $this->_username = '';
        $this->_password = '';
    }
    
    /**
     * Graceful object desruction
     *
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}