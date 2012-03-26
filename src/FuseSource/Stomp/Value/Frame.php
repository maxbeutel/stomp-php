<?php

namespace FuseSource\Stomp\Value;

use FuseSource\Stomp\Event\EventType;

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
 * Stomp Frames are messages that are sent and received on a stomp connection.
 *
 * @package Stomp
 */
class Frame
{
    private $command;
    private $headers = [];
    private $body;
    
    /**
     * Constructor
     *
     * @param string $command
     * @param array $headers
     * @param string $body
     */
    private function __construct($command, array $headers, $body)
    {
        $this->command = $command;
        $this->headers = $headers;
        $this->body = $body;
    }
    
    public static function unserializeFrom($data)
    {
        list($header, $body) = explode("\n\n", $data, 2);
        
        $header = explode("\n", $header);
        $headers = [];
        
        $command = null;
        
        foreach ($header as $v) {
            if (isset($command)) {
                list ($name, $value) = explode(':', $v, 2);
                $headers[$name] = $value;
            } else {
                $command = $v;
            }
        }
        
        return new static($command, $headers, $body);
    }
    
    public static function createNew($command = null, $headers = null, $body = null)
    {
        return new static($command, (array) $headers, $body);
    }
    
    public function getEventName()
    {
        if ($this->command === 'CONNECTED') {
            return EventType::FRAME_CONNECTED;
        }
        
        if ($this->command === 'ERROR') {
            return EventType::FRAME_ERROR;
        }

        return $this->headers['destination'];
    }
    
    public function getCommand()
    {
        return $this->command;
    }
    
    public function getHeaders()
    {
        return $this->headers;
    }
    
    // @TODO take if (isset($frame->headers['transformation']) && $frame->headers['transformation'] == 'jms-map-json') into account?
    public function getBody()
    {
        return $this->body;
    }
    
    /**
     * Convert frame to transportable string
     *
     * @return string
     */
    public function __toString()
    {
        $data = $this->command . "\n";
        
        foreach ($this->headers as $name => $value) {
            $data .= $name . ': ' . $value . "\n";
        }
        
        $data .= "\n";
        $data .= $this->body;
        return $data .= "\x00";
    }
}