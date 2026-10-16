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

namespace Thelia\Domain\SaleUnit;

use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\SaleUnit\Config\SaleUnitConfigScope;
use Thelia\Domain\SaleUnit\Exception\NoCurrentSaleUnitException;
use Thelia\Model\SaleUnit;

/**
 * The sale unit the current request sells from, and nothing else: resolution,
 * scoped prices, stocks and configuration are services of their own, the way
 * the language, the currency and the customer each have theirs.
 *
 * Null is a legitimate state, not an error: it is every request of a shop with
 * no active sale unit (the single-shop mode this feature leaves untouched), and
 * it is a multi-sale-unit request nothing resolved. A reader that can serve a
 * national answer reads getSaleUnit() and handles null; a writer that must not
 * guess (an order, a stock movement) calls requireSaleUnit() and lets it throw.
 *
 * The sale unit it holds is also the one whose configuration the static readers
 * answer for (SaleUnitConfigScope): the store settings and the module settings it
 * sets for itself.
 */
class SaleUnitContext implements ResetInterface
{
    private ?SaleUnit $saleUnit = null;

    public function getSaleUnit(): ?SaleUnit
    {
        return $this->saleUnit;
    }

    public function setSaleUnit(?SaleUnit $saleUnit): void
    {
        $this->saleUnit = $saleUnit;
        SaleUnitConfigScope::set($saleUnit?->getId());
    }

    public function requireSaleUnit(): SaleUnit
    {
        return $this->saleUnit ?? throw new NoCurrentSaleUnitException();
    }

    public function reset(): void
    {
        $this->saleUnit = null;
        SaleUnitConfigScope::reset();
    }
}
