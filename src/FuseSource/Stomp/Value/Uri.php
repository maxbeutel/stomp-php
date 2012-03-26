<?php
namespace FuseSource\Stomp\Value;

use InvalidArgumentException;

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
class Uri
{
    private $uriParts;
    private $uriString;
    
    public function __construct($uriString)
    {
        $uriParts = @parse_url($uriString);
        $this->assertValidUri($uriParts);
        
        $this->uriParts = $uriParts;
        $this->uriString = $uriString;
    }
    
    private function assertValidUri($uriParts)
    {
        if (!$uriParts) {
            throw new InvalidArgumentException('Invalid broker URI');
        }
        
        if (!isset($uriParts['scheme']) || $uriParts['scheme'] !== 'tcp') {
            throw new InvalidArgumentException('Only tcp is supported as scheme for now');
        }
        
        if (!isset($uriParts['port']) || !is_numeric($uriParts['port'])) {
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
    
    public function getHost()
    {
        return $this->uriParts['host'];
    }
}