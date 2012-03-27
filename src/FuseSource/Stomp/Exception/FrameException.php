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

namespace FuseSource\Stomp\Exception;

use Exception;
use FuseSource\Stomp\Value\Frame;

class FrameException extends Exception implements ExceptionInterface
{
	private $frame;

	public function __construct($message = null, $code = 0, Exception $previous = null, Frame $frame = null)
	{
		parent::__construct($message, $code, $previous);

		$this->frame = $frame;
	}

	public function getFrame()
	{
		return $this->frame;
	}
}