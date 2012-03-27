<?php

/**
 *
 * Copyright 2012 Max Beutel <me@maxbeutel.de>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace Stomp\Value;

use PHPUnit_Framework_TestCase;

class FrameTest extends PHPUnit_Framework_TestCase
{
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

	public function testUnserializeFromInvalidData_1()
	{
		$this->setExpectedException('InvalidArgumentException', 'Invalid data given');

		$frame = Frame::unserializeFrom(null);
	}

	public function testUnserializeFromInvalidData_2()
	{
		$this->setExpectedException('InvalidArgumentException', 'Invalid data given');

		$frame = Frame::unserializeFrom(' ');
	}

	public function testUnserializeFromInvalidData_3()
	{
		$this->setExpectedException('InvalidArgumentException', 'Invalid data given');

		$frame = Frame::unserializeFrom("\n   ");
	}

	public function testUnserializeFrom()
	{
		$data = 'SEND
destination:/queue/a
content-type:text/plain

hello queue a';

		$frame = Frame::unserializeFrom($data);
		$this->assertSame('SEND', $frame->getCommand());
		$this->assertSame('/queue/a', $frame->getEventName());
		$this->assertFalse($frame->waitForReceipt());
		$this->assertSame('hello queue a', $frame->getBody());
		$this->assertSame(['destination' => '/queue/a', 'content-type' => 'text/plain'], $frame->getHeaders());
	}

	public function testUnserializeFromWithoutBody()
	{
		$data = 'SEND
destination:/queue/a
content-type:text/plain';

		$frame = Frame::unserializeFrom($data);
		$this->assertSame('SEND', $frame->getCommand());
		$this->assertSame('/queue/a', $frame->getEventName());
		$this->assertFalse($frame->waitForReceipt());
		$this->assertNull($frame->getBody());
		$this->assertSame(['destination' => '/queue/a', 'content-type' => 'text/plain'], $frame->getHeaders());
	}
}