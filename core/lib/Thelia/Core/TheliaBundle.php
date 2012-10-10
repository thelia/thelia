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
        $container->register('dispatcher','Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher')
                ->addArgument(new Reference('service_container'));
        $container->register('resolver', 'Thelia\Controller\TheliaController');
        
        $container->register('http_kernel','Symfony\Component\HttpKernel\HttpKernel')
            ->addArgument(new Reference('dispatcher'))        
            ->addArgument(new Reference('resolver')); 
        
        
    }
}
?>
