<?php

require_once __DIR__ . '/../../autoload.php';

use FuseSource\Stomp\StompClient;

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