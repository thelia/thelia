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
    
    /**
     * 
     * @param \Thelia\Core\Template\ParserInterface $parser
     */
    public function __construct(ParserInterface $parser) {
        $this->parser = $parser;
    }
    
    /**
     * 
     * 
     * 
     * @param \Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent $event
     */
    public function onKernelView(GetResponseForControllerResultEvent $event){
        $content = $this->parser->getContent();
        
        if($content instanceof Response){
            $event->setResponse($content);
        }
        else{
            $event->setResponse(new Response($content, $this->parser->getStatus()));
        }
    }
    
    
    /**
     * 
     * Register the method to execute in this class for a specific event (here the kernel.view event)
     * 
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(){
        return array(
            KernelEvents::VIEW => array('onKernelView'),
        );
    }
}
?>
