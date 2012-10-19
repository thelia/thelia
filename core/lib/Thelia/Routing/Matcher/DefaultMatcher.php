<?php

namespace Thelia\Routing\Matcher;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Thelia\Controller\NullControllerInterface;

/**
 * Default matcher when no action is needed and there is no result for urlmatcher
 * 
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class DefaultMatcher implements RequestMatcherInterface{
    
    protected $controller;
    
    public function __construct(NullControllerInterface $controller) {
        $this->controller = $controller;
    }
    
    public function matchRequest(Request $request) {
        
        
        $objectInformation = new \ReflectionObject($this->controller);
        
        $parameter = array(
          '_controller' => $objectInformation->getName().'::noAction'  
        );
        
        return $parameter;
    }
}


?>