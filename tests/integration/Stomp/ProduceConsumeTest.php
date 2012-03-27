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
use Stomp\Event\FrameEvent;
use Stomp\Helper\PHPUnit\StompServerTrait;

class ProduceConsumeTest extends PHPUnit_Framework_TestCase
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

	private function startStompConsumer()
	{

	}

	private function stopStompConsumer()
	{

	}

	public function testSomething()
	{
		#$this->startBackgroundStompListener();
		#$this->assertTrue(bla(1));
		#$this->assertFalse(bla(2));
	}

	/**
	 * @group integration
	 * @large
	 */
	/*public function testProduceConsumeSnyc()
	{
		$client = new StompClient('tcp://localhost:61613', ['loggerInstance' => $this->loggerMock, 'waitForReceipt' => true]);
		$client->connect();

		$client->subscribe('/queue/test', function(FrameEvent $event) {
			$event->getConnection()->ack($event->getFrame());
			$event->getConnection()->disconnect();
			$this->assertSame('message 1', $event->getFrame()->getBody());
		});

		$client->send('/queue/test', 'message 1');

		$client->listen();
	}*/

	/**
	 * @group integration
	 * @large
	 *//*
	public function testProduceConsume()
	{
		$client = new StompClient('tcp://localhost:61613', ['loggerInstance' => $this->loggerMock]);
		$client->connect();

		$client->subscribe('/queue/test', function(FrameEvent $event) {
			$event->getConnection()->ack($event->getFrame());
			$event->getConnection()->disconnect();
			$this->assertSame('message 1', $event->getFrame()->getBody());
		});

		$client->send('/queue/test', 'message 1');

		$client->listen();
	}*/
}