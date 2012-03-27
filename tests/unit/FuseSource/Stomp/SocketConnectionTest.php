<?php

namespace FuseSource\Stomp;

use PHPUnit_Framework_TestCase;

/**
 *
 * Copyright 2005-2006 The Apache Software Foundation
 * Source Code modified 2012 by Max Beutel <me@maxbeutel.de>
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

// dummy class extending regular SocketConnection in order to test some internal stuff
// without the hassle of setting up a socket connection
class DummySocketConnection extends SocketConnection
{
	public function _getOptions()
	{
		return $this->options;
	}

	public function _getNextUri()
	{
		return $this->getNextUri();
	}
}

class SocketConnectionTest extends PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		$this->loggerMock = $this->getMockBuilder('Monolog\Logger')
								 ->disableOriginalConstructor()
								 ->getMock();
	}

	public function testValidUriStrings()
	{
		$manager = new DummySocketConnection('failover://(tcp://localhost:61614,tcp://localhost:61613)', 3, 10, $this->loggerMock);
		$manager = new DummySocketConnection('failover://(tcp://localhost:61614,tcp://localhost:61613)?foo=bar', 3, 10, $this->loggerMock);
		$manager = new DummySocketConnection('tcp://localhost:61614', 3, 10, $this->loggerMock);
		$manager = new DummySocketConnection('ssl://localhost:61614', 3, 10, $this->loggerMock);
	}

	public function testInvalidUriString_1()
	{
		$this->setExpectedException('InvalidArgumentException', 'Could not create URI from URI string "failover:/(tcp://localhost:61614,tcp://localhost:61613)"');

		$manager = new DummySocketConnection('failover:/(tcp://localhost:61614,tcp://localhost:61613)', 3, 10, $this->loggerMock);
	}

	public function testInvalidUriString_2()
	{
		$this->setExpectedException('InvalidArgumentException', 'Could not create URI from URI string "tcp://localhost:61614 tcp://localhost:61613"');

		$manager = new DummySocketConnection('failover://(tcp://localhost:61614 tcp://localhost:61613)', 3, 10, $this->loggerMock);
	}

	public function testDefaultOptions()
	{
		$manager = new DummySocketConnection('failover://(tcp://localhost:61614,tcp://localhost:61613)', 3, 10, $this->loggerMock);

		$this->assertFalse($manager->_getOptions()['randomize']);
	}

	public function testFailoverUris()
	{
		$manager = new DummySocketConnection('failover://(tcp://localhost:61614,tcp://localhost:61613)', 3, 10, $this->loggerMock);

		$uri = $manager->_getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame('tcp://localhost:61614', (string) $uri);

		$uri = $manager->_getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame('tcp://localhost:61614', (string) $uri);

		$uri = $manager->_getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame('tcp://localhost:61614', (string) $uri);


		$uri = $manager->_getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame('tcp://localhost:61613', (string) $uri);

		$uri = $manager->_getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame('tcp://localhost:61613', (string) $uri);

		$uri = $manager->_getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame('tcp://localhost:61613', (string) $uri);

		$this->setExpectedException('BadMethodCallException', 'No more URIs left to try');
		$uri = $manager->_getNextUri();
	}

	public function testRandomizedUris()
	{
		$manager = new DummySocketConnection('failover://(tcp://localhost:61614,tcp://localhost:61613)?randomize=true', 3, 10, $this->loggerMock);
		$this->assertTrue($manager->_getOptions()['randomize']);
	}
}