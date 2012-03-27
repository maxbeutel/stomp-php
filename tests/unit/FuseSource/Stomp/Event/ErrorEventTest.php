<?php

namespace FuseSource\Stomp\Event;

use PHPUnit_Framework_TestCase;

class ErrorEventTest extends PHPUnit_Framework_TestCase
{
	private $connectionMock;
	private $message;

	public function setUp()
	{
		$this->connectionMock = $this->getMockBuilder('FuseSource\Stomp\StompClient')
									 ->disableOriginalConstructor()
									 ->getMock();

		$this->message = 'Some bad error';
	}

	public function testSimpleGetter()
	{
		$event = new ErrorEvent($this->connectionMock, $this->message);
		$this->assertSame($this->connectionMock, $event->getConnection());
		$this->assertSame($this->message, $event->getMessage());
	}
}