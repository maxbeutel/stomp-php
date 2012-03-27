<?php

namespace FuseSource\Stomp;

use InvalidArgumentException;
use BadMethodCallException;
use FuseSource\Stomp\Value\Uri;
use FuseSource\Stomp\Helper\InputHelper;

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

class UriManager
{
	private $uris = [];
	private $retryAttemptsPerUri;

	private $options = [];

	private $currentUriIndex = 0;
	private $currentRetries = 0;

	public function __construct($uriString, $retryAttemptsPerUri)
	{
		$defaultOptions = [
			'randomize' => false,
		];

		$pattern = '|^(([a-zA-Z0-9]+)://)+\(*([a-zA-Z0-9\.:/i,-]+)\)*\??([a-zA-Z0-9=&]*)$|i';

		if (!preg_match($pattern, $uriString, $regs)) {
			throw new InvalidArgumentException('Cant parse URIs from URI string');
		}

        $options = $regs[4];

        if ($options) {
        	parse_str($options, $options);
        	$options = InputHelper::convertStringOptions($options);
        }

        $singleUriStrings = explode(',', $regs[3]);

        foreach ($singleUriStrings as $singleUriString) {
        	$this->uris[] = new Uri($singleUriString);
        }

        if ($options['randomize']) {
        	shuffle($this->uris);
        }

        $this->retryAttemptsPerUri = $retryAttemptsPerUri;
        $this->options = array_merge($this->defaultOptions, $options);
	}

	public function getNextUri()
	{
		if ($this->currentRetries === $this->retryAttemptsPerUri) {
			$this->currentUriIndex++;
			$this->currentRetries = 0;
		}

		if (!isset($this->uris[$this->currentUriIndex])) {
			throw new BadMethodCallException('No more URIs left to try');
		}

		$uri = $this->uris[$this->currentUriIndex];

		$this->currentRetries++;

		return $uri;
	}

	public function reset()
	{
		$this->currentUriIndex = $this->currentRetries = 0;
	}
}