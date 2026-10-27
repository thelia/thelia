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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Thelia\Domain\Pricing\CatalogPriceResolverInterface;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContext;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContextInterface;
use Thelia\Domain\Pricing\Rule\CatalogPriceRuleResolver;

/*
 * The catalog price contract a module pricing by rules of its own decorates
 * (#[AsDecorator(CatalogPriceResolverInterface::class)]).
 *
 * Declared here rather than left to the singly-implemented-interface rule of the
 * loader: that rule spans the core and the modules of one container build, so the
 * decorator of a module, which implements the interface too, would remove the
 * alias and leave every reader of a price without a resolver.
 *
 * The customer price context is aliased the same way, for the same reason: a module
 * may decorate it.
 */
return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->alias(CatalogPriceResolverInterface::class, CatalogPriceRuleResolver::class);
    $services->alias(CustomerPriceContextInterface::class, CustomerPriceContext::class);
};
