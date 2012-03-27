<?php

namespace FuseSource\Stomp;

use PHPUnit_Framework_TestCase;

class UriManagerTest extends PHPUnit_Framework_TestCase
{
	public function testFailoverUris()
	{
		$manager = new UriManager('failover://(tcp://localhost:61614,tcp://localhost:61613)', 3);

		$uri = $manager->getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame(61614, $uri->getPort());

		$uri = $manager->getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame(61614, $uri->getPort());

		$uri = $manager->getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame(61614, $uri->getPort());


		$uri = $manager->getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame(61613, $uri->getPort());

		$uri = $manager->getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame(61613, $uri->getPort());

		$uri = $manager->getNextUri();
		$this->assertInstanceOf('FuseSource\Stomp\Value\Uri', $uri);
		$this->assertSame(61613, $uri->getPort());

		$this->setExpectedException('BadMethodCallException', 'No more URIs left to try');
		$uri = $manager->getNextUri();
	}

	public function testRandomizedUris()
	{
		$manager = new UriManager('failover://(tcp://localhost:61614,tcp://localhost:61613)?randomize=true', 3);
	}
}