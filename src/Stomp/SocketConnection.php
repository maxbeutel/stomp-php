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

namespace Stomp;

use InvalidArgumentException;
use BadMethodCallException;
use Stomp\Exception\ConnectionException;
use Stomp\Value\Uri;
use Stomp\Helper\InputHelper;
use Monolog\Logger;

class SocketConnection
{
	protected $uris = [];
	protected $retryAttemptsPerUri;
	protected $connectionTimeout;
	protected $logger;

	protected $options = [];
	protected $socket;

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

	protected function connect()
	{
		try {
			while ($uri = $this->getNextUri()) {
				$connectionErrorNumber = $connectionError = null;

				$this->socket = @fsockopen($uri->getHostWithScheme(), $uri->getPort(), $connectionErrorNumber, $connectionError, $this->connectionTimeout);

				if (is_resource($this->socket)) {
					$this->logger->info(sprintf('Successfully connected to broker "%s" at attempt %d', $uri, $this->currentRetryAttempt));
					return;
				}

				$this->logger->info(sprintf('Failed to connect to broker "%s" at attempt %d', $uri, $this->currentRetryAttempt));

				if ($connectionErrorNumber || $connectionError) {
					$this->logger->warn(sprintf('Got error no %d with message "%s"', $connectionErrorNumber, $connectionError));
				}

				$this->close();
			}
		} catch (BadMethodCallException $e) {
			throw new ConnectionException('Could not connect to any broker', $e->getCode(), $e);
		} catch (InvalidArgumentException $e) {
			throw new ConnectionException('Attempted to use invalid URI', $e->getCode(), $e);
		}
	}

	public function open()
	{
		$this->reset();

		$this->connect();
	}

	public function write($string)
	{
		$success = (bool) @fwrite($this->socket, $string, strlen($string));

		if (!$success) {
			$this->logger->warn('Could not write string to socket, trying to reconnect', ['string' => $string]);

			$this->open();
			$this->write($string);
		}
	}

	public function writeUnchecked($string)
	{
		@fwrite($this->socket, $string, strlen($string));
	}

	public function read(callable $endPredicate)
	{
		if (!(bool) stream_socket_recvfrom($this->socket, 2, STREAM_PEEK)) {
			throw new ConnectionException('Unexpected EOF');
		}

		$data = '';

		while (false !== ($char = fgetc($this->socket))) {
		    if ($char === "\x00") {
		    	break;
		    }

		    $data .= $char;
		}

		return trim($data, "\n");
	}

	public function getRawSocket()
	{
		return $this->socket;
	}

	public function close()
	{
		if ($this->socket) {
			@fclose($this->socket);
		}
	}
}