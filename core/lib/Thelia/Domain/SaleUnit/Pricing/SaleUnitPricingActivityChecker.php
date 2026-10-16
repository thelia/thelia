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

namespace Thelia\Domain\SaleUnit\Pricing;

use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\SaleUnitProductPriceQuery;

/**
 * Whether the current sale unit prices anything of its own, so that a request
 * outside any unit — and a unit that never overrode a price — keeps the query
 * plans and the caches the shop had before.
 *
 * Every method short-circuits on the multi-sale-unit check first, which the
 * request listener has already warmed: a single-shop shop pays no extra query
 * here at all, exactly the compatibility promise the feature stands on.
 */
class SaleUnitPricingActivityChecker implements ResetInterface
{
    /** @var array<int, bool> keyed by sale unit id */
    private array $hasOwnPrices = [];

    private ?bool $anyUnitPricesAnything = null;

    public function __construct(
        private readonly SaleUnitContext $saleUnitContext,
        private readonly SaleUnitActivityChecker $activityChecker,
    ) {
    }

    public function currentSaleUnitPricesAnything(): bool
    {
        if (!$this->activityChecker->isMultiSaleUnitMode()) {
            return false;
        }

        $saleUnit = $this->saleUnitContext->getSaleUnit();

        if (null === $saleUnit) {
            return false;
        }

        return $this->hasOwnPrices[$saleUnit->getId()] ??= SaleUnitProductPriceQuery::create()
            ->filterBySaleUnitId($saleUnit->getId())
            ->exists();
    }

    /**
     * Whether any sale unit at all prices anything of its own: what a cache shared
     * across requests asks, since the requests it serves may sell from different
     * units while its key carries none.
     */
    public function anySaleUnitPricesAnything(): bool
    {
        if (!$this->activityChecker->isMultiSaleUnitMode()) {
            return false;
        }

        return $this->anyUnitPricesAnything ??= SaleUnitProductPriceQuery::create()->exists();
    }

    public function reset(): void
    {
        $this->hasOwnPrices = [];
        $this->anyUnitPricesAnything = null;
    }
}
