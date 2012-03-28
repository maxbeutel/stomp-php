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

namespace Stomp\Helper\PHPUnit;

trait StompServerTrait
{
	private static $NO_OUTPUT = '[no output]';

	private $stompServerProcess;
	private $currentServerOutputFile;

	private $stompConsumerProcess;
	private $currentConsumerOutputFile;

	private function startStompServer()
	{
		$this->stopStompServer();

		$this->currentServerOutputFile = STOMP_TEST_DIR . '/integration/fixtures/output-files/' . microtime(true) . '-' . md5(uniqid(mt_rand(), true)) . '.server';

		$cmd = 'node ' . STOMP_TEST_DIR . '/../vendor/maxbeutel/node-stomp-server/app.js';

		$descriptorspec = [
			0 => ['pipe', 'r'],
			1 => ['file', $this->currentServerOutputFile, 'w'],
			2 => ['pipe', 'w'],
		];

		$this->stompServerProcess = proc_open($cmd, $descriptorspec, $pipes, sys_get_temp_dir());

		// hack: hopefully node process started within that timespan...
		sleep(2);
	}

	private function stopStompServer()
	{
		if (is_resource($this->stompServerProcess)) {
			proc_terminate($this->stompServerProcess);
		}

		foreach (glob(STOMP_TEST_DIR . '/integration/fixtures/output-files/*.server') as $outputFile) {
			unlink($outputFile);
		}

		exec('ps -ef | grep "/node-stomp-server/" | awk \'{print $2}\' | xargs -r kill 2>&1');

		$this->stompServerProcess = $this->currentServerOutputFile = null;
	}

	private function getStompServerOutput()
	{
		if (!is_file($this->currentServerOutputFile)) {
			return self::$NO_OUTPUT;
		}

		// hack: not all data might have been written to disk yet
		sleep(1);

		return file_get_contents($this->currentServerOutputFile);
	}

	private function startStompConsumer($consumer)
	{
		$this->stopStompConsumer();

		$this->currentConsumerOutputFile = STOMP_TEST_DIR . '/integration/fixtures/output-files/' . microtime(true) . '-' . md5(uniqid(mt_rand(), true)) . '.consumer';

		$cmd = 'php ' . $consumer;

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
			proc_terminate($this->stompConsumerProcess);
		}

		foreach (glob(STOMP_TEST_DIR . '/integration/fixtures/output-files/*.consumer') as $outputFile) {
			unlink($outputFile);
		}

		exec('ps -ef | grep "/consumer*.php" | awk \'{print $2}\' | xargs -r kill 2>&1');

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
}