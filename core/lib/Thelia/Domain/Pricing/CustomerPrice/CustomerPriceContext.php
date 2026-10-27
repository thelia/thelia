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

namespace Thelia\Domain\Pricing\CustomerPrice;

use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\EffectivePrice;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Pricing\Rule\Storage\BasePriceLoader;
use Thelia\Domain\Pricing\Rule\Storage\BasePrices;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;

/**
 * What a customer pays, resolved for a batch of sale elements.
 *
 * The sale unit speaks first, as it always did: its price is the price, less the
 * discount of the group, and never a promotion. Elsewhere the group comes first: a
 * price the group has of its own replaces the catalog price and promo price; without
 * one, the discount of the group applies to the catalog price only, never to its
 * promotion, and the cheaper of the two wins. Then a catalog price rule or a
 * reserved operation competes with what the group pays, and the lower price wins -
 * except for a group that changes nothing for the sale element (no price of its own,
 * no discount), where the rule replaces the promo price whatever it was, as it did
 * before groups existed. The discount of the customer always applies last, to whatever won.
 * Nothing is rounded.
 *
 * Three statements for a whole batch at most - the catalog prices, the prices of the
 * group, the effective prices - each answer remembered for the request. The group of
 * the customer is the one {@see CustomerGroupResolver} reads back from the database; in
 * a shop where no group has a price of its own nor a discount, every group pays the
 * same, and the prices are resolved for the default group without asking which group
 * the customer is in.
 */
final class CustomerPriceContext implements CustomerPriceContextInterface, ResetInterface
{
    /** @var array<string, array<int, ResolvedPrice|false>> per customer, group, currency and sale unit: the price, or false for "none" */
    private array $memo = [];

    public function __construct(
        private readonly BasePriceLoader $basePriceLoader,
        private readonly CustomerGroupPriceLoader $customerGroupPriceLoader,
        private readonly EffectivePriceCatalog $effectivePriceCatalog,
        private readonly CatalogActivityProbe $catalogActivityProbe,
        private readonly CustomerGroupResolver $customerGroupResolver,
        private readonly CustomerDiscountRate $customerDiscountRate,
        private readonly ?SaleUnitContext $saleUnitContext = null,
    ) {
    }

    public function resolve(array $productSaleElementsIds, Currency $currency, ?Customer $customer): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds) {
            return [];
        }

        $activity = $this->catalogActivityProbe->read();
        $group = $activity->customerGroupsPriceAnything() ? $this->customerGroupOf($customer) : null;
        $groupId = $group?->getId() ?? $this->customerGroupResolver->defaultGroupId();
        $key = $this->keyOf($currency, $customer, (int) $groupId);
        $missing = array_values(array_filter(
            $productSaleElementsIds,
            fn (int $pseId): bool => !isset($this->memo[$key][$pseId]),
        ));

        if ([] !== $missing) {
            $resolved = $this->resolveMissing(
                $missing,
                $currency,
                $customer,
                (int) $groupId,
                $activity->hasDiscountedCustomerGroup ? (float) $group?->effectiveDiscountRate() : 0.0,
                $activity->hasCustomerGroupPrices,
            );

            foreach ($missing as $pseId) {
                $this->memo[$key][$pseId] = $resolved[$pseId] ?? false;
            }
        }

        $prices = [];

        foreach ($productSaleElementsIds as $pseId) {
            $price = $this->memo[$key][$pseId] ?? false;

            if (false !== $price) {
                $prices[$pseId] = $price;
            }
        }

        return $prices;
    }

    /**
     * A visitor, a console command and a guest account belong to the default group: a
     * guest row is reusable by e-mail, which is no proof of belonging anywhere.
     */
    public function customerGroupOf(?Customer $customer): CustomerGroup
    {
        return $this->customerGroupResolver->groupOf($customer);
    }

    /**
     * While every group shows prices the way the default group does, the answer is the
     * default group's, without asking which group the customer is in.
     */
    public function displaysTaxedPrices(?Customer $customer): bool
    {
        $activity = $this->catalogActivityProbe->read();

        if (!$activity->hasCustomerGroupDisplayingPricesOtherwise && null !== $activity->defaultCustomerGroupId) {
            return $activity->defaultCustomerGroupDisplaysTaxedPrices;
        }

        return 1 === (int) $this->customerGroupOf($customer)->getDisplayTaxedPrice();
    }

    public function reset(): void
    {
        $this->memo = [];
    }

    /**
     * @param list<int> $productSaleElementsIds
     * @param float     $groupRate              the effective discount rate of the group, in %
     *
     * @return array<int, ResolvedPrice>
     */
    private function resolveMissing(
        array $productSaleElementsIds,
        Currency $currency,
        ?Customer $customer,
        int $groupId,
        float $groupRate,
        bool $groupsHavePrices,
    ): array {
        $currencyId = (int) $currency->getId();
        $customerRate = $this->customerDiscountRate->rateOf($customer);

        $basePrices = $this->basePriceLoader->load($productSaleElementsIds);
        $groupPrices = $groupsHavePrices
            ? $this->customerGroupPriceLoader->load($productSaleElementsIds, $groupId, $currency)
            : [];
        $effectivePrices = $this->effectivePriceCatalog->warm($productSaleElementsIds, $currency, $customer);

        $resolved = [];

        $customerFactor = 1 - $customerRate / 100;

        foreach ($productSaleElementsIds as $pseId) {
            $groupPrice = $groupPrices[$pseId] ?? null;
            $effectivePrice = $effectivePrices[$pseId] ?? null;

            $candidate = EffectivePrice::SOURCE_SALE_UNIT === $effectivePrice?->source
                ? $this->saleUnitCandidate($effectivePrice, $groupId, $groupRate)
                : $this->groupCandidate($basePrices[$pseId] ?? null, $groupPrice, $currencyId, $groupId, $groupRate);

            if (null === $candidate) {
                continue;
            }

            if (null !== $effectivePrice && EffectivePrice::SOURCE_SALE_UNIT !== $effectivePrice->source) {
                $candidate = $this->settle($candidate, $effectivePrice, 0.0 === $groupRate && null === $groupPrice);
            }

            $resolved[$pseId] = new ResolvedPrice(
                $candidate->untaxedPrice * $customerFactor,
                $candidate->untaxedPromoPrice * $customerFactor,
                $candidate->isPromo,
                $candidate->displayInitialPrice,
                $candidate->source,
                $groupId,
                $groupRate,
                $customerRate,
            );
        }

        return $resolved;
    }

    /**
     * What the group pays before any rule, operation or sale unit: its own price when
     * it has one, the catalog less its discount otherwise. Null when neither prices
     * the sale element in this currency. The discount of the customer is not applied
     * yet: it comes last, to whatever wins.
     */
    private function groupCandidate(?BasePrices $basePrices, ?CustomerGroupPrice $groupPrice, int $currencyId, int $groupId, float $groupRate): ?ResolvedPrice
    {
        $groupFactor = 1 - $groupRate / 100;
        $catalogPrice = $basePrices?->in($currencyId);

        if (null !== $groupPrice?->untaxedPrice) {
            $isPromo = null !== $groupPrice->untaxedPromoPrice;

            return new ResolvedPrice(
                $groupPrice->untaxedPrice,
                $groupPrice->untaxedPromoPrice ?? $groupPrice->untaxedPrice,
                $isPromo,
                $isPromo,
                ResolvedPrice::SOURCE_CUSTOMER_GROUP,
                $groupId,
                $groupRate,
                0.0,
            );
        }

        if (null === $catalogPrice) {
            return null;
        }

        // A promo price of its own without a price: the catalog price, less the
        // discount of the group, is what the promotion is shown against.
        if (null !== $groupPrice?->untaxedPromoPrice) {
            return new ResolvedPrice(
                $catalogPrice * $groupFactor,
                $groupPrice->untaxedPromoPrice,
                true,
                true,
                ResolvedPrice::SOURCE_CUSTOMER_GROUP,
                $groupId,
                $groupRate,
                0.0,
            );
        }

        $isPromo = true === $basePrices?->isPromo;
        $discountedPrice = $catalogPrice * $groupFactor;
        $promoPrice = $basePrices?->promoIn($currencyId);

        // A promo price of the catalog is the customer's as it stands: the discount of the
        // group goes to the base price only. A promo price equal to the price is no promotion.
        if (null === $promoPrice || $promoPrice === $catalogPrice) {
            $promoPrice = $discountedPrice;
        } elseif ($isPromo && $promoPrice >= $discountedPrice) {
            // The cheaper wins: the discounted base beats a dearer promotion, which is then none.
            $promoPrice = $discountedPrice;
            $isPromo = false;
        }

        return new ResolvedPrice(
            $discountedPrice,
            $promoPrice,
            $isPromo,
            $isPromo,
            ResolvedPrice::SOURCE_BASE,
            $groupId,
            $groupRate,
            0.0,
        );
    }

    /**
     * The price of the sale unit is the price of the sale element there, never a
     * promotion; the discount of the group applies to it as to any catalog price, and
     * a price the group has of its own does not reach it.
     */
    private function saleUnitCandidate(EffectivePrice $effectivePrice, int $groupId, float $groupRate): ResolvedPrice
    {
        $price = $effectivePrice->untaxedPromoPrice * (1 - $groupRate / 100);

        return new ResolvedPrice($price, $price, false, false, EffectivePrice::SOURCE_SALE_UNIT, $groupId, $groupRate, 0.0);
    }

    /**
     * Brings in the rule or the reserved operation that priced the sale element. The
     * price the promotion is shown against stays the group's.
     */
    private function settle(ResolvedPrice $candidate, EffectivePrice $effectivePrice, bool $groupIsNeutral): ResolvedPrice
    {
        if (!$groupIsNeutral && $effectivePrice->untaxedPromoPrice >= $candidate->chargedPrice()) {
            return $candidate;
        }

        return new ResolvedPrice(
            $candidate->untaxedPrice,
            $effectivePrice->untaxedPromoPrice,
            true,
            $effectivePrice->displayInitialPrice,
            $effectivePrice->source,
            $candidate->customerGroupId,
            $candidate->customerGroupDiscountRate,
            0.0,
        );
    }

    private function keyOf(Currency $currency, ?Customer $customer, int $groupId): string
    {
        // The sale unit is part of the key, as in EffectivePriceCatalog: within one
        // process the same sale element may owe two units two prices.
        return ($customer?->getId() ?? 0).'|'.$groupId.'|'.$currency->getId().'|'.($this->saleUnitContext?->getSaleUnit()?->getId() ?? 0);
    }
}
