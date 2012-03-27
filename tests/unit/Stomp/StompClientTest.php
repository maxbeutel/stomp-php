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

	public function testSubscribeNotConnected()
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