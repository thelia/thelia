<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Exception;
use Symfony\Component\VarExporter\Exception\ClassNotFoundException;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Service\ConfigCacheService;

return static function (ContainerConfigurator $configurator): void {
    // Import service configurations
    $configurator->import('packages/*');
    $configurator->import('parameters/*');
    $configurator->import('services/*');


    $serviceConfigurator = $configurator->services();

    $serviceConfigurator->defaults()
        ->autowire(false)
        ->autoconfigure(false)
        ->bind('$kernelCacheDir', '%kernel.cache_dir%')
        ->bind('$cacheDir', '%kernel.cache_dir%')
        ->bind('$kernelDebug', '%kernel.debug%')
        ->bind('$debugMode', '%kernel.debug%')
        ->bind('$debug', '%kernel.debug%')
        ->bind('$kernelEnvironment', '%kernel.environment%')
        ->bind('$environment', '%kernel.environment%')
        ->bind('$env', '%kernel.environment%')
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

    if (!isset($_SERVER['MAILER_DSN'])) {
        $dsn = 'smtp://localhost:25';
        if (ConfigQuery::isSmtpEnable()) {
            $dsn = 'smtp://';

            if (ConfigQuery::getSmtpUsername()) {
                $dsn .= urlencode((string) ConfigQuery::getSmtpUsername()).':'.urlencode((string) ConfigQuery::getSmtpPassword()).'@';
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
        $apiResourcePaths = [
            THELIA_LIB.'/Api/Resource',
        ];
        $modules = ModuleQuery::getActivated();
        /** @var Module $module */
        foreach ($modules as $module) {
            try {
                if (!class_exists($module->getFullNamespace())) {
                    throw new ClassNotFoundException($module->getFullNamespace());
                }

                \call_user_func([$module->getFullNamespace(), 'configureContainer'], $configurator);
                \call_user_func([$module->getFullNamespace(), 'configureServices'], $serviceConfigurator);
                $apiModulePath = $module->getAbsoluteBaseDir().'/Api/Resource';
                if (is_dir($apiModulePath)) {
                    $apiResourcePaths[] = $apiModulePath;
                }
            } catch (Exception $e) {
                if ($_SERVER['APP_DEBUG']) {
                    throw $e;
                }

                Tlog::getInstance()->addError(
                    \sprintf('Failed to load module %s: %s', $module->getCode(), $e->getMessage()),
                    $e
                );
            }
        }

        $configurator->extension('api_platform', ['mapping' => ['paths' => $apiResourcePaths]]);
    }

    $serviceConfigurator->get(ConfigCacheService::class)
        ->public();
};
