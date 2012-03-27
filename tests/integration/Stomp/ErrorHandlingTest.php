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
use Stomp\StompClient;
use Stomp\Helper\PHPUnit\StompServerTrait;

class ErrorHandlingTest extends PHPUnit_Framework_TestCase
{
	use StompServerTrait;

	private $loggerMock;

	public function setUp()
	{
		$this->startStompServer();

		$this->loggerMock = $this->getMockBuilder('Monolog\Logger')
								 ->disableOriginalConstructor()
								 ->getMock();
	}

	public function tearDown()
	{
		$this->stopStompServer();
	}

	/**
	 * @group integration
	 * @large
	 */
	public function testBasicConnectDisconnect()
	{
		$client = new StompClient('tcp://localhost:1', ['loggerInstance' => $this->loggerMock]);

		$this->setExpectedException('Stomp\Exception\ConnectionException', 'Could not connect to any broker');
		$client->connect();
	}
}