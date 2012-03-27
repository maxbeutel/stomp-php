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

$client = new StompClient('tcp://localhost:61613');
$client->connect();

$client->beginTransaction('transaction_1');
$client->send('/queue/simple-example/transactions', 'message for transaction 1', ['transaction' => 'transaction_1']);
$client->abortTransaction('transaction_1');

$client->beginTransaction('transaction_2');
$client->send('/queue/simple-example/transactions', 'message for transaction 2', ['transaction' => 'transaction_2']);
$client->send('/queue/simple-example/transactions', 'another message for transaction 2', ['transaction' => 'transaction_2']);
$client->commitTransaction('transaction_2');

$client->disconnect();