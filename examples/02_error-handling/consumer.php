<?php

require_once __DIR__ . '/../../autoload.php';

use FuseSource\Stomp\StompClient;
use FuseSource\Stomp\Event\FrameEvent;
use FuseSource\Stomp\Exception\FrameException;
use FuseSource\Stomp\Exception\ReceiptException;
use FuseSource\Stomp\Exception\TransportException;
use FuseSource\Stomp\Exception\ConnectionException;

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
} catch (FrameException $e) {
	// this exception is thrown when some unexpected/unknown frame type was encountered
} catch (TransportException $e) {
	// something went wrong while reading/writing to the socket
} catch (ReceiptException $e) {
	// only relevant for sync connections
	// occurs when a receipt sent from the server did not match the expected message id
} catch (ConnectionException $e) {
	// no socket connection could be opened
}
