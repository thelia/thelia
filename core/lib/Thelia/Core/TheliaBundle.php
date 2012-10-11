<?php

namespace Thelia\Core;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Scope;


class TheliaBundle extends Bundle {
    public function build(ContainerBuilder $container) {
        
        $container->addScope( new Scope('request'));
        
        $container->register('request', 'Symfony\Component\HttpFoundation\Request');
        
        $container->register('controller.default','Thelia\Controller\DefaultController');
        $container->register('matcher.default','Thelia\Routing\Matcher\DefaultMatcher')
                ->addArgument(new Reference('controller.default'));
        
        $container->register('matcher','Thelia\Routing\Matcher\theliaMatcherCollection')
                ->addMethodCall('add', array(new Reference('matcher.default'), -255))
                //->addMethodCall('add','a matcher class (instance or class name)
                
        ;
        
        $container->register('resolver', 'Symfony\Component\HttpKernel\Controller\ControllerResolver');
        
        $container->register('parser','Thelia\Core\TheliaTemplate');
        /**
         * RouterListener implements EventSubscriberInterface and listen for kernel.request event
         */
        $container->register('listener.router', 'Symfony\Component\HttpKernel\EventListener\RouterListener')
            ->setArguments(array(new Reference('matcher')));
        
        //$container->register('listener.view')
        
        $container->register('http_kernel','Symfony\Component\HttpKernel\HttpKernel')
            ->addArgument(new Reference('dispatcher'))        
            ->addArgument(new Reference('resolver')); 
        
        $container->register('dispatcher','Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher')
                ->addArgument(new Reference('service_container'))
                ->addMethodCall('addSubscriber', array(new Reference('listener.router')));
        
        
        
        
    }
}
?>
