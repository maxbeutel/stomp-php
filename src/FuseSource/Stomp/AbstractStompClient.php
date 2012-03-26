<?php

namespace FuseSource\Stomp;

use FuseSource\Stomp\Exception\StompException;
use FuseSource\Stomp\Exception\ConnectionException;
use FuseSource\Stomp\Value\Uri;
use FuseSource\Stomp\Value\Frame;
use FuseSource\Stomp\Event\EventType;
use FuseSource\Stomp\Event\FrameEvent;
use FuseSource\Stomp\Event\ErrorEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Event;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
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
abstract class AbstractStompClient
{
    protected $_hosts = array();
    protected $_params = array();
    protected $_subscriptions = array();
    
    protected $_username = '';
    protected $_password = '';
    protected $_read_timeout_seconds = 60;
    protected $_read_timeout_milliseconds = 0;
    protected $_connect_timeout_seconds = 60;
    


    protected $brokerUri;
    protected $options;
    protected $logger;

    protected $_attempts = 10;
    protected $_socket;
    protected $_sessionId;


    /**
     * Constructor
     *
     * @param string $brokerUri Broker URL
     * @throws StompException
     */
    public function __construct($uriString, array $options = [])
    {
        $defaultOptions = [
            'username'      => null,
            'password'      => null,
            'clientId'      => null,
            'prefetchSize'  => 1,
            'readTimeout'   
        ];

        $this->brokerUri = new Uri($uriString);
        $this->options = array_merge($defaultOptions, $options);
        $this->logger = new Logger('StompClient');
        $this->logger->pushHandler(new StreamHandler('php://stdout'));
    }

    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Make socket connection to the server
     *
     * @throws StompException
     */
    protected function openSocket()
    {
        $connected = false;
        $connectionAttempts = 0;

        while (!$connected && $connectionAttempts < $this->_attempts) { 
            $this->_socket = @fsockopen(
                'tcp://' . $this->brokerUri->getHost(), 
                $this->brokerUri->getPort(), 
                $connectionErrorNumber, 
                $connectionError, 
                $this->_connect_timeout_seconds
            );
            
            if (is_resource($this->_socket)) {
                $this->logger->info(sprintf('Successfully connected to socket at attempt %d', $connectionAttempts));

                return;
            }
            
            if ($this->_socket) {
                @fclose($this->_socket);
            }

            $connectionAttempts++;
        }
        
        throw new ConnectionException(sprintf('Could not connect to broker at attempt %d', $connectionAttempts));
    }

    public function getSessionId()
    {
        return $this->_sessionId;
    }

    protected function _writeFrame(Frame $frame)
    {
        $data = (string) $frame;

        $this->logger->debug('Writing frame data', array('data' => $data));

        $success = (bool) fwrite($this->_socket, $data, strlen($data));

        $this->logger->debug('Write frame success', array('success' => $success));

        if (!$success) {
            $this->openSocket();
            $this->_writeFrame($frame);
        }
    }

    public function disconnect()
    {
        $this->logger->debug('Shutting down gracefully');

        $headers = [];

        if ($this->options['clientId']) {
            $headers['client-id'] = $this->options['clientId'];
        }

        if (is_resource($this->_socket)) {
            $this->_writeFrame(Frame::createNew('DISCONNECT', $headers));
            fclose($this->_socket);
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}