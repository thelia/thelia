<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class CurrencyConverterProviderPass
 * @package Thelia\Core\DependencyInjection\Compiler
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CurrencyConverterProviderPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('currency.converter')) {
            return;
        }

        $currencyConverter = $container->getDefinition('currency.converter');
        $services = $container->findTaggedServiceIds('currency.converter.provider');

        $providers = [];

        foreach ($services as $id => $attributes) {
            $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
            $providers[$priority] = $id;
        }

        if (false === empty($providers)) {
            $service = array_pop($providers);
            $currencyConverter->addMethodCall('setProvider', [new Reference($service)]);
        } else {
            throw new \LogicException('the currency converter needs a provider, please define one');
        }
    }
}
