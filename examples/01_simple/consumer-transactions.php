<?php

/**
 *
 * Copyright 2012 Max Beutel <me@maxbeutel.de>
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

require_once __DIR__ . '/../../autoload.php';

use Stomp\StompClient;
use Stomp\Event\FrameEvent;
use Stomp\Event\SystemEventType;

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