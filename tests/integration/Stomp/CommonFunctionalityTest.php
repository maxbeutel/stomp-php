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

class CommonFunctionalityTest extends PHPUnit_Framework_TestCase
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
	 */
	public function testBasicConnectDisconnect()
	{
		$client = new StompClient('tcp://localhost:61613', ['loggerInstance' => $this->loggerMock]);
		$client->connect();
		$client->disconnect();

		$output = $this->getStompServerOutput();

		$this->assertContains('CONNECT {"login":"","passcode":"","content-length":0}', $output);
		$this->assertContains('DISCONNECT {"content-length":0', $output);
	}

	/**
	 * @group integration
	 */
	public function testBasicConnectDisconnectWithCredentials()
	{
		$client = new StompClient('tcp://localhost:61613', ['username' => 'user', 'password' => 'secret', 'loggerInstance' => $this->loggerMock]);
		$client->connect();
		$client->disconnect();

		$output = $this->getStompServerOutput();

		$this->assertContains('CONNECT {"login":"user","passcode":"secret","content-length":0}', $output);
		$this->assertContains('DISCONNECT {"content-length":0', $output);
	}

	/**
	 * @group integration
	 */
	public function testMultipleDisconnectsDontSendMultipleCommands()
	{
		$client = new StompClient('tcp://localhost:61613', ['loggerInstance' => $this->loggerMock]);
		$client->connect();
		$client->disconnect();
		$client->__destruct();

		$output = $this->getStompServerOutput();

		$this->assertContains('CONNECT {"login":"","passcode":"","content-length":0}', $output);
		$this->assertContains('DISCONNECT {"content-length":0', $output);
	}

	/**
	 * @group integration
	 */
	public function testConnectedClientHasSessionId()
	{
		$client = new StompClient('tcp://localhost:61613', ['loggerInstance' => $this->loggerMock]);
		$this->assertNull($client->getSessionId());
		$client->connect();
		$this->assertNotNull($client->getSessionId());
		$client->disconnect();
	}
}