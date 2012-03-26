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

class SendClient extends AbstractStompClient
{

    /**
     * Send a message to a destination in the messaging system 
     *
     * @param string $destination Destination queue
     * @param string|Frame $msg Message
     * @param array $properties
     * @param boolean $sync Perform request synchronously
     * @return boolean
     */
    public function send($destination, $msg, $properties = [], $sync = null)
    {
        $headers = $properties;
        $headers['destination'] = $destination;
        
        $frame = Frame::createNew('SEND', $headers, $msg);

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
    protected function _prepareReceipt(Frame $frame, $sync)
    {
        $receive = $this->sync;

        if ($sync !== null) {
            $receive = $sync;
        }

        if ($receive == true) {
            $frame->headers['receipt'] = md5(microtime());
        }
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