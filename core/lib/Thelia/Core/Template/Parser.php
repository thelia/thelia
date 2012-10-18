<?php

namespace Thelia\Core\Template;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Template\ParserInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * 
 * Master class of Thelia's parser. The loop mechnism depends of this parser
 * 
 * From this class all the parser is lunch
 * 
 * 
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */


class Parser implements ParserInterface {
    
    protected $container;
    
    protected $content;
    protected $status;
    
    public function __construct(ContainerBuilder $container) {
        $this->container = $container;
    }
    
    /**
     * 
     * This method must return a Symfony\Component\HttpFoudation\Response instance or the content of the response
     * 
     */
    public function getContent() {
       $this->loadParser();
       
       return $this->content;
    }
    
    public function setContent($content) {
        $this->content = $content;
    }
    
    public function getStatus() {
        return 200;
    }
    
    public function setStatus($status) {
        $this->status = $status;
    }
    
    public function loadParser(){
        
    }
    
}
?>
