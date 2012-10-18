<?php

namespace Thelia\Core\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Template\ParserInterface;


/**
 * 
 * 
 * 
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

class ViewSubscriber implements EventSubscriberInterface{
    
    private $parser;
    
    public function __construct(ParserInterface $parser) {
        $this->parser = $parser;
    }
    
    public function onKernelView(GetResponseForControllerResultEvent $event){
        $content = $this->parser->getContent();
        
        if($content instanceof Response){
            $event->setResponse($content);
        }
        else{
            $event->setResponse(new Response($this->parser->getContent(), $this->parser->getStatus()));
        }


        //$event->setResponse(($content = $this->parser->getContent() instanceof Response) ?: new Response($this->parser->getContent(), $this->parser->getStatus()) );
    }
    
    
    /**
     * 
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(){
        return array(
            KernelEvents::VIEW => array(array('onKernelView', 32)),
        );
    }
}
?>
