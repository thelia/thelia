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
use Thelia\Core\Thelia;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

return static function (ContainerConfigurator $configurator): void {
    $serviceConfigurator = $configurator->services();

    $serviceConfigurator->defaults()
        ->autowire(false)
        ->autoconfigure(false)
        ->bind('$kernelCacheDir', '%kernel.cache_dir%')
        ->bind('$kernelDebug', '%kernel.debug%')
        ->bind('$kernelEnvironment', '%kernel.environment%')
        ->bind('$sessionSavePath', '%session.save_path%')
        ->bind('$theliaParserLoops', '%Thelia.parser.loops%')
        ->bind('$formDefinition', '%Thelia.parser.forms%')
        ->bind('$propelCollectionExtensions', tagged_iterator('thelia.api.propel.query_extension.collection'))
        ->bind('$propelItemExtensions', tagged_iterator('thelia.api.propel.query_extension.item'))
        ->bind('$apiResourceAddons', '%Thelia.api.resource.addons%');

    $serviceConfigurator->load('Thelia\\', THELIA_LIB)
        ->exclude(
            [
                THELIA_LIB.'/Command/Skeleton/Module/I18n/*.php',
                THELIA_LIB.'/Config/**/*.php',
            ]
        )
        ->autowire()
        ->autoconfigure();

    foreach (Thelia::getTemplateComponentsDirectories() as $namespace => $resource) {
        if (is_dir($resource)) {
            $serviceConfigurator->load($namespace, $resource)
                ->autowire()
                ->autoconfigure();
        }
    }

    if (!isset($_SERVER['MAILER_DSN'])) {
        $dsn = 'smtp://localhost:25';
        if (ConfigQuery::isSmtpEnable()) {
            $dsn = 'smtp://';

            if (ConfigQuery::getSmtpUsername()) {
                $dsn .= urlencode(ConfigQuery::getSmtpUsername()).':'.urlencode(ConfigQuery::getSmtpPassword()).'@';
            }

            // Escape "%" added by urlencode
            $dsn = str_replace('%', '%%', $dsn);

            $dsn .= ConfigQuery::getSmtpHost().':'.ConfigQuery::getSmtpPort();
        }

        $configurator->extension('framework', [
            'mailer' => [
                'dsn' => addslashes($dsn),
            ],
        ]);
    }

    if (\defined('THELIA_INSTALL_MODE') === false) {
        $apiModulePaths = [];
        $modules = ModuleQuery::getActivated();
        /** @var Module $module */
        foreach ($modules as $module) {
            try {
                \call_user_func([$module->getFullNamespace(), 'configureContainer'], $configurator);
                \call_user_func([$module->getFullNamespace(), 'configureServices'], $serviceConfigurator);
                $apiResourcePath = $module->getAbsoluteBaseDir().'/Api/Resource';
                if (is_dir($apiResourcePath)) {
                    $apiModulePaths[] = $apiResourcePath;
                }
            } catch (\Exception $e) {
                if ($_SERVER['APP_DEBUG']) {
                    throw $e;
                }
                Tlog::getInstance()->addError(
                    sprintf('Failed to load module %s: %s', $module->getCode(), $e->getMessage()),
                    $e
                );
            }
        }

        $configurator->extension('api_platform', ['mapping' => ['paths' => $apiModulePaths]]);
    }

    $serviceConfigurator->get(ConfigCacheService::class)
        ->public();
};
