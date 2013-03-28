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
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\Yaml\Yaml;


use Thelia\Core\Bundle;
use Thelia\Log\Tlog;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Config\DefinePropel;
use Thelia\Config\Dumper\TpexConfigDumper;

use Propel;
use PropelConfiguration;

class Thelia extends Kernel
{

    protected $tpexConfig;
    
    public function init()
    {
        parent::init();
        if($this->debug) {
            ini_set('display_errors', 1);
        }
        $this->initPropel();
    }
    
    protected function initPropel()
    {
        if (file_exists(THELIA_ROOT . '/local/config/database.yml') === false) {
            return ;
        }

        if(! Propel::isInit()) {

            $definePropel = new DefinePropel(new DatabaseConfiguration(),
                Yaml::parse(THELIA_ROOT . '/local/config/database.yml'));

            Propel::setConfiguration($definePropel->getConfig());

            if ($this->isDebug()) {
                Propel::setLogger(Tlog::getInstance());
                $config = Propel::getConfiguration(PropelConfiguration::TYPE_OBJECT);
                $config->setParameter('debugpdo.logging.details.method.enabled', true);
                $config->setParameter('debugpdo.logging.details.time.enabled', true);
                $config->setParameter('debugpdo.logging.details.mem.enabled', true);

                $con = Propel::getConnection("thelia");
                $con->useDebug(true);
            }

            Propel::initialize();
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
        /**
         * TODO :
         *  - Retrieve all actives plugins
         *  - load config (create a cache and use this cache
         */
        $container = $this->getContainer();

        /**
         * Set all listener here.
         * Use $dispatcher->addSubscriber or addListener ?
         */
        $dispatcher = $container->get("dispatcher");




        /**
         * manage Tpex configuration here
         */

        $file = $this->getCacheDir() . "/TpexConfig.php";
        $configCache = new ConfigCache($file, $this->debug);

        if (!$configCache->isFresh()) {
            $this->generateTpexConfigCache($configCache);
        }

        require_once $configCache;

        $this->tpexConfig = new \TpexConfig();

        $container->set("loop", $this->tpexConfig->getLoopConfig());

        $container->set("filter", $this->tpexConfig->getFilterConfig());

        $container->set("baseParam", $this->tpexConfig->getBaseParamConfig());

        $container->set("testLoop", $this->tpexConfig->getLoopTestConfig());

    }

    protected function generateTpexConfigCache(ConfigCache $cache)
    {
        $loopConfig = array();
        $filterConfig = array();
        $baseParamConfig = array();
        $loopTestConfig = array();
        $resources = array();

        //load master config, can be overload using modules

        $masterConfigFile = THELIA_ROOT . "/core/lib/Thelia/config.xml";

        if (file_exists($masterConfigFile)) {
            $resources[] = new FileResource($masterConfigFile);

            $dom = XmlUtils::loadFile($masterConfigFile);

            $loopConfig = $this->processConfig($dom->getElementsByTagName("loop"));

            $filterConfig = $this->processConfig($dom->getElementsByTagName("filter"));

            $baseParamConfig = $this->processConfig($dom->getElementsByTagName("baseParam"));

            $loopTestConfig = $this->processConfig($dom->getElementsByTagName("testLoop"));
        }


        $modules = \Thelia\Model\ModuleQuery::getActivated();

        foreach ($modules as $module) {
            $configFile = THELIA_PLUGIN_DIR . "/" . ucfirst($module->getCode()) . "/Config/config.xml";
            if (file_exists($configFile)) {
                $resources[] = new FileResource($configFile);
                $dom = XmlUtils::loadFile($configFile);

                $loopConfig = array_merge($loopConfig, $this->processConfig($dom->getElementsByTagName("loop")));

                $filterConfig = array_merge($filterConfig, $this->processConfig($dom->getElementsByTagName("filter")));

                $baseParamConfig = array_merge(
                    $baseParamConfig,
                    $this->processConfig($dom->getElementsByTagName("baseParam"))
                );

                $loopTestConfig = array_merge(
                    $loopTestConfig,
                    $this->processConfig($dom->getElementsByTagName("testLoop"))
                );

            }
        }

        $tpexConfig = new TpexConfigDumper(
            $loopConfig,
            $filterConfig,
            $baseParamConfig,
            $loopTestConfig
        );

        $cache->write($tpexConfig->dump(), $resources);
    }

    protected function processConfig(\DOMNodeList $elements)
    {
        $result = array();
        for ($i = 0; $i < $elements->length; $i ++) {
            $element = XmlUtils::convertDomElementToArray($elements->item($i));
            $result[$element["name"]] = $element["class"];
        }
        return $result;
    }
    
    /**
     * 
     * initialize session in Request object
     * 
     * All param must be change in Config table
     * 
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    

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
