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

use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;

/**
 * The one place that answers what a customer pays: every reader of a price
 * (loops, API, cart, order) goes through it instead of reading the discount of
 * the customer by itself.
 *
 * The price of the group a customer belongs to comes first, then the promotions,
 * then the base price less the discount of the group; the discount of the
 * customer always comes last.
 */
interface CustomerPriceContextInterface
{
    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, ResolvedPrice> indexed by sale element id; a sale element with no price is absent, never an exception
     */
    public function resolve(array $productSaleElementsIds, Currency $currency, ?Customer $customer): array;

    /**
     * The group of the customer, the default group for a visitor.
     */
    public function customerGroupOf(?Customer $customer): CustomerGroup;

    /**
     * Whether the group of the customer is shown taxed prices (`display_taxed_price`).
     */
    public function displaysTaxedPrices(?Customer $customer): bool;
}
