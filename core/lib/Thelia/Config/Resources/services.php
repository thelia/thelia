<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Thelia\Log\Tlog;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

return function(ContainerConfigurator $configurator) {
    $services = $configurator->services()
        ->defaults()
        ->autowire(false)
        ->autoconfigure(false);

    $services->load('Thelia\\',THELIA_LIB )
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
                $serviceLoaderConfig = \call_user_func(array($module->getFullNamespace(), 'serviceLoaderConfig'));

                if (!$serviceLoaderConfig['autoload']) {
                    continue;
                }

                $services->load($module->getCode().'\\', $module->getAbsoluteBaseDir())
                    ->exclude($serviceLoaderConfig['autoloadExclude'])
                    ->autowire($serviceLoaderConfig['autowire'])
                    ->autoconfigure($serviceLoaderConfig['autoconfigure']);
            } catch (\Exception $e) {
                Tlog::getInstance()->addError(
                    sprintf("Failed to load module %s: %s", $module->getCode(), $e->getMessage()),
                    $e
                );
            }
        }
    }
};