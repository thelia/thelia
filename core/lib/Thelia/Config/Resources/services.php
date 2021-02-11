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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ServiceValueResolver;
use Thelia\Log\Tlog;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

return function(ContainerConfigurator $configurator) {
    $serviceConfigurator = $configurator->services();

    $serviceConfigurator->defaults()
        ->autowire(false)
        ->autoconfigure(false)
        ->bind('$kernelCacheDir', '%kernel.cache_dir%')
        ->bind('$kernelDebug', '%kernel.debug%')
        ->bind('$kernelEnvironment', '%kernel.environment%');

    $serviceConfigurator->load('Thelia\\',THELIA_LIB )
        ->exclude(
            [
                THELIA_LIB."/Command/Skeleton/Module/I18n/*.php",
                THELIA_LIB."/Config/**/*.php"
            ]
        )->autowire()
        ->autoconfigure();

    if (\defined("THELIA_INSTALL_MODE") === false) {
        $modules = ModuleQuery::getActivated();
        /** @var Module $module */
        foreach ($modules as $module) {
            try {
                \call_user_func([$module->getFullNamespace(), 'configureContainer'], $configurator);
                \call_user_func([$module->getFullNamespace(), 'configureServices'], $serviceConfigurator);
            } catch (\Exception $e) {
                Tlog::getInstance()->addError(
                    sprintf("Failed to load module %s: %s", $module->getCode(), $e->getMessage()),
                    $e
                );
            }
        }
    }
};
