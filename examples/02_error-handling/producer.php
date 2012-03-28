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
use Stomp\Exception\FrameException;
use Stomp\Exception\ReceiptException;
use Stomp\Exception\ConnectionException;

try {
	$client = new StompClient('tcp://localhost:61613');
	$client->connect();

	for ($i = 0; $i < 10; $i++) {
		$client->send('/queue/simple-example', 'message ' . ($i + 1));
	}

	$client->disconnect();
} catch (ReceiptException $e) {
	// only relevant for sync connections
	// occurs when a receipt sent from the server did not match the expected message id
} catch (FrameException $e) {
	// this exception is thrown when some unexpected/unknown frame type was encountered
} catch (ConnectionException $e) {
	// no socket connection could be opened
}