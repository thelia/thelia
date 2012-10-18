<?php

namespace Thelia\Core\Template;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Template\ParserInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;


class Parser implements ParserInterface {
    
    protected $container;
    
    public function __construct(ContainerBuilder $container) {
        $this->container = $container;
    }
    
    public function getContent() {
       return new Response('toto');
    }
    
    public function setContent($content) {
        
    }
    
    public function getStatus() {
        return 200;
    }
    
    public function setStatus($status) {
        
    }
    
}
?>
