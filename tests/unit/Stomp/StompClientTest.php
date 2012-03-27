<?php

namespace Stomp;

use PHPUnit_Framework_TestCase;
use Stomp\Value\Frame;

class ConnectedStompClientDummy extends StompClient
{
	public function __construct($uriString, array $options = [])
	{
		parent::__construct($uriString, $options);
		$this->connected = true;
	}

	protected function writeFrame(Frame $frame)
	{
		// do nothing
	}

	public function disconnect()
	{
		// do nothing
	}
}

class StompClientTest extends PHPUnit_Framework_TestCase
{
	private $loggerMock;

	public function setUp()
	{
		$this->loggerMock = $this->getMockBuilder('Monolog\Logger')
								 ->disableOriginalConstructor()
								 ->getMock();
	}

	public function testCantSubscribeIfNotConnected()
	{
		$this->setExpectedException('BadMethodCallException', 'Cant subscribe before connecting');

		$client = new StompClient('tcp://localhost:61613');
		$client->subscribe('/queue/test', function() {});
	}

	public function testSubscribeWithInvalidEventName()
	{
		$this->setExpectedException('InvalidArgumentException', 'Event name must begin with one of /queue /topic /temp-queue /temp-topic, got "/foo/bar"');

		$client = new ConnectedStompClientDummy('tcp://localhost:61613', ['loggerInstance' => $this->loggerMock]);
		$client->subscribe('/foo/bar', function() {});
	}

	public function testSubcribe()
	{
		$client = new ConnectedStompClientDummy('tcp://localhost:61613', ['loggerInstance' => $this->loggerMock]);
		$client->subscribe('/queue/test', function() {});
		$client->subscribe('/temp-queue/test', function() {});
		$client->subscribe('/topic/test', function() {});
		$client->subscribe('/temp-topic/test', function() {});
	}
}