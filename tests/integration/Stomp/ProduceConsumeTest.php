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

	public function setUp()
	{
		$this->startStompServer();
	}

	public function tearDown()
	{
		$this->stopStompConsumer();
		$this->stopStompServer();
	}

	private function messageOutputAsserts($output)
	{
		$this->assertContains('string(9) "message 1"', $output);
		$this->assertContains('string(9) "message 2"', $output);
		$this->assertContains('string(9) "message 3"', $output);
		$this->assertContains('string(9) "message 4"', $output);
		$this->assertContains('string(9) "message 5"', $output);
		$this->assertContains('string(9) "message 6"', $output);
		$this->assertContains('string(9) "message 7"', $output);
		$this->assertContains('string(9) "message 8"', $output);
		$this->assertContains('string(9) "message 9"', $output);
		$this->assertContains('string(10) "message 10"', $output);
	}

	/**
	 * @group integration
	 * @large
	 */
	public function testExample_transactions()
	{
		$this->startStompConsumer(STOMP_TEST_DIR . '/../examples/01_simple/consumer-transactions.php');

		require_once STOMP_TEST_DIR . '/../examples/01_simple/producer-transactions.php';

		$output = $this->getStompConsumerOutput();

		// skip this for now as the dummy node stomp server doesnt abort transactions
		//$this->assertNotContains('string(25) "message for transaction 1"', $output);
		$this->assertContains('string(25) "message for transaction 2"', $output);
		$this->assertContains('string(33) "another message for transaction 2"', $output);
	}

	/**
	 * @group integration
	 * @large
	 */
	public function testExample_simple()
	{
		$this->startStompConsumer(STOMP_TEST_DIR . '/../examples/01_simple/consumer.php');

		require_once STOMP_TEST_DIR . '/../examples/01_simple/producer.php';

		$this->messageOutputAsserts($this->getStompConsumerOutput());
	}

	/**
	 * @group integration
	 * @large
	 */
	public function testExample_persistent()
	{
		$this->startStompConsumer(STOMP_TEST_DIR . '/../examples/01_simple/consumer-persistent.php');

		require_once STOMP_TEST_DIR . '/../examples/01_simple/producer-persistent.php';

		$this->messageOutputAsserts($this->getStompConsumerOutput());
	}

	/**
	 * @group integration
	 * @large
	 */
	public function testExample_sync()
	{
		$this->startStompConsumer(STOMP_TEST_DIR . '/../examples/01_simple/consumer-sync.php');

		require_once STOMP_TEST_DIR . '/../examples/01_simple/producer-sync.php';

		$this->messageOutputAsserts($this->getStompConsumerOutput());
	}

	/**
	 * @group integration
	 * @large
	 */
	public function testExample_authenticated()
	{
		$this->startStompConsumer(STOMP_TEST_DIR . '/../examples/01_simple/consumer-authenticated.php');

		require_once STOMP_TEST_DIR . '/../examples/01_simple/producer-authenticated.php';

		$this->messageOutputAsserts($this->getStompConsumerOutput());
	}

	/**
	 * @group integration
	 * @large
	 */
	public function testExample_errorHandling()
	{
		$this->startStompConsumer(STOMP_TEST_DIR . '/../examples/02_error-handling/consumer.php');

		require_once STOMP_TEST_DIR . '/../examples/02_error-handling/producer.php';

		$this->messageOutputAsserts($this->getStompConsumerOutput());
	}

	/**
	 * @group integration
	 * @large
	 */
	public function testExample_failover()
	{
		$this->startStompConsumer(STOMP_TEST_DIR . '/../examples/03_failover/consumer.php');

		require_once STOMP_TEST_DIR . '/../examples/03_failover/producer.php';

		$this->messageOutputAsserts($this->getStompConsumerOutput());
	}
}