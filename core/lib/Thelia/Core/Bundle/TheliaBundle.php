<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Thelia\Core\Bundle;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\DependencyInjection\ControllerArgumentValueResolverPass;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;
use Thelia\Core\DependencyInjection\Compiler\CurrencyConverterProviderPass;
use Thelia\Core\DependencyInjection\Compiler\FallbackParserPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterApiResourceAddonPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterArchiverPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterCommandPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterCouponConditionPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterCouponPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterFormExtensionPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterFormPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterHookListenersPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterRouterPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterSerializerPass;
use Thelia\Core\DependencyInjection\Compiler\TranslatorPass;
use Thelia\Service\ConfigCacheService;

/**
 * First Bundle use in Thelia
 * It initialize dependency injection container.
 *
 * @TODO load configuration from thelia plugin
 * @TODO register database configuration.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class TheliaBundle extends Bundle
{
    /**
     * Construct the depency injection builder.
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container
            ->addCompilerPass(new FallbackParserPass())
            ->addCompilerPass(new TranslatorPass())
            ->addCompilerPass(new ControllerArgumentValueResolverPass())
            ->addCompilerPass(new RegisterControllerArgumentLocatorsPass())
            ->addCompilerPass(new RegisterHookListenersPass(), PassConfig::TYPE_AFTER_REMOVING)
            ->addCompilerPass(new RegisterRouterPass())
            ->addCompilerPass(new RegisterCouponPass())
            ->addCompilerPass(new RegisterCouponConditionPass())
            ->addCompilerPass(new RegisterArchiverPass())
            ->addCompilerPass(new RegisterSerializerPass())
            ->addCompilerPass(new RegisterFormExtensionPass())
            ->addCompilerPass(new CurrencyConverterProviderPass())
            ->addCompilerPass(new RegisterCommandPass())
            ->addCompilerPass(new RegisterFormPass())
            ->addCompilerPass(new RegisterApiResourceAddonPass())
        ;
    }

    public function boot(): void
    {
        /** @var ConfigCacheService $configCacheService */
        $configCacheService = $this->container->get(ConfigCacheService::class);

        $configCacheService->initCacheConfigs();
    }
}
