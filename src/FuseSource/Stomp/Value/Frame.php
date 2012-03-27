<?php

/**
 *
 * Copyright 2005-2006 The Apache Software Foundation
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

namespace FuseSource\Stomp\Value;

use FuseSource\Stomp\Event\SystemEventType;
use FuseSource\Stomp\Helper\InputHelper;

class Frame
{
	private $command;
	private $headers = [];
	private $body;
	private $waitForReceipt;

	private function __construct($command, array $headers, $body, $waitForReceipt)
	{
		$this->command = $command;
		$this->headers = $headers;
		$this->body = $body;
		$this->waitForReceipt = $waitForReceipt;
	}

	public static function unserializeFrom($data)
	{
		$data = (string) $data;
		list($header, $body) = explode("\n\n", $data, 2);

		$header = explode("\n", $header);
		$headers = [];

		$command = null;

		foreach ($header as $v) {
			if (isset($command)) {
				list ($name, $value) = explode(':', $v, 2);
				$headers[$name] = $value;
			} else {
				$command = $v;
			}
		}

		return new static($command, $headers, $body, false);
	}

	public static function createNew($command = null, $headers = null, $body = null, $waitForReceipt = false)
	{
		if ($waitForReceipt) {
			$headers['receipt'] = md5(microtime() . uniqid(mt_rand(), true));
		}

		return new static($command, (array) $headers, $body, $waitForReceipt);
	}

	public function getEventName()
	{
		if ($this->command === 'CONNECTED') {
			return SystemEventType::FRAME_CONNECTED;
		}

		if ($this->command === 'ERROR') {
			return SystemEventType::FRAME_ERROR;
		}

		if ($this->command === 'RECEIPT') {
			return SystemEventType::FRAME_RECEIPT;
		}

		return $this->headers['destination'];
	}

	public function getCommand()
	{
		return $this->command;
	}

	public function getHeaders()
	{
		return $this->headers;
	}

	// @TODO take if (isset($frame->headers['transformation']) && $frame->headers['transformation'] == 'jms-map-json') into account?
	public function getBody()
	{
		return $this->body;
	}

	public function waitForReceipt()
	{
		return $this->waitForReceipt;
	}

	public function __toString()
	{
		$data = $this->command . "\n";

		$headers = InputHelper::convertPhpOptions($this->headers);

		foreach ($headers as $name => $value) {
			$data .= $name . ': ' . $value . "\n";
		}

		$data .= "\n";
		$data .= $this->body;
		return $data .= "\x00";
	}
}