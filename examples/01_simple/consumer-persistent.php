<?php

require_once __DIR__ . '/../../autoload.php';

use FuseSource\Stomp\StompClient;
use FuseSource\Stomp\Event\FrameEvent;

$client = new StompClient('tcp://localhost:61613');
$client->connect();

$client->subscribe('/queue/simple-example/persistent', function(FrameEvent $event) {
    $frameBody  = $event->getFrame()->getBody();

    var_dump($frameBody);

    $event->getConnection()->ack($event->getFrame());
});

$client->listen();