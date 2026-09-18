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
use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * Combines everything that prices a sale element outside the catalog columns,
 * for a batch and a visitor.
 *
 * The rules come first: a rule replaces the promo price, whatever it was. A
 * reserved operation the customer is named on then applies only when it beats
 * what the customer would otherwise pay - the frozen decision of the reserved
 * sales, kept as is.
 */
class EffectivePriceResolver
{
    public function __construct(
        private readonly CatalogPriceResolverInterface $catalogPriceResolver,
        private readonly ReservedSalePriceResolver $reservedSalePriceResolver,
        private readonly SaleAudienceChecker $saleAudienceChecker,
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
        $rulePrices = $this->catalogPriceResolver->resolve($productSaleElementsIds, $currency, $customer, $now);
        $reservedPrices = null !== $customer && $this->saleAudienceChecker->hasActiveReservedSale()
            ? $this->reservedSalePriceResolver->resolve($productSaleElementsIds, $currency, $customer, $now)
            : [];

        $effective = [];

        foreach ($productSaleElementsIds as $pseId) {
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
