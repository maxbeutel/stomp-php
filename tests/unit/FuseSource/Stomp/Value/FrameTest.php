<?php

namespace FuseSource\Stomp\Value;

use PHPUnit_Framework_TestCase;

class FrameTest extends PHPUnit_Framework_TestCase
{
	public function setUp()
	{
	}

	public function testCreateNew()
	{
		$frame = Frame::createNew();
		$this->assertNull($frame->getCommand());
		$this->assertSame([], $frame->getHeaders());
		$this->assertNull($frame->getBody());
		$this->assertFalse($frame->waitForReceipt());

		$frame = Frame::createNew('COMMAND', ['headerKey' => 'headerValue'], 'body', true);
		$this->assertSame('COMMAND', $frame->getCommand());
		$this->assertSame('headerValue', $frame->getHeaders()['headerKey']);
		$this->assertSame('body', $frame->getBody());
		$this->assertTrue($frame->waitForReceipt());
		$this->assertArrayHasKey('receipt', $frame->getHeaders());
	}

	public function testUnserializeFromInvalidData()
	{
		$frame = Frame::unserializeFrom(null);
	}
}