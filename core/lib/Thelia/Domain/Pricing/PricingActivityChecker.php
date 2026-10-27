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

use Thelia\Domain\Pricing\CustomerPrice\CustomerDiscountRate;
use Thelia\Domain\SaleUnit\Pricing\SaleUnitPricingActivityChecker;
use Thelia\Model\Customer;

/**
 * Whether anything at all is pricing the catalog beyond `product_price`, so that a
 * shop without a single rule, operation or customer group keeps the query plans and
 * the caches it had before.
 *
 * Every answer comes from {@see CatalogActivityProbe}: one statement per request,
 * whichever of these questions the request asks, and however many times. It keeps nothing
 * of its own: the probe is what the kernel forgets with the request.
 */
class PricingActivityChecker
{
    public function __construct(
        private readonly CatalogActivityProbe $catalogActivityProbe,
        private readonly CustomerDiscountRate $customerDiscountRate,
        private readonly ?SaleUnitPricingActivityChecker $saleUnitPricingActivityChecker = null,
    ) {
    }

    /**
     * Whether a visitor may be owed a price other than the catalog columns: the one
     * guard every reader asks before spending a query on resolving prices. A visitor
     * with no account is never asked about the rules and operations reserved for
     * named customers or groups: none of them can entitle a visitor.
     */
    public function mayPriceBeyondCatalog(?Customer $customer): bool
    {
        return $this->hasDiscountedCustomer($customer)
            || $this->catalogActivityProbe->read()->customerGroupsPriceAnything()
            || $this->mayResolveEffectivePriceFor($customer);
    }

    /**
     * Whether a catalog price rule, a reserved operation or the sale unit may price a
     * sale element for this visitor: the guard of the effective prices alone, which
     * the customer groups and the discounts do not reach.
     */
    public function mayResolveEffectivePriceFor(?Customer $customer): bool
    {
        $activity = $this->catalogActivityProbe->read();

        return $activity->hasActivePublicRule
            || true === $this->saleUnitPricingActivityChecker?->currentSaleUnitPricesAnything()
            || (null !== $customer && ($activity->hasActiveNamedRule || $activity->hasActiveReservedSale));
    }

    /**
     * Whether two visitors may owe two prices for the same sale element, this one
     * included: what decides that a cart filled anonymously is repriced at sign-in,
     * and that an answer cached for everybody does not suit the one asking.
     */
    public function pricesDependOnWhoIsAsking(?Customer $customer): bool
    {
        return $this->hasDiscountedCustomer($customer) || $this->hasVisitorDependentPricing();
    }

    /**
     * A turned-on rule open to everyone exists. Its dates are not looked at here: the
     * stored segments carry them, and a rule between two windows costs one indexed
     * join, not a wrong price.
     */
    public function hasActivePublicRule(): bool
    {
        return $this->catalogActivityProbe->read()->hasActivePublicRule;
    }

    /**
     * A turned-on rule pricing for named customers exists, so the price of a sale
     * element may depend on who is asking.
     */
    public function hasActiveNamedRule(): bool
    {
        return $this->catalogActivityProbe->read()->hasActiveNamedRule;
    }

    /**
     * A customer group has a price of its own for at least one sale element.
     */
    public function hasCustomerGroupPrices(): bool
    {
        return $this->catalogActivityProbe->read()->hasCustomerGroupPrices;
    }

    /**
     * A customer group discounts the catalog for its members.
     */
    public function hasDiscountedCustomerGroup(): bool
    {
        return $this->catalogActivityProbe->read()->hasDiscountedCustomerGroup;
    }

    /**
     * The customer has a discount of their own, as the database has it (one read per
     * request and customer, none for a visitor).
     */
    public function hasDiscountedCustomer(?Customer $customer): bool
    {
        return $this->customerDiscountRate->rateOf($customer) > 0.0;
    }

    /**
     * Two visitors may owe two different prices for the same sale element, whoever
     * they are: a shared cache of prices then has to step aside. Rules and operations
     * reserved for customer groups are among the named ones and the reserved ones.
     */
    public function hasVisitorDependentPricing(): bool
    {
        $activity = $this->catalogActivityProbe->read();

        return $activity->hasActiveNamedRule
            || $activity->hasActiveReservedSale
            || $activity->customerGroupsPriceAnything();
    }
}
