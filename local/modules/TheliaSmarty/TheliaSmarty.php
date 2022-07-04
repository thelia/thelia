<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
            new RegisterParserPluginPass(),
        ];
    }

    /**
     * Defines how services are loaded in your modules.
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR.ucfirst(self::getModuleCode()).'/I18n/*', THELIA_MODULE_DIR.ucfirst(self::getModuleCode()).'/Template/Assets/EncoreModuleAssetsPathPackage.php'])
            ->autowire(true)
            ->autoconfigure(true);
    }

    public static function loadConfiguration(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->registerForAutoconfiguration(SmartyPluginInterface::class)
            ->addTag('thelia.parser.register_plugin');
    }
}
