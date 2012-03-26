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

class SendClient extends AbstractStompClient
{
    public function send($destination, $msg, $properties = [], $waitForReceipt = false)
    {
        $headers = array_merge(['destination' => $destination], $properties);
        
        $frame = Frame::createNew('SEND', $headers, $msg, $waitForReceipt);

        $this->_writeFrame($frame);
        return $this->_waitForReceipt($frame);
    }
    /**
     * Wait for receipt
     *
     * @param Frame $frame
     * @param boolean $sync
     * @return boolean
     * @throws StompException
     */
    protected function _waitForReceipt(Frame $frame, $sync)
    {
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
    }

    public function begin($transactionId = null, $sync = null)
    {
        $headers = [];

        if ($transactionId) {
            $headers['transaction'] = $transactionId;
        }

        $frame = Frame::createNew('BEGIN', $headers);
        $this->_prepareReceipt($frame, $sync);
        $this->_writeFrame($frame);

        return $this->_waitForReceipt($frame, $sync);
    }

    public function commit($transactionId = null, $sync = null)
    {
        $headers = [];
        
        if ($transactionId) {
            $headers['transaction'] = $transactionId;
        }
        
        $frame = Frame::createNew('COMMIT', $headers);
        $this->_prepareReceipt($frame, $sync);
        $this->_writeFrame($frame);
        
        return $this->_waitForReceipt($frame, $sync);
    }

    public function abort($transactionId = null, $sync = null)
    {
        $headers = [];

        if ($transactionId) {
            $headers['transaction'] = $transactionId;
        }

        $frame = Frame::createNew('ABORT', $headers);
        $this->_prepareReceipt($frame, $sync);
        $this->_writeFrame($frame);

        return $this->_waitForReceipt($frame, $sync);
    }
}