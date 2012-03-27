<?php

require_once __DIR__ . '/../../autoload.php';

use FuseSource\Stomp\StompClient;
use FuseSource\Stomp\Event\FrameEvent;

$con = new StompClient('failover://(tcp://localhost:61613,tcp://localhost:61612)');
$con->connect();

$con->subscribe('/queue/simple-example/persistent', function (FrameEvent $event) {
    $frameBody  = $event->getFrame()->getBody();

    var_dump($frameBody);

    $event->getConnection()->ack($event->getFrame());
});

$con->listen();