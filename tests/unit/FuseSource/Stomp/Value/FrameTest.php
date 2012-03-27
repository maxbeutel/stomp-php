<?php

namespace FuseSource\Stomp\Value;

use PHPUnit_Framework_TestCase;

/**
 *
 * Copyright 2005-2006 The Apache Software Foundation
 * Source Code modified 2012 by Max Beutel <me@maxbeutel.de>
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

	public function testUnserializeFromInvalidData()
	{
		$frame = Frame::unserializeFrom(null);
	}
}