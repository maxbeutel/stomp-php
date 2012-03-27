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

class DummyUriManager extends UriManager
{
	public function getOptions()
	{
		return $this->options;
	}
}

class UriManagerTest extends PHPUnit_Framework_TestCase
{
	public function testDefaultOptions()
	{
		$manager = new DummyUriManager('failover://(tcp://localhost:61614,tcp://localhost:61613)', 3);

		$this->assertFalse($manager->getOptions()['randomize']);
	}

	public function testFailoverUris()
	{
		$manager = new DummyUriManager('failover://(tcp://localhost:61614,tcp://localhost:61613)', 3);

		$uri = $manager->getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame('tcp://localhost:61614', (string) $uri);

		$uri = $manager->getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame('tcp://localhost:61614', (string) $uri);

		$uri = $manager->getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame('tcp://localhost:61614', (string) $uri);


		$uri = $manager->getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame('tcp://localhost:61613', (string) $uri);

		$uri = $manager->getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame('tcp://localhost:61613', (string) $uri);

		$uri = $manager->getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame('tcp://localhost:61613', (string) $uri);

		$this->setExpectedException('BadMethodCallException', 'No more URIs left to try');
		$uri = $manager->getNextUri();
	}

	public function testRandomizedUris()
	{
		$manager = new DummyUriManager('failover://(tcp://localhost:61614,tcp://localhost:61613)?randomize=true', 3);
		$this->assertTrue($manager->getOptions()['randomize']);
	}
}