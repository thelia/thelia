<?php

namespace Thelia\Core;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Scope;

/**
 * First Bundle use in Thelia
 * It initialize dependency injection container.
 * 
 * @TODO load configuration from thelia plugin
 * @TODO register database configuration.
 * 
 * 
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

class TheliaBundle extends Bundle {
    
    /**
     * 
     * Construct the depency injection builder
     * 
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */
    
    public function build(ContainerBuilder $container) {
        
        $container->addScope( new Scope('request'));
        
        $container->register('request', 'Symfony\Component\HttpFoundation\Request')
                ->setSynthetic(true);
        
        $container->register('controller.default','Thelia\Controller\DefaultController');
        $container->register('matcher.default','Thelia\Routing\Matcher\DefaultMatcher')
                ->addArgument(new Reference('controller.default'));
        
        $container->register('matcher','Thelia\Routing\Matcher\theliaMatcherCollection')
                ->addMethodCall('add', array(new Reference('matcher.default'), -255))
                //->addMethodCall('add','a matcher class (instance or class name)
                
        ;
        
        $container->register('resolver', 'Symfony\Component\HttpKernel\Controller\ControllerResolver');
        
        $container->register('parser','Thelia\Core\Template\Parser')
                ->addArgument(new Reference('service_container'));
        /**
         * RouterListener implements EventSubscriberInterface and listen for kernel.request event
         */
        $container->register('listener.router', 'Symfony\Component\HttpKernel\EventListener\RouterListener')
            ->setArguments(array(new Reference('matcher')));
        
        /**
         * @TODO think how to use kernel.view event for templating. In most of case (in Thelia) controller doesn't return a Response instance
         */
        $container->register('listener.view','Thelia\Core\EventSubscriber\ViewSubscriber')
                ->addArgument(new Reference('parser'));
        
        $container->register('http_kernel','Symfony\Component\HttpKernel\HttpKernel')
            ->addArgument(new Reference('dispatcher'))        
            ->addArgument(new Reference('resolver')); 
        
        $container->register('dispatcher','Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher')
                ->addArgument(new Reference('service_container'))
                ->addMethodCall('addSubscriber', array(new Reference('listener.router')))
                ->addMethodCall('addSubscriber', array(new Reference('listener.view')));
        
        
        /**
         * @TODO learn about container compilation
         */
        
    }
}
?>
