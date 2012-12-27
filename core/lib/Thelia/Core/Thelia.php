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
use Thelia\Core\Bundle\TheliaBundle;
use Thelia\Core\Bundle\NotORMBundle;
use Thelia\Core\Bundle\ModelBundle;

class Thelia extends Kernel
{
    /**
     * Initializes the service container.
     *
     * @TODO cache container initialization
     *
     * The cached version of the service container is used when fresh, otherwise the
     * container is built.
     */
    protected function initializeContainer()
    {
        $this->container = $this->buildContainer();
        //$this->container->set('kernel', $this);

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
        if(defined('THELIA_ROOT'))
        {
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
        if(defined('THELIA_ROOT'))
        {
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
    protected function buildContainer()
    {
        $container = $this->getContainerBuilder();
        $container->set('kernel', $this);

        foreach ($this->bundles as $bundle) {
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
    public function registerBundles()
    {
        $bundles = array(
            /* TheliaBundle contain all the dependency injection description */
            new TheliaBundle(),
            new NotORMBundle(),
            new ModelBundle()
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
