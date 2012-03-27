<?php

namespace FuseSource\Stomp;

use InvalidArgumentException;
use BadMethodCallException;
use FuseSource\Stomp\Exception\ConnectionException;
use FuseSource\Stomp\Value\Uri;
use FuseSource\Stomp\Helper\InputHelper;
use Monolog\Logger;

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
	protected $uris = [];
	protected $retryAttemptsPerUri;
	protected $connectionTimeout;
	protected $logger;

	protected $options = [];

	protected $currentUriIndex = 0;
	protected $currentRetryAttempt = 0;

	public function __construct($uriString, $retryAttemptsPerUri, $connectionTimeout, Logger $logger)
	{
		$defaultOptions = [
			'randomize' => false,
		];

		// create options from key/value string
		parse_str(substr($uriString, strpos($uriString, '?') + 1), $options);
        $options = InputHelper::convertStringOptions($options);

        $singleUriStrings = [];

        // parse URIs
		if (stripos($uriString, 'failover://') === 0) {
			if (preg_match('#\((.*)\)#i', $uriString, $matches)) {
				$singleUriStrings = explode(',', $matches[1]);
			}
		} else {
			list($singleUriStrings) = explode('?', $uriString);
			$singleUriStrings = (array) $singleUriStrings;
		}

		if (count($singleUriStrings) === 0) {
			throw new InvalidArgumentException(sprintf('No URIs found in "%s"', $uriString));
		}

		// create URI value objects
        foreach ($singleUriStrings as $singleUriString) {
        	try {
        		$this->uris[] = new Uri($singleUriString);
        	} catch (InvalidArgumentException $e) {
        		throw new InvalidArgumentException(sprintf('Could not create URI from URI string "%s"', $singleUriString));
        	}
        }

        $this->retryAttemptsPerUri = $retryAttemptsPerUri;
        $this->connectionTimeout = $connectionTimeout;
        $this->options = array_merge($defaultOptions, $options);
        $this->logger = $logger;

        if ($this->options['randomize']) {
        	shuffle($this->uris);
        }
	}

	protected function getNextUri()
	{
		if ($this->currentRetryAttempt === $this->retryAttemptsPerUri) {
			$this->currentUriIndex++;
			$this->currentRetryAttempt = 0;
		}

		if (!isset($this->uris[$this->currentUriIndex])) {
			throw new BadMethodCallException('No more URIs left to try');
		}

		$uri = $this->uris[$this->currentUriIndex];

		$this->currentRetryAttempt++;

		return $uri;
	}

	protected function reset()
	{
		$this->currentUriIndex = $this->currentRetryAttempt = 0;
	}

	public function openSocketToBroker()
	{
		$this->reset();

		try {
			while ($uri = $this->getNextUri()) {
				$connectionErrorNumber = $connectionError = null;

				$socket = @fsockopen($uri->getHostWithScheme(), $uri->getPort(), $connectionErrorNumber, $connectionError, $this->connectionTimeout);

				if (is_resource($socket)) {
					$this->logger->info(sprintf('Successfully connected to broker "%s" at attempt %d', $uri, $this->uriManager->getCurrentRetryAttempt()));
					return $socket;
				}

				$this->logger->info(sprintf('Failed to connect to broker "%s" at attempt %d', $uri, $this->uriManager->getCurrentRetryAttempt()));

				if ($connectionErrorNumber || $connectionError) {
					$this->logger->warn(sprintf('Got error no %d with message "%s"', $connectionErrorNumber, $connectionError));
				}

				if ($socket) {
					@fclose($socket);
				}
			}
		} catch (BadMethodCallException $e) {
			throw new ConnectionException(sprintf('Could not connect to any broker', $connectionAttempts), $e->getCode(), $e);
		} catch (InvalidArgumentException $e) {
			throw new ConnectionException('Attempted to use invalid URI', $e->getCode(), $e);
		}
	}
}