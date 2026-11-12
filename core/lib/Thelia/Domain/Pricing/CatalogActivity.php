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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What runs in the shop beyond the plain catalog, as read by {@see CatalogActivityProbe}
 * in a single statement: the answer every guard of the front asks before spending a
 * query on prices that may depend on who is asking, or on categories closed to a group.
 */
#[Exclude]
final readonly class CatalogActivity
{
    public function __construct(
        /** A turned-on catalog price rule open to everyone exists. */
        public bool $hasActivePublicRule,
        /** A turned-on catalog price rule reserved for named customers or customer groups exists. */
        public bool $hasActiveNamedRule,
        /** A turned-on sale operation reserved for named customers or customer groups exists. */
        public bool $hasActiveReservedSale,
        /** A customer group has a price of its own for at least one sale element. */
        public bool $hasCustomerGroupPrices,
        /** A customer group has a discount the prices apply (above 0, up to 100). */
        public bool $hasDiscountedCustomerGroup,
        /** Some customer group is restricted to the categories it lists, an empty list included. */
        public bool $hasCategoryRestrictions,
        /** The id of the default customer group, null when the shop has none. */
        public ?int $defaultCustomerGroupId,
        /** The default customer group shows taxed prices. */
        public bool $defaultCustomerGroupDisplaysTaxedPrices = true,
        /** A customer group shows prices the other way round from the default group. */
        public bool $hasCustomerGroupDisplayingPricesOtherwise = false,
        /** A price list that is turned on exists (its period and its customers are looked at by its own reader). */
        public bool $hasPriceLists = false,
        /** A catalog restriction (a customer or a group limited to, or kept from, categories, brands or products) exists. */
        public bool $hasCatalogRestrictions = false,
        /** The shop shows its prices to identified customers only or on request, or a product states a mode of its own other than visible. */
        public bool $hasNonVisiblePriceDisplay = false,
        /** A price list that is turned on is assigned to the default customer group, the group of every visitor. */
        public bool $hasDefaultGroupPriceLists = false,
        /** A catalog restriction is assigned to the default customer group, the group of every visitor. */
        public bool $hasDefaultGroupCatalogRestrictions = false,
        /** A line of a cart still carries the trace of the price list that priced it, whether the list is still on, or gone. */
        public bool $hasCartLinesPricedByPriceLists = false,
    ) {
    }

    /**
     * A customer group changes a price: by a price of its own or by its discount.
     */
    public function customerGroupsPriceAnything(): bool
    {
        return $this->hasCustomerGroupPrices || $this->hasDiscountedCustomerGroup;
    }

    /**
     * What an anonymous visitor is shown may differ from what the plain catalog shows: a mode
     * that hides the prices, a price list or a restriction of the default group.
     */
    public function visitorsMayBeShownApart(): bool
    {
        return $this->hasNonVisiblePriceDisplay || $this->hasDefaultGroupPriceLists || $this->hasDefaultGroupCatalogRestrictions;
    }

    /**
     * Something prices a customer other than the plain catalog does, without a rule or an
     * operation: a customer group, or a price list, including one that was turned off or deleted
     * while a cart still holds a line it priced.
     */
    public function customersMayBePricedApart(): bool
    {
        return $this->customerGroupsPriceAnything() || $this->hasPriceLists || $this->hasCartLinesPricedByPriceLists;
    }
}
