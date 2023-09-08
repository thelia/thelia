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

use Thelia\Api\Bridge\Propel\MetaData\Property\PropelPropertyMetadataFactory;
use Thelia\Api\Bridge\Propel\OpenApiDecorator\HideExtendDecorator;
use Thelia\Api\Bridge\Propel\OpenApiDecorator\JwtDecorator;
use Thelia\Api\Bridge\Propel\Routing\IriConverter;
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
        $modules = ModuleQuery::getActivated();
        /** @var Module $module */
        foreach ($modules as $module) {
            try {
                \call_user_func([$module->getFullNamespace(), 'configureContainer'], $configurator);
                \call_user_func([$module->getFullNamespace(), 'configureServices'], $serviceConfigurator);
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
    }

    $serviceConfigurator->get(ConfigCacheService::class)
        ->public();

            //        $resourceExtends = $container->getParameter('Thelia.api.resource.extends');
//        $chainLoader = $serviceConfigurator->get('serializer.mapping.cache_warmer');
//        dd($chainLoader);
//        $serializerLoaders = $chainLoader->getArgument(0);
//
//        $extendLoader = new Definition(
//            ExtendLoader::class,
//            [$resourceExtends]
//        );
//        $extendLoader->setPublic(false);
//        $serializerLoaders[] = $extendLoader;
//
//        $chainLoader->replaceArgument(0, $serializerLoaders);
//        $this->getContainer()->getDefinition('serializer.mapping.cache_warmer')->replaceArgument(0, $serializerLoaders);
};
