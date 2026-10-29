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

namespace Thelia\Domain\Pricing;

use Thelia\Domain\Sale\ReservedPrice;
use Thelia\Domain\Sale\ReservedSalePriceResolver;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Domain\SaleUnit\Pricing\SaleUnitPriceResolver;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * Combines everything that prices a sale element outside the catalog columns,
 * for a batch and a visitor.
 *
 * A bundle priced by its components has no price of the catalog to be ruled on: its
 * price is the sum of its components less its discount, and nothing else touches it.
 *
 * The sale unit speaks next: where the request sells from a unit that priced
 * a sale element itself, that price is the price — the national rules and
 * operations are computed against the national catalog and have nothing to say
 * about it. Everywhere else the rules come next: a rule replaces the promo
 * price, whatever it was. A reserved operation the customer is named on then
 * applies only when it beats what the customer would otherwise pay - the
 * frozen decision of the reserved sales, kept as is.
 */
class EffectivePriceResolver
{
    public function __construct(
        private readonly CatalogPriceResolverInterface $catalogPriceResolver,
        private readonly ReservedSalePriceResolver $reservedSalePriceResolver,
        private readonly SaleAudienceChecker $saleAudienceChecker,
        private readonly ?SaleUnitPriceResolver $saleUnitPriceResolver = null,
        private readonly ?SaleUnitContext $saleUnitContext = null,
        private readonly ?BundleEffectivePriceSource $bundleEffectivePriceSource = null,
    ) {
    }

    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, EffectivePrice> keyed by sale element id; absent when the catalog price stands
     */
    public function resolve(
        array $productSaleElementsIds,
        Currency $currency,
        ?Customer $customer,
        ?\DateTimeInterface $now = null,
    ): array {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds) {
            return [];
        }

        $now ??= new \DateTimeImmutable();
        $bundlePrices = $this->bundleEffectivePriceSource?->forSaleElements($productSaleElementsIds, $currency) ?? [];
        $priced = array_values(array_diff($productSaleElementsIds, array_keys($bundlePrices)));
        $saleUnitPrices = [] !== $priced ? ($this->saleUnitPriceResolver?->resolve($priced, $currency) ?? []) : [];
        $remaining = array_values(array_diff($priced, array_keys($saleUnitPrices)));

        $rulePrices = [] !== $remaining
            ? $this->catalogPriceResolver->resolve($remaining, $currency, $customer, $now)
            : [];
        $reservedPrices = [] !== $remaining && null !== $customer && $this->saleAudienceChecker->hasActiveReservedSale()
            ? $this->reservedSalePriceResolver->resolve($remaining, $currency, $customer, $now)
            : [];

        $effective = [];
        $saleUnitId = $this->saleUnitContext?->getSaleUnit()?->getId();

        foreach ($productSaleElementsIds as $pseId) {
            if (isset($bundlePrices[$pseId])) {
                $effective[$pseId] = $bundlePrices[$pseId];

                continue;
            }

            if (isset($saleUnitPrices[$pseId])) {
                $effective[$pseId] = new EffectivePrice(
                    $pseId,
                    $saleUnitPrices[$pseId],
                    false,
                    EffectivePrice::SOURCE_SALE_UNIT,
                    saleUnitId: $saleUnitId,
                );

                continue;
            }

            $rulePrice = $rulePrices[$pseId] ?? null;
            $reservedPrice = $reservedPrices[$pseId] ?? null;

            if (null !== $reservedPrice && (null === $rulePrice || $reservedPrice->untaxedPromoPrice < $rulePrice->untaxedPrice)) {
                $effective[$pseId] = $this->fromReservedPrice($reservedPrice);

                continue;
            }

            if (null !== $rulePrice) {
                $effective[$pseId] = new EffectivePrice(
                    $pseId,
                    $rulePrice->untaxedPrice,
                    $rulePrice->displayInitialPrice,
                    EffectivePrice::SOURCE_RULE,
                    $rulePrice->ruleId,
                    null,
                    $rulePrice->validUntil,
                );
            }
        }

        return $effective;
    }

    private function fromReservedPrice(ReservedPrice $reservedPrice): EffectivePrice
    {
        return new EffectivePrice(
            $reservedPrice->productSaleElementsId,
            $reservedPrice->untaxedPromoPrice,
            $reservedPrice->displayInitialPrice,
            EffectivePrice::SOURCE_RESERVED_SALE,
            null,
            $reservedPrice->saleId,
            $reservedPrice->saleEndDate,
        );
    }
}
