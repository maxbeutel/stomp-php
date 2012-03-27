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

namespace FuseSource\Stomp\Event;

use FuseSource\Stomp\StompClient;
use FuseSource\Stomp\Value\Frame;
use Symfony\Component\EventDispatcher\Event;

class FrameEvent extends Event
{
	private $connection;
	private $frame;

	public function __construct(StompClient $connection, Frame $frame)
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