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

namespace Thelia\Domain\Catalog\Product;

use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContextInterface;
use Thelia\Domain\Pricing\CustomerPrice\ResolvedPrice;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * What the front readers of a price - the product loops, the front API, the data
 * access of the themes, the price filter - ask to learn what the visitor at hand pays.
 *
 * A thin door onto {@see CustomerPriceContextInterface}: it brings the customer the
 * request authenticated as, whichever way (token or session), and the guard of
 * {@see PricingActivityChecker::mayPriceBeyondCatalog()}, so a shop without groups,
 * rules, operations or discounts keeps the queries it had before. The back office
 * never comes here: it reads the public catalog.
 */
final readonly class VisitorPriceReader
{
    public function __construct(
        private CustomerPriceContextInterface $customerPriceContext,
        private CurrentCustomerProvider $currentCustomerProvider,
        private PricingActivityChecker $activityChecker,
    ) {
    }

    public function currentCustomer(): ?Customer
    {
        return $this->currentCustomerProvider->getCurrentCustomer();
    }

    /**
     * What the current visitor pays for each sale element, in a currency. A sale element
     * without a price in it is absent.
     *
     * By default empty, without a query, when nothing prices this visitor beyond the
     * catalog: the caller then keeps the catalog price it already holds. A reader that
     * holds no catalog price of its own passes $alsoWithinTheCatalog and takes every
     * price from here.
     *
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, ResolvedPrice> keyed by sale element id
     */
    public function resolve(array $productSaleElementsIds, Currency $currency, bool $alsoWithinTheCatalog = false): array
    {
        if ([] === $productSaleElementsIds) {
            return [];
        }

        $customer = $this->currentCustomer();

        if (!$alsoWithinTheCatalog && !$this->activityChecker->mayPriceBeyondCatalog($customer)) {
            return [];
        }

        return $this->customerPriceContext->resolve($productSaleElementsIds, $currency, $customer);
    }

    /**
     * Whether the current visitor may be owed a price other than the catalog one: the
     * answer to ask before gathering the sale elements of a page to resolve.
     */
    public function mayPriceBeyondTheCatalog(): bool
    {
        return $this->activityChecker->mayPriceBeyondCatalog($this->currentCustomer());
    }

    /**
     * The group of the current visitor shows taxed prices.
     */
    public function displaysTaxedPrices(): bool
    {
        return $this->customerPriceContext->displaysTaxedPrices($this->currentCustomer());
    }
}
