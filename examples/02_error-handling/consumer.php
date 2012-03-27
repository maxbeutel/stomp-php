<?php

require_once __DIR__ . '/../../autoload.php';

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

use Stomp\StompClient;
use Stomp\Event\FrameEvent;
use Stomp\Exception\FrameException;
use Stomp\Exception\ReceiptException;
use Stomp\Exception\ConnectionException;

// NOTE: the event loop is exited when an exception is thrown from the client
try {
	$client = new StompClient('tcp://localhost:61613');
	$client->connect();

	$client->subscribe('/queue/simple-example', function(FrameEvent $event) {
	    $frameBody  = $event->getFrame()->getBody();

	    var_dump($frameBody);

	    $event->getConnection()->ack($event->getFrame());
	});

	$client->listen();
} catch (ReceiptException $e) {
	// only relevant for sync connections
	// occurs when a receipt sent from the server did not match the expected message id
} catch (FrameException $e) {
	// this exception is thrown when some unexpected/unknown frame type was encountered
} catch (ConnectionException $e) {
	// no socket connection could be opened
}
