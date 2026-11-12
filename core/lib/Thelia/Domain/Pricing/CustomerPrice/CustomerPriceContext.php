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
use Thelia\Domain\Pricing\PriceList\PriceListMatch;
use Thelia\Domain\Pricing\PriceList\PriceListResolverInterface;
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
 * before groups existed. A bundle priced by its components is priced by them, less the
 * discount of the group, and nothing competes with it. The discount of the customer always
 * applies last, to whatever won.
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
        private readonly ?PriceListResolverInterface $priceListResolver = null,
    ) {
    }

    public function resolve(array $productSaleElementsIds, Currency $currency, ?Customer $customer, array $quantities = []): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds) {
            return [];
        }

        $matches = $this->priceListResolver?->resolve($productSaleElementsIds, $currency, $customer, $quantities) ?? [];

        if ([] === $matches) {
            return $this->resolveByChain($productSaleElementsIds, $currency, $customer);
        }

        $chainIds = array_values(array_filter(
            $productSaleElementsIds,
            static fn (int $pseId): bool => null === ($matches[$pseId] ?? null)?->untaxedPrice,
        ));
        $chainPrices = $this->resolveByChain($chainIds, $currency, $customer);
        $groupId = $this->customerGroupResolver->groupIdOf($customer);
        $prices = [];

        foreach ($productSaleElementsIds as $pseId) {
            $match = $matches[$pseId] ?? null;

            if (null !== $match && null !== $match->untaxedPrice) {
                $prices[$pseId] = $this->priceListPrice($match, $groupId);
            } elseif (isset($chainPrices[$pseId])) {
                $prices[$pseId] = null === $match ? $chainPrices[$pseId] : $chainPrices[$pseId]->closedBy($match->priceListId);
            }
        }

        return $prices;
    }

    /**
     * What the discounts, the rules and the groups make of the catalog price, for the sale elements
     * no price list prices.
     *
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, ResolvedPrice>
     */
    private function resolveByChain(array $productSaleElementsIds, Currency $currency, ?Customer $customer): array
    {
        if ([] === $productSaleElementsIds) {
            return [];
        }

        $activity = $this->catalogActivityProbe->read();
        [$groupId, $groupRate] = $this->groupPricingOf($customer);
        $key = $this->keyOf($currency, $customer, $groupId);
        $missing = array_values(array_filter(
            $productSaleElementsIds,
            fn (int $pseId): bool => !isset($this->memo[$key][$pseId]),
        ));

        if ([] !== $missing) {
            $resolved = $this->resolveMissing(
                $missing,
                $currency,
                $customer,
                $groupId,
                $groupRate,
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

    public function resolveBundlePrices(array $bundlePrices, ?Customer $customer): array
    {
        // A bundle priced by its components ignores the price lists: they price sale elements.
        if ([] === $bundlePrices) {
            return [];
        }

        [$groupId, $groupRate] = $this->groupPricingOf($customer);
        $customerRate = $this->customerDiscountRate->rateOf($customer);
        $resolved = [];

        foreach ($bundlePrices as $key => $bundlePrice) {
            $resolved[$key] = $this->withCustomerDiscount($this->bundleCandidate($bundlePrice, $groupId, $groupRate), $customerRate);
        }

        return $resolved;
    }

    public function reset(): void
    {
        $this->memo = [];
    }

    /**
     * The group a customer is priced as, and its discount rate in %: the default group, at no
     * rate, while no group prices anything of its own nor discounts anything.
     *
     * @return array{0: int, 1: float}
     */
    private function groupPricingOf(?Customer $customer): array
    {
        $activity = $this->catalogActivityProbe->read();
        $group = $activity->customerGroupsPriceAnything() ? $this->customerGroupOf($customer) : null;
        $groupId = (int) ($group?->getId() ?? $this->customerGroupResolver->defaultGroupId());

        return [$groupId, $activity->hasDiscountedCustomerGroup ? (float) $group?->effectiveDiscountRate() : 0.0];
    }

    /**
     * The price of a price list line: the price, with no promotion and no discount on top of it.
     */
    private function priceListPrice(PriceListMatch $match, int $groupId): ResolvedPrice
    {
        $price = (float) $match->untaxedPrice;

        return new ResolvedPrice(
            $price,
            $price,
            false,
            false,
            ResolvedPrice::SOURCE_PRICE_LIST,
            $groupId,
            0.0,
            0.0,
            true,
            $match->priceListId,
            $match->tiers,
        );
    }

    /**
     * The discount of the customer, applied last to whatever won.
     */
    private function withCustomerDiscount(ResolvedPrice $candidate, float $customerRate): ResolvedPrice
    {
        $customerFactor = 1 - $customerRate / 100;

        return new ResolvedPrice(
            $candidate->untaxedPrice * $customerFactor,
            $candidate->untaxedPromoPrice * $customerFactor,
            $candidate->isPromo,
            $candidate->displayInitialPrice,
            $candidate->source,
            $candidate->customerGroupId,
            $candidate->customerGroupDiscountRate,
            $customerRate,
        );
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

        foreach ($productSaleElementsIds as $pseId) {
            $groupPrice = $groupPrices[$pseId] ?? null;
            $effectivePrice = $effectivePrices[$pseId] ?? null;

            $candidate = match ($effectivePrice?->source) {
                EffectivePrice::SOURCE_SALE_UNIT => $this->saleUnitCandidate($effectivePrice, $groupId, $groupRate),
                EffectivePrice::SOURCE_BUNDLE => $this->bundleCandidate($effectivePrice, $groupId, $groupRate),
                default => $this->groupCandidate($basePrices[$pseId] ?? null, $groupPrice, $currencyId, $groupId, $groupRate),
            };

            if (null === $candidate) {
                continue;
            }

            if (null !== $effectivePrice && !\in_array($effectivePrice->source, [EffectivePrice::SOURCE_SALE_UNIT, EffectivePrice::SOURCE_BUNDLE], true)) {
                $candidate = $this->settle($candidate, $effectivePrice, 0.0 === $groupRate && null === $groupPrice);
            }

            $resolved[$pseId] = $this->withCustomerDiscount($candidate, $customerRate);
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
            // The cheaper wins, as for the catalog: a promo price at or above the price is none.
            $isPromo = null !== $groupPrice->untaxedPromoPrice && $groupPrice->untaxedPromoPrice < $groupPrice->untaxedPrice;

            return new ResolvedPrice(
                $groupPrice->untaxedPrice,
                $isPromo ? $groupPrice->untaxedPromoPrice : $groupPrice->untaxedPrice,
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
        // discount of the group, is what the promotion is shown against, and the
        // cheaper wins: a promo price at or above it is none.
        if (null !== $groupPrice?->untaxedPromoPrice) {
            $discountedPrice = $catalogPrice * $groupFactor;
            $isPromo = $groupPrice->untaxedPromoPrice < $discountedPrice;

            return new ResolvedPrice(
                $discountedPrice,
                $isPromo ? $groupPrice->untaxedPromoPrice : $discountedPrice,
                $isPromo,
                $isPromo,
                ResolvedPrice::SOURCE_CUSTOMER_GROUP,
                $groupId,
                $groupRate,
                0.0,
            );
        }

        $isPromo = true === $basePrices->isPromo;
        $discountedPrice = $catalogPrice * $groupFactor;
        $promoPrice = $basePrices->promoIn($currencyId);

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
     * A bundle priced by its components is sold at the sum of the components less the discount
     * of the bundle, which nothing in the catalog holds: the discount of the group applies to both
     * figures, as to any catalog price, and a price the group has of its own for the sale element
     * standing for the bundle does not reach it. A bundle without a discount is no promotion.
     */
    private function bundleCandidate(EffectivePrice $bundlePrice, int $groupId, float $groupRate): ResolvedPrice
    {
        $groupFactor = 1 - $groupRate / 100;

        return new ResolvedPrice(
            (float) $bundlePrice->untaxedPrice * $groupFactor,
            $bundlePrice->untaxedPromoPrice * $groupFactor,
            $bundlePrice->isPromo(),
            $bundlePrice->displayInitialPrice,
            EffectivePrice::SOURCE_BUNDLE,
            $groupId,
            $groupRate,
            0.0,
        );
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
