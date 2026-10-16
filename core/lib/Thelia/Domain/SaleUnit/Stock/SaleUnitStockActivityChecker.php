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

namespace Thelia\Domain\SaleUnit\Stock;

use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Model\SaleUnitProductSaleElementsQuery;

/**
 * Whether any sale unit keeps a stock of its own: what a cache shared across
 * requests asks, since the requests it serves may buy from different units while
 * its key carries none. Asked of the database once per request, and never in a
 * single shop.
 */
class SaleUnitStockActivityChecker implements ResetInterface
{
    private ?bool $anyUnitKeepsStock = null;

    public function __construct(private readonly SaleUnitActivityChecker $activityChecker)
    {
    }

    public function anySaleUnitKeepsItsOwnStock(): bool
    {
        if (!$this->activityChecker->isMultiSaleUnitMode()) {
            return false;
        }

        return $this->anyUnitKeepsStock ??= SaleUnitProductSaleElementsQuery::create()->exists();
    }

    public function reset(): void
    {
        $this->anyUnitKeepsStock = null;
    }
}
