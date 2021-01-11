<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Thelia\Log\Tlog;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

return function(ContainerConfigurator $configurator) {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Thelia\\',THELIA_LIB )
        ->exclude(
            [
                THELIA_LIB."/Command/Skeleton/Module/I18n/*.php",
                THELIA_LIB."/Config/**/*.php"
            ]
        );

    if (\defined("THELIA_INSTALL_MODE") === false) {
        $modules = ModuleQuery::getActivated();

        /** @var Module $module */
        foreach ($modules as $module) {
            try {
                $services->load($module->getCode().'\\', $module->getAbsoluteBaseDir())
                    ->exclude([
                        $module->getAbsoluteBaseDir() . "/I18n/*"
                    ]);
            } catch (\Exception $e) {
                Tlog::getInstance()->addError(
                    sprintf("Failed to load module %s: %s", $module->getCode(), $e->getMessage()),
                    $e
                );
            }
        }
    }
};