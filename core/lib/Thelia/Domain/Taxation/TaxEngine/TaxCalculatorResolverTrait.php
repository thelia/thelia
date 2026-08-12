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

use Propel\Runtime\Propel;
use Thelia\Core\Event\Tax\TaxCalculatorEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Map\TaxTableMap;

/**
 * Obtains a tax calculator from a call site that has no container.
 *
 * Propel models, export types built with `new` and module base classes cannot
 * receive a TaxCalculatorFactoryInterface through autowiring. They reach the
 * factory through the event dispatcher carried by the Propel connection, the
 * same route Model\Tax::getTypeInstance() already uses for tax types.
 */
trait TaxCalculatorResolverTrait
{
    protected function createTaxCalculator(): TaxCalculatorInterface
    {
        $connection = Propel::getServiceContainer()->getWriteConnection(TaxTableMap::DATABASE_NAME);

        // No dispatcher outside of a booted kernel (install scripts, standalone CLI).
        if (!method_exists($connection, 'getEventDispatcher') || null === $eventDispatcher = $connection->getEventDispatcher()) {
            return new Calculator();
        }

        $event = new TaxCalculatorEvent();
        $eventDispatcher->dispatch($event, TheliaEvents::TAX_GET_CALCULATOR);

        return $event->getTaxCalculator() ?? new Calculator();
    }
}
