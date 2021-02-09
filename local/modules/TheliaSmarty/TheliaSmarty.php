<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace TheliaSmarty;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Module\BaseModule;
use TheliaSmarty\Compiler\RegisterParserPluginPass;
use TheliaSmarty\Template\SmartyPluginInterface;

class TheliaSmarty extends BaseModule
{
    /*
     * You may now override BaseModuleInterface methods, such as:
     * install, destroy, preActivation, postActivation, preDeactivation, postDeactivation
     *
     * Have fun !
     */

    public static function getCompilers()
    {
        return [
            new RegisterParserPluginPass()
        ];
    }

    /**
     * Defines how services are loaded in your modules
     *
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator)
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }

    public static function loadConfiguration(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->registerForAutoconfiguration(SmartyPluginInterface::class)
            ->addTag("thelia.parser.register_plugin");
    }
}
