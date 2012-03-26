<?php
namespace FuseSource\Stomp\Value;

use InvalidArgumentException;

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