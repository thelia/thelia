<?php

namespace Thelia\Core;

/**
 * Root class of Thelia
 * 
 * 
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class Thelia extends Kernel {    
    
    protected function initializeContainer(){
        if(false === $container = require THELIA_ROOT . '/local/config/container.php'){
            /**
             * @todo redirect to installation process
             * 
             */
            
        }
                
        $this->container = $container;
        $this->container->set('kernel', $this);
    }
    
    
    /**
     * 
     * @return \Symfony\Component\DependencyInjection\ContainerBuilder
     */
    public function getContainer(){
        return $this->container;
    }
    
    /**
     * return available bundle
     * 
     * Part of Symfony\Component\HttpKernel\KernelInterface
     * 
     */
    public function registerBundles() {
        
    }
    
    /**
     * Loads the container configuration
     * 
     * part of Symfony\Component\HttpKernel\KernelInterface
     *
     * @param LoaderInterface $loader A LoaderInterface instance
     *
     * @api
     */
    public function registerContainerConfiguration(LoaderInterface $loader){
        
    }
    
    
}
?>
