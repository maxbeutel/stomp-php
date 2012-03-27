<?php

require_once __DIR__ . '/../../autoload.php';

use FuseSource\Stomp\StompClient;
use FuseSource\Stomp\Event\FrameEvent;
use FuseSource\Stomp\Event\SystemEventType;

$client = new StompClient('tcp://localhost:61613');
$client->connect();

$client->beginTransaction('transaction_1');

$client->subscribe('/queue/simple-example/transactions', function(FrameEvent $event) {
	static $messagesCount = 0;

	$messagesCount++;

    $frameBody  = $event->getFrame()->getBody();

    var_dump($messagesCount, $frameBody);

    $event->getConnection()->ack($event->getFrame(), 'transaction_1');

    // number taken from producer-transactions.php
    // if you want to abort a transaction, this would be the place to do so
    // might also make sense to begin a new transaction for next messages batch here
    if ($messagesCount == 2) {
    	$event->getConnection()->commitTransaction('transaction_1');
    }
});

$client->listen();