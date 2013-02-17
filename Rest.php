<?php

/**
 * REST client
 * this class is dependent on the "pecl_http" extension
 * 
 * @author Wojciech Brüggemann <wojtek77@o2.pl>
 */
class Rest
{
    const TYPE_RAW = 0;
    const TYPE_JSON = 1;
    const TYPE_XML = 2;
    
    
    
    /**
     * @var HttpRequest
     */
    private $request;
    
    /**
     * @var boolean
     */
    private $isRawOutput;
    
    
    /**
     * Whether APC cache is used (if it is set to FALSE, it certainly will not be used APC at all)
     * @var boolean
     */
    private $isApc = true;
    private $apcPrefix = __FILE__;          // prefix for variables in APC
    private $apcLimitMemory = 0;            // in byte, 0 - no limit
    private $apcLimitLive = 0;              // in second, 0 - no limit
    private $apcIsCompression = false;      // if compression data
    
    
    
    /**
     * @param string $url   the target or base url
     * @param boolean $isRawOutput   if FALSE for JSON output is stdClass and for XML output is SimpleXMLElement
     */
    public function __construct($url, $isRawOutput=false)
    {
        $this->isRawOutput = $isRawOutput;
        
        $this->request = new HttpRequest($url);
        
        $options = array(
            'redirect' => 10, // stop after 10 redirects
        );
        $this->request->setOptions($options);
        
        if ($this->isApc)
            $this->isApc = extension_loaded('apc') && strstr($url, 'localhost/') === false;
    }
    
    
    /**
     * @param string $path
     * @param mixed $dataIn
     * @param boolean $isApc
     * @return string|stdClass|SimpleXMLElement
     */
    public function get($path='', $dataIn=null, $isApc=true)
    {
        $request = clone $this->request;
        
        if ($isApc && $this->isApc)
        {
            $apcKey =
                $this->apcPrefix
                .$this->_finalUrl($request, $path)
                .'?'.http_build_query((array)$dataIn, null, '&')
                //.crc32(serialize($this))
                ;
            
            $body = $this->_apcRead($apcKey);
            
            if ($body !== false)
                return $body;
        }
        else
            $apcKey = null;
        
        return $this->_fetch(HTTP_METH_GET, $path, 'setQueryData', $dataIn, $request, $apcKey);
    }
    
    /**
     * @param string $path
     * @param array $dataIn
     * @return string|stdClass|SimpleXMLElement
     */
    public function post($path='', array $dataIn=null)
    {
        return $this->_fetch(HTTP_METH_POST, $path, 'setPostFields', $dataIn);
    }
    
    /**
     * @param string $path
     * @param string|array $dataIn
     * @return string|stdClass|SimpleXMLElement
     */
    public function put($path='', $dataIn=null)
    {
        if (is_array($dataIn)) $dataIn = http_build_query($dataIn, null, '&');
        
        $request = clone $this->request;
        $request->setContentType('application/x-www-form-urlencoded');
        
        return $this->_fetch(HTTP_METH_PUT, $path, 'setPutData', $dataIn, $request);
    }
    
    /**
     * @param string $path
     * @return string|stdClass|SimpleXMLElement
     */
    public function delete($path='')
    {
        return $this->_fetch(HTTP_METH_DELETE, $path, null, null);
    }
    
    
    
    /**
     * @return string|stdClass|SimpleXMLElement
     */
    private function _fetch($method, $path, $callback, $dataIn, HttpRequest $request=null, $apcKey=null)
    {
        if (!isset($request)) $request = clone $this->request;
        $request->setMethod($method);
        $request->setUrl( $this->_finalUrl($request, $path) );
        if (isset($callback)) $request->$callback($dataIn);
        $request->send();
        
        $body = $request->getResponseBody();
        if ($request->getResponseCode() !== 200)
            return $body;
        
        $type = strtolower($request->getResponseHeader('content-type'));
        if (strpos($type, 'json') !== false)
            $type = self::TYPE_JSON;
        elseif (strpos($type, 'xml') !== false)
            $type = self::TYPE_XML;
        else
            $type = self::TYPE_RAW;
        
        $data = (object) array(
            'type' => $type,
            'body' => $body,
        );
        
        if (isset($apcKey))
            $this->_apcWrite($apcKey, $data);
        
        return $this->_modifyOutput($data);
    }
    
    /**
     * @return string|stdClass|SimpleXMLElement
     */
    private function _modifyOutput(stdClass $data)
    {
        if ($this->isRawOutput)
            return $data->body;
        
        switch ($data->type)
        {
            case self::TYPE_RAW:
                return $data->body;
            
            case self::TYPE_JSON:
                return json_decode($data->body);
            
            case self::TYPE_XML:
                return new SimpleXMLElement($data->body);
        }
    }
    
    /**
     * @return string
     */
    private function _finalUrl(HttpRequest $request, $path)
    {
        $url = $request->getUrl();
        if (substr($url, -1) === '/' && isset($path{0}) && $path{0} === '/')
            $path = ltrim($path, '/');
        
        return $url.$path;
    }
    
    /**
     * @return boolean  if success
     */
    private function _apcWrite($key, stdClass $data)
    {
        $sum = 0;
        if ($this->apcLimitMemory > 0)
        {
            foreach (new APCIterator('user', '/^'.preg_quote($this->apcPrefix).'/') as $v)
            {
                $sum += $v['mem_size'];
            }
        }
        if ($sum > $this->apcLimitMemory) return false;
        
        if ($this->apcIsCompression)
            $data = gzdeflate(serialize($data));
        
        return apc_store($key, $data, $this->apcLimitLive);
    }
    
    /**
     * @return string|stdClass|SimpleXMLElement|false  if failure return FALSE
     */
    private function _apcRead($key)
    {
        $data = apc_fetch($key);
        if ($data === false) return false;
        
        if (is_string($data))
            $data = unserialize(gzinflate($data));
        
        return $this->_modifyOutput($data);
    }
}
