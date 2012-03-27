<?php

namespace FuseSource\Stomp\Event;

use PHPUnit_Framework_TestCase;

class FrameEventTest extends PHPUnit_Framework_TestCase
{
	private $connectionMock;
	private $frameMock;

	public function setUp()
	{
		$this->connectionMock = $this->getMockBuilder('FuseSource\Stomp\StompClient')
									 ->disableOriginalConstructor()
									 ->getMock();

		$this->frameMock = $this->getMockBuilder('FuseSource\Stomp\Value\Frame')
								->disableOriginalConstructor()
								->getMock();
	}

	public function testSimpleGetter()
	{
		$event = new FrameEvent($this->connectionMock, $this->frameMock);
		$this->assertSame($this->connectionMock, $event->getConnection());
		$this->assertSame($this->frameMock, $event->getFrame());
	}
}