<?php

require_once __DIR__ . '/../../autoload.php';

use FuseSource\Stomp\StompClient;

$client = new StompClient('tcp://localhost:61613', ['username' => 'user', 'password' => 'secret']);
$client->connect();

for ($i = 0; $i < 10; $i++) {
	$client->send('/queue/simple-example/authenticated', 'frob');
}

$client->disconnect();