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

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class CurrencyConverterProviderPass.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CurrencyConverterProviderPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @api
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('currency.converter')) {
            return;
        }

        $currencyConverter = $container->getDefinition('currency.converter');
        $services = $container->findTaggedServiceIds('currency.converter.provider');

        $providers = [];

        foreach ($services as $id => $attributes) {
            $priority = $attributes[0]['priority'] ?? 0;
            $providers[$priority] = $id;
        }

        if ($providers !== []) {
            $service = array_pop($providers);
            $currencyConverter->addMethodCall('setProvider', [new Reference($service)]);
        } else {
            throw new \LogicException('the currency converter needs a provider, please define one');
        }
    }
}
