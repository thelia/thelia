<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

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
use Thelia\Core\Bundle;
use Thelia\Log\Tlog;
use Propel;
use PropelConfiguration;

class Thelia extends Kernel
{
    
    public function init()
    {
        parent::init(); 
        
        $this->initPropel();
    }
    
    protected function initPropel()
    {
        if (file_exists(THELIA_ROOT . '/local/config/config_db.php') === false) {
            return ;
        }
        Propel::init(THELIA_CONF_DIR . "/config_thelia.php");
        
        if ($this->isDebug()) {
            Propel::setLogger(Tlog::getInstance());
            $config = Propel::getConfiguration(PropelConfiguration::TYPE_OBJECT);
            $config->setParameter('debugpdo.logging.details.method.enabled', true);
            $config->setParameter('debugpdo.logging.details.time.enabled', true);
            $config->setParameter('debugpdo.logging.details.mem.enabled', true);
            
            $con = Propel::getConnection("thelia");
            $con->useDebug(true);
        }
    }

    /**
     * 
     * Load some configuration 
     * Initialize all plugins
     * 
     */
    public function loadConfiguration()
    {
        $request = $this->getContainer()->get('request');
    }
    
    /**
     * 
     * boot parent kernel and after current kernel
     * 
     */
    public function boot()
    {
        parent::boot();
        
        $this->loadConfiguration();
    }

    /**
     * Gets the cache directory.
     *
     * @return string The cache directory
     *
     * @api
     */
    public function getCacheDir()
    {
        if (defined('THELIA_ROOT')) {
            return THELIA_ROOT.'cache/'.$this->environment;
        } else {
            return parent::getCacheDir();
        }

    }

    /**
     * Gets the log directory.
     *
     * @return string The log directory
     *
     * @api
     */
    public function getLogDir()
    {
        if (defined('THELIA_ROOT')) {
            return THELIA_ROOT.'log/';
        } else {
            return parent::getLogDir();
        }
    }

    /**
     * Builds the service container.
     *
     * @return ContainerBuilder The compiled service container
     */
//    protected function buildContainer()
//    {
//        $container = $this->getContainerBuilder();
//        $container->set('kernel', $this);
//
//        foreach ($this->bundles as $bundle) {
//            $bundle->build($container);
//        }
//
//        return $container;
//    }

    /**
     * return available bundle
     *
     * Part of Symfony\Component\HttpKernel\KernelInterface
     *
     * @return array An array of bundle instances.
     *
     */
    public function registerBundles()
    {
        $bundles = array(
            /* TheliaBundle contain all the dependency injection description */
            new Bundle\TheliaBundle()
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
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        //Nothing is load here but it's possible to load container configuration here.
        //exemple in sf2 : $loader->load(__DIR__.'/config/config_'.$this->getEnvironment().'.yml');
    }

}
