<?php

namespace FuseSource\Stomp\Event;

use FuseSource\Stomp\Stomp;
use FuseSource\Stomp\Value\Frame;
use Symfony\Component\EventDispatcher\Event;

class FrameEvent extends Event
{
	private $connection;
	private $frame;

	public function __construct(Stomp $connection, Frame $frame)
	{
		$this->connection = $connection;
		$this->frame = $frame;
	}

	public function getConnection()
	{
		return $this->connection;
	}

	public function getFrame()
	{
		return $this->frame;
	}
}