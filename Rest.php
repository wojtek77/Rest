<?php

/**
 * REST client
 * this class is dependent on the "pecl_http" extension
 * 
 * @author Wojciech Brüggemann <wojtek77@o2.pl>
 */
class Rest
{
    /**
     * @var HttpRequest
     */
    private $request;
    
    
    
    /**
     * @param string $url
     */
    public function __construct($url)
    {
        $this->request = new HttpRequest($url);
        
        $options = array(
            'redirect' => 10, // stop after 10 redirects
        );
        $this->request->setOptions($options);
    }
    
    
    /**
     * @param string $path
     * @param mixed $dataIn
     */
    public function get($path='', $dataIn=null)
    {
        return $this->_fetch(HTTP_METH_GET, $path, 'setQueryData', $dataIn);
    }
    
    /**
     * @param string $path
     * @param array $dataIn
     */
    public function post($path='', array $dataIn=null)
    {
        return $this->_fetch(HTTP_METH_POST, $path, 'setPostFields', $dataIn);
    }
    
    /**
     * @param string $path
     * @param string|array $dataIn
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
     */
    public function delete($path='')
    {
        return $this->_fetch(HTTP_METH_DELETE, $path, null, null);
    }
    
    
    
    private function _fetch($method, $path, $callback, $dataIn, HttpRequest $request=null)
    {
        if (!isset($request)) $request = clone $this->request;
        $request->setMethod($method);
        $request->setUrl( $this->_finalUrl($request, $path) );
        if (isset($callback)) $request->$callback($dataIn);
        $request->send();
        
        return $request->getResponseBody();
    }
    
    private function _finalUrl(HttpRequest $request, $path)
    {
        $url = $request->getUrl();
        if (substr($url, -1) === '/' && isset($path{0}) && $path{0} === '/')
            $path = ltrim($path, '/');
        
        return $url.$path;
    }
}
