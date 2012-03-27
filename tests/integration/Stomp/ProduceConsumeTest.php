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
		$this->startStompConsumer();

		$this->loggerMock = $this->getMockBuilder('Monolog\Logger')
								 ->disableOriginalConstructor()
								 ->getMock();
	}

	public function tearDown()
	{
		$this->stopStompConsumer();
		$this->stopStompServer();
	}

	private $stompConsumerProcess;
	private $currentConsumerOutputFile;

	private function startStompConsumer()
	{
		$this->stopStompConsumer();

		$this->currentConsumerOutputFile = STOMP_TEST_DIR . '/integration/fixtures/output-files/' . microtime(true) . '-' . md5(uniqid(mt_rand(), true)) . '.consumer';

		$cmd = 'php ' . STOMP_TEST_DIR . '/../examples/01_simple/consumer.php';

		$descriptorspec = [
			0 => ['pipe', 'r'],
			1 => ['file', $this->currentConsumerOutputFile, 'w'],
			2 => ['pipe', 'w'],
		];

		$this->stompConsumerProcess = proc_open($cmd, $descriptorspec, $pipes, sys_get_temp_dir());

		sleep(1);
	}

	private function stopStompConsumer()
	{
		if (is_resource($this->stompConsumerProcess)) {
		#	proc_close($this->stompConsumerProcess);
		}

		foreach (glob(STOMP_TEST_DIR . '/integration/fixtures/output-files/*.consumer') as $outputFile) {
			unlink($outputFile);
		}

		exec('ps -ef | grep "/consumer.php" | awk \'{print $2}\' | xargs -r kill 2>&1');

		$this->stompConsumerProcess = $this->currentConsumerOutputFile = null;
	}

	private function getStompConsumerOutput()
	{
		if (!is_file($this->currentConsumerOutputFile)) {
			return self::$NO_OUTPUT;
		}

		// hack: not all data might have been written to disk yet
		sleep(1);

		return file_get_contents($this->currentConsumerOutputFile);
	}

	public function testSomething()
	{
$client = new StompClient('tcp://localhost:61613');
$client->connect();

for ($i = 0; $i < 10; $i++) {
	$client->send('/queue/simple-example', 'message ' . ($i + 1));
}

$client->disconnect();

var_dump($this->getStompConsumerOutput());

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