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

namespace Stomp\Value;

use PHPUnit_Framework_TestCase;

class UriTest extends PHPUnit_Framework_TestCase
{
	public function testStringArgument()
	{
		$this->setExpectedException('InvalidArgumentException', 'URI must be string, integer given');
		$uri = new Uri(1111);
	}

	public function testUnsupportedScheme()
	{
		$uri = new Uri('tcp://localhost:1234');
		$uri = new Uri('ssl://localhost:1234');

		$this->setExpectedException('InvalidArgumentException', 'Scheme must be either tcp or ssl for now');
		$uri = new Uri('udp://localhost:1234');
	}

	public function testMissingScheme()
	{
		$this->setExpectedException('InvalidArgumentException', 'Scheme must be either tcp or ssl for now');
		$uri = new Uri('localhost:1234');
	}

	public function testMissingPort()
	{
		$this->setExpectedException('InvalidArgumentException', 'No valid port found');
		$uri = new Uri('tcp://localhost');
	}

	public function testInvalidUri()
	{
		$this->setExpectedException('InvalidArgumentException', 'Invalid broker URI');
		$uri = new Uri('tcp://localhost:fooo');
	}

	public function testGetHostWithScheme()
	{
		$uri = new Uri('tcp://localhost:1234');
		$this->assertSame('tcp://localhost', $uri->getHostWithScheme());
	}

	public function testToString()
	{
		$uri = new Uri('tcp://localhost:1234');
		$this->assertSame('tcp://localhost:1234', (string) $uri);
	}
}