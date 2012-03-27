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

use InvalidArgumentException;

class Uri
{
	private $uriParts;
	private $uriString;

	public function __construct($uriString)
	{
		if (!is_string($uriString)) {
			throw new InvalidArgumentException(sprintf('URI must be string, %s given', gettype($uriString)));
		}

		$uriParts = @parse_url($uriString);
		$this->assertValidUri($uriParts);

		$this->uriParts = $uriParts;
		$this->uriString = $uriString;
	}

	private function assertValidUri($uriParts)
	{
		if (!$uriParts) {
			throw new InvalidArgumentException('Invalid broker URI format');
		}

		if (!isset($uriParts['scheme']) || !in_array($uriParts['scheme'], ['tcp', 'ssl'], true)) {
			throw new InvalidArgumentException('Scheme must be either tcp or ssl for now');
		}

		if (!isset($uriParts['port'])) {
			throw new InvalidArgumentException('No valid port found');
		}

		if (!isset($uriParts['host'])) {
			throw new InvalidArgumentException('No host found');
		}
	}

	public function getPort()
	{
		return $this->uriParts['port'];
	}

	public function getHostWithScheme()
	{
		return sprintf('%s://%s', $this->uriParts['scheme'], $this->uriParts['host']);
	}

	public function __toString()
	{
		return $this->uriString;
	}
}