<?php

namespace Thelia\Routing\Matcher;

use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;


class TheliaMatcherCollection implements RequestMatcherInterface, UrlMatcherInterface {
    
    protected $context;
    protected $matchers = array();
    
    /**
     * Constructor
     * 
     * Check if this constructor id needed (is RequestContext needed ? )
     */
    public function __construct() {
        
        
        
        $this->context = new RequestContext();
    }
    
    public function add($matcher){
        if(!$matcher instanceof RequestMatcherInterface && !$matcher instanceof UrlMatcherInterface){
            throw new \InvalidArgumentException('Matcher must either implement UrlMatcherInterface or RequestMatcherInterface.');
        }
        
        $this->matchers[] = $matcher;
    }
    
    
    /**
     * Tries to match a request with a set of routes.
     *
     * If the matcher can not find information, it must throw one of the exceptions documented
     * below.
     *
     * @param Request $request The request to match
     *
     * @return array An array of parameters
     *
     * @throws ResourceNotFoundException If no matching resource could be found
     * @throws MethodNotAllowedException If a matching resource was found but the request method is not allowed
     */
    public function matchRequest(Request $request){
    
        
    }
    
    /**
     * Tries to match a URL path with a set of routes.
     *
     * If the matcher can not find information, it must throw one of the exceptions documented
     * below.
     *
     * @param string $pathinfo The path info to be parsed (raw format, i.e. not urldecoded)
     *
     * @return array An array of parameters
     *
     * @throws ResourceNotFoundException If the resource could not be found
     * @throws MethodNotAllowedException If the resource was found but the request method is not allowed
     *
     * @api
     */
    public function match($pathinfo){
        
    }
    
    /**
     * Sets the request context.
     *
     * @param RequestContext $context The context
     *
     */
    public function setContext(RequestContext $context){
        $this->context = $context;
        
    }

    /**
     * Gets the request context.
     *
     * @return RequestContext The context
     *
     */
    public function getContext(){
        return $this->context;
    }
    
    
    
    
}
?>
