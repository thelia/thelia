<?php

namespace Thelia\Core;

/**
 * Root class of Thelia
 * 
 * It extends Symfony\Component\HttpKernel\Kernel for changing some fonctionnality
 * 
 * 
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */


use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Thelia\Core\TheliaBundle;

class Thelia extends Kernel {   
    /**
     * Initializes the service container.
     * 
     * @TODO cache container initialization
     *
     * The cached version of the service container is used when fresh, otherwise the
     * container is built.
     */
    protected function initializeContainer(){
        $this->container = $this->buildContainer();
        $this->container->set('kernel', $this);

    }
    
    /**
     * Builds the service container.
     *
     * @return ContainerBuilder The compiled service container
     */
    protected function buildContainer(){
        
        $container = $this->getContainerBuilder();
        
        foreach($this->bundles as $bundle){
            $bundle->build($container);
        }
        
        return $container;
    }
    
    
    
    /**
     * return available bundle
     * 
     * Part of Symfony\Component\HttpKernel\KernelInterface
     * 
     * @return array An array of bundle instances.
     * 
     */
    public function registerBundles() {
        
        $bundles = array(
            /* TheliaBundle contain all the dependency injection description */
            new TheliaBundle()
        );
        
        /**
         * OTHER CORE BUNDLE CAN BE DECLARE HERE AND INITIALIZE WITH SPECIFIC CONFIGURATION
         * 
         * HOW TO DECLARE OTHER BUNDLE ? ETC
         */
        
        return $bundles;
        
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
        //Nothing is load here but it's possible to load container configuration here.
        //exemple in sf2 : $loader->load(__DIR__.'/config/config_'.$this->getEnvironment().'.yml');
    }
    
    
}
?>
