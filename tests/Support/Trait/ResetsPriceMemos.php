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

namespace Thelia\Tests\Support\Trait;

use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\PriceFilter;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\CustomerPrice\CustomerDiscountRate;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContext;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Domain\SaleUnit\Pricing\SaleUnitPricingActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;

/**
 * The shop reads its prices once per request and forgets them with the request
 * (kernel.reset). A test that reads the same price twice in one process, after changing
 * what prices it, resets the memos by hand the way the kernel would.
 */
trait ResetsPriceMemos
{
    protected function resetPriceMemos(): void
    {
        foreach ([
            CatalogActivityProbe::class,
            CustomerGroupResolver::class,
            CustomerDiscountRate::class,
            CustomerPriceContext::class,
            EffectivePriceCatalog::class,
            SaleAudienceChecker::class,
            SaleUnitContext::class,
            SaleUnitActivityChecker::class,
            SaleUnitPricingActivityChecker::class,
            PriceFilter::class,
        ] as $service) {
            $this->getService($service)->reset();
        }
    }
}
