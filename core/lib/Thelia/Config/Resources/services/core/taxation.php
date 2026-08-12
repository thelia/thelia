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

use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactory;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;

/*
 * Single substitution point for the tax engine: alias
 * TaxCalculatorFactoryInterface to your own factory from a module
 * configureServices() to replace the calculator everywhere at once.
 *
 * The alias is public so that code holding only the container, such as a
 * module built on BaseModule, can fetch the factory without autowiring.
 */
return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->alias(TaxCalculatorFactoryInterface::class, TaxCalculatorFactory::class)
        ->public();
};
