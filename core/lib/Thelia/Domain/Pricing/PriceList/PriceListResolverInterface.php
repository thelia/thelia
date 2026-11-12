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

namespace Thelia\Domain\Pricing\PriceList;

use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * The price lists a customer is priced by: the one place that answers what a customer of
 * a price list pays for a sale element, before the rules of the catalog.
 */
interface PriceListResolverInterface
{
    /**
     * @param list<int>       $productSaleElementsIds
     * @param array<int, int> $quantities             sale element id => quantity, 1 when absent
     *
     * @return array<int, PriceListMatch> indexed by sale element id; absent means no price list applies
     */
    public function resolve(array $productSaleElementsIds, Currency $currency, ?Customer $customer, array $quantities = []): array;

    /**
     * The tiers the cart will apply to the customer, whatever the quantity: what a product page
     * shows as its table of tiers. They are those of the first price list that holds the sale
     * element, then, below its lowest minimum quantity, those of the lists ranked after it that
     * price a smaller quantity, and so on down the chain; below the last one the price is not
     * a list's (the catalog, a group), so no tier tells it. A list whose lowest minimum quantity
     * is above 1 is listed all the same; an exclusive list that does not hold the sale element
     * closes it, so it has no tier and ends the chain.
     *
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, list<array{minQuantity: int, untaxedPrice: string}>> indexed by sale element id, the smallest minimum quantity first; absent means no tier
     */
    public function tiersFor(array $productSaleElementsIds, Currency $currency, ?Customer $customer): array;
}
