<?php

/**
 * REST client
 * this class is dependent on the "pecl_http" extension
 * 
 * @author Wojciech Brüggemann <wojtek77@o2.pl>
 */
class Rest
{
    const OUTPUT_RAW = 0;       // original unmodified output (as string)
    const OUTPUT_RECOGNIZE = 1; // automatic recognition of output
    const OUTPUT_JSON = 2;      // if JSON output as stdClass
    const OUTPUT_XML = 3;       // if XML output as SimpleXMLElement
    
    
    
    /**
     * @var HttpRequest
     */
    private $request;
    
    /**
     * @var int self-constant
     */
    private $output;
    
    
    
    /**
     * @param string $url   the target or base url
     * @param int $output   a self-constant OUTPUT_...
     */
    public function __construct($url, $output=self::OUTPUT_RECOGNIZE)
    {
        $this->output = $output;
        
        $this->request = new HttpRequest($url);
        
        $options = array(
            'redirect' => 10, // stop after 10 redirects
        );
        $this->request->setOptions($options);
    }
    
    
    /**
     * @param string $path
     * @param mixed $dataIn
     * @return mixed
     */
    public function get($path='', $dataIn=null)
    {
        return $this->_fetch(HTTP_METH_GET, $path, 'setQueryData', $dataIn);
    }
    
    /**
     * @param string $path
     * @param array $dataIn
     * @return mixed
     */
    public function post($path='', array $dataIn=null)
    {
        return $this->_fetch(HTTP_METH_POST, $path, 'setPostFields', $dataIn);
    }
    
    /**
     * @param string $path
     * @param string|array $dataIn
     * @return mixed
     */
    public function put($path='', $dataIn=null)
    {
        if (is_array($dataIn)) $dataIn = http_build_query ($dataIn, '', '&');
        
        $request = clone $this->request;
        $request->setContentType('application/x-www-form-urlencoded');
        
        return $this->_fetch(HTTP_METH_PUT, $path, 'setPutData', $dataIn, $request);
    }
    
    /**
     * @param string $path
     * @return mixed
     */
    public function delete($path='')
    {
        return $this->_fetch(HTTP_METH_DELETE, $path, null, null);
    }
    
    
    
    /**
     * @return mixed
     */
    private function _fetch($method, $path, $callback, $dataIn, HttpRequest $request=null)
    {
        if (!isset($request)) $request = clone $this->request;
        $request->setMethod($method);
        $request->setUrl( $this->_finalUrl($request, $path) );
        if (isset($callback)) $request->$callback($dataIn);
        $request->send();
        
        if ($this->output === self::OUTPUT_RAW)
        {
            return $request->getResponseBody();
        }
        else
        {
            $body = $request->getResponseBody();
            $bodyTrim = trim($body);
            
            /* JSON */
            if ($this->output === self::OUTPUT_RECOGNIZE || $this->output === self::OUTPUT_JSON)
            {
                if (isset($bodyTrim{0}) && $bodyTrim{0} === '{')
                {
                    $return = json_decode($bodyTrim);
                    if (json_last_error() === JSON_ERROR_NONE)
                        return $return;
                }
            }
            
            /* XML */
            if ($this->output === self::OUTPUT_RECOGNIZE || $this->output === self::OUTPUT_XML)
            {
                if (isset($bodyTrim{0}) && $bodyTrim{0} === '<')
                {
                    try
                    {
                        libxml_use_internal_errors(true);
                        return new SimpleXMLElement($bodyTrim);
                    }
                    catch (Exception $e)
                    {
                        libxml_clear_errors();
                    }
                }
            }
            
            return $body;
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
}
