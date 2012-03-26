<?php

namespace FuseSource\Stomp\Event;

use Symfony\Component\EventDispatcher\Event;

class ErrorEvent extends Event
{
	private $message;

	public function __construct($message)
	{
		$this->message = $message;
	}

	public function getMessage()
	{
		return $this->message;
	}
}