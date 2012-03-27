<?php

/**
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

namespace Stomp\Event;

use PHPUnit_Framework_TestCase;

class FrameEventTest extends PHPUnit_Framework_TestCase
{
	private $connectionMock;
	private $frameMock;

	public function setUp()
	{
		$this->connectionMock = $this->getMockBuilder('Stomp\StompClient')
									 ->disableOriginalConstructor()
									 // hack - mock only certain methods as PHPUnit cant cope with callable typehint yet
									 ->setMethods(['connect', 'disconnect'])
									 ->getMock();

		$this->frameMock = $this->getMockBuilder('Stomp\Value\Frame')
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