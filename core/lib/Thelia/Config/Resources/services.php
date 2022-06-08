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

use Thelia\Core\Service\ConfigCacheService;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

return function (ContainerConfigurator $configurator): void {
    $serviceConfigurator = $configurator->services();

    $serviceConfigurator->defaults()
        ->autowire(false)
        ->autoconfigure(false)
        ->bind('$kernelCacheDir', '%kernel.cache_dir%')
        ->bind('$kernelDebug', '%kernel.debug%')
        ->bind('$kernelEnvironment', '%kernel.environment%')
        ->bind('$sessionSavePath', '%session.save_path%')
        ->bind('$theliaParserLoops', '%Thelia.parser.loops%')
        ->bind('$formDefinition', '%Thelia.parser.forms%');

    $serviceConfigurator->load('Thelia\\', THELIA_LIB)
        ->exclude(
            [
                THELIA_LIB.'/Command/Skeleton/Module/I18n/*.php',
                THELIA_LIB.'/Config/**/*.php',
            ]
        )->autowire()
        ->autoconfigure();

    if (isset($_SERVER['ACTIVE_ADMIN_TEMPLATE'])) {
        $serviceConfigurator->load('backOffice\\', THELIA_ROOT.'templates/backOffice'.DS.$_SERVER['ACTIVE_ADMIN_TEMPLATE'].DS.'components')
            ->autowire()->autoconfigure();
    }
    if (isset($_SERVER['ACTIVE_FRONT_TEMPLATE'])) {
        $serviceConfigurator->load('frontOffice\\', THELIA_ROOT.'templates/frontOffice'.DS.$_SERVER['ACTIVE_FRONT_TEMPLATE'].DS.'components')
            ->autowire()->autoconfigure();
    }

    if (ConfigQuery::isSmtpEnable()) {
        $dsn = 'smtp://';

        if (ConfigQuery::getSmtpUsername()) {
            $dsn .= ConfigQuery::getSmtpUsername().':'.ConfigQuery::getSmtpPassword();
        }

        $dsn .= ConfigQuery::getSmtpHost().':'.ConfigQuery::getSmtpPort();
        $configurator->extension('framework', [
            'mailer' => [
                'dsn' => $dsn,
            ],
        ]);
    }

    if (\defined('THELIA_INSTALL_MODE') === false) {
        $modules = ModuleQuery::getActivated();
        /** @var Module $module */
        foreach ($modules as $module) {
            try {
                \call_user_func([$module->getFullNamespace(), 'configureContainer'], $configurator);
                \call_user_func([$module->getFullNamespace(), 'configureServices'], $serviceConfigurator);
            } catch (\Exception $e) {
                if ($_ENV['APP_DEBUG']) {
                    throw $e;
                }
                Tlog::getInstance()->addError(
                    sprintf('Failed to load module %s: %s', $module->getCode(), $e->getMessage()),
                    $e
                );
            }
        }
    }

    $serviceConfigurator->get(ConfigCacheService::class)
        ->public();
};
