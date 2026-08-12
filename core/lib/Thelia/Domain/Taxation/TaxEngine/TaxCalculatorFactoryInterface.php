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

namespace Thelia\Domain\Taxation\TaxEngine;

/**
 * Builds the tax calculator used by Thelia.
 *
 * Replace the default implementation to plug a custom tax engine in, for example
 * from a module configureServices():
 *
 *     $services->set(MyTaxCalculatorFactory::class)->autowire();
 *     $services->alias(TaxCalculatorFactoryInterface::class, MyTaxCalculatorFactory::class);
 *
 * Each call must return a new instance: a calculator carries the product, country
 * and state it was loaded with.
 */
interface TaxCalculatorFactoryInterface
{
    public function createTaxCalculator(): TaxCalculatorInterface;
}
