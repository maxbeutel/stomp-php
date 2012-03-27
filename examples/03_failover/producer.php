<?php

require_once __DIR__ . '/../../autoload.php';

use FuseSource\Stomp\StompClient;

$client = new StompClient('failover://(tcp://localhost:61613,tcp://localhost:61612)');
$client->connect();

for ($i = 0; $i < 10; $i++) {
	$client->send('/queue/simple-example/persistent', 'frob');
}

$client->disconnect();