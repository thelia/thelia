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

use Thelia\Domain\Pricing\BundleEffectivePriceSource;
use Thelia\Domain\Pricing\EffectivePrice;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;

/**
 * The one place that answers what a customer pays: every reader of a price
 * (loops, API, cart, order) goes through it instead of reading the discount of
 * the customer by itself.
 *
 * The price list that applies to the customer comes first and is final. Otherwise the
 * price of the group a customer belongs to comes first, then the promotions, then the
 * base price less the discount of the group; the discount of the customer always comes last.
 */
interface CustomerPriceContextInterface
{
    /**
     * @param list<int>       $productSaleElementsIds
     * @param array<int, int> $quantities             sale element id => quantity bought, 1 when absent: the tier of a price list depends on it
     *
     * @return array<int, ResolvedPrice> indexed by sale element id; a sale element with no price is absent, never an exception
     */
    public function resolve(array $productSaleElementsIds, Currency $currency, ?Customer $customer, array $quantities = []): array;

    /**
     * What a customer pays for bundles priced by the composition each holds (the line of a cart):
     * the price of the components less the discount of the bundle, then the discounts of the group
     * and of the customer. Nothing is read but the customer and their group.
     *
     * @template TKey of array-key
     *
     * @param array<TKey, EffectivePrice> $bundlePrices as {@see BundleEffectivePriceSource::forLines()} answers them
     *
     * @return array<TKey, ResolvedPrice> by the same keys
     */
    public function resolveBundlePrices(array $bundlePrices, ?Customer $customer): array;

    /**
     * The group of the customer, the default group for a visitor.
     */
    public function customerGroupOf(?Customer $customer): CustomerGroup;

    /**
     * Whether the group of the customer is shown taxed prices (`display_taxed_price`).
     */
    public function displaysTaxedPrices(?Customer $customer): bool;
}
