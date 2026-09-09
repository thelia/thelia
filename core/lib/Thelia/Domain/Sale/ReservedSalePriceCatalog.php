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

namespace Thelia\Domain\Sale;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\SaleProductQuery;

/**
 * The reserved prices of the current visitor, for the whole request.
 *
 * {@see ReservedSalePriceResolver} prices a batch, which is what a cart hands it.
 * A read does not: the API transforms one sale element at a time, and the product
 * loop parses one row at a time, so a resolution hanging off each of them would
 * cost one statement per row and grow with the catalog.
 *
 * This resolves once, for everything the visitor's reserved operations cover, and
 * answers every later question from memory. The work is therefore bounded by the
 * size of the operations the visitor is entitled to — not by the size of the page
 * being read, and not by the number of times it is asked.
 *
 * A visitor entitled to no reserved operation — every visitor of most shops —
 * asks the database nothing at all beyond the indexed existence check.
 */
class ReservedSalePriceCatalog implements ResetInterface
{
    /** @var array<string, array<int, ReservedPrice>> resolved prices per customer and currency */
    private array $pricesByCustomerAndCurrency = [];

    /** @var array<string, array<int, list<int>>> the sale elements of each product, per customer and currency */
    private array $saleElementsByProduct = [];

    public function __construct(
        private readonly ReservedSalePriceResolver $resolver,
        private readonly SaleAudienceChecker $saleAudienceChecker,
    ) {
    }

    /**
     * What the customer pays for this sale element under a reserved operation, or
     * null when no operation of theirs beats the price it is on sale for.
     */
    public function priceFor(int $productSaleElementsId, Currency $currency, ?Customer $customer): ?ReservedPrice
    {
        return $this->prices($currency, $customer)[$productSaleElementsId] ?? null;
    }

    /**
     * The cheapest a reserved operation makes any sale element of the product, or
     * null when none of them is discounted for this customer.
     *
     * This is what a reader showing one price per product needs — the product loop
     * in its "complex" form, which prices a product by the lowest of its sale
     * elements rather than by the default one.
     */
    public function lowestPriceForProduct(int $productId, Currency $currency, ?Customer $customer): ?float
    {
        $prices = $this->prices($currency, $customer);

        if ([] === $prices) {
            return null;
        }

        $lowest = null;

        foreach ($this->saleElementsOf($productId, $currency, $customer) as $productSaleElementsId) {
            $reservedPrice = $prices[$productSaleElementsId] ?? null;

            if (null === $reservedPrice) {
                continue;
            }

            $lowest = null === $lowest ? $reservedPrice->untaxedPromoPrice : min($lowest, $reservedPrice->untaxedPromoPrice);
        }

        return $lowest;
    }

    /**
     * @return array<int, ReservedPrice> keyed by sale element id
     */
    public function prices(Currency $currency, ?Customer $customer): array
    {
        if (null === $customer || !$this->saleAudienceChecker->hasActiveReservedSale()) {
            return [];
        }

        $key = $this->keyOf($currency, $customer);

        return $this->pricesByCustomerAndCurrency[$key] ??= $this->resolve($currency, $customer);
    }

    public function reset(): void
    {
        $this->pricesByCustomerAndCurrency = [];
        $this->saleElementsByProduct = [];
    }

    /**
     * @return list<int>
     */
    private function saleElementsOf(int $productId, Currency $currency, ?Customer $customer): array
    {
        if (null === $customer) {
            return [];
        }

        return $this->saleElementsByProduct[$this->keyOf($currency, $customer)][$productId] ?? [];
    }

    private function keyOf(Currency $currency, Customer $customer): string
    {
        return $customer->getId().'|'.$currency->getId();
    }

    /**
     * @return array<int, ReservedPrice>
     */
    private function resolve(Currency $currency, Customer $customer): array
    {
        $saleIds = $this->saleAudienceChecker->getEntitledReservedSaleIds($customer);

        if ([] === $saleIds) {
            return [];
        }

        /** @var list<int> $productIds */
        $productIds = array_map('intval', SaleProductQuery::create()
            ->filterBySaleId($saleIds, Criteria::IN)
            ->groupByProductId()
            ->select('ProductId')
            ->find()
            ->getData());

        if ([] === $productIds) {
            return [];
        }

        // Every sale element of those products, not only the ones an attribute value
        // narrows the operation down to: the resolver applies that selection itself,
        // and a superset costs one statement either way.
        $rows = ProductSaleElementsQuery::create()
            ->filterByProductId($productIds, Criteria::IN)
            ->select(['Id', 'ProductId'])
            ->find()
            ->getData();

        $productSaleElementsIds = [];
        $saleElementsByProduct = [];

        foreach ($rows as $row) {
            $productSaleElementsIds[] = (int) $row['Id'];
            $saleElementsByProduct[(int) $row['ProductId']][] = (int) $row['Id'];
        }

        $this->saleElementsByProduct[$this->keyOf($currency, $customer)] = $saleElementsByProduct;

        if ([] === $productSaleElementsIds) {
            return [];
        }

        return $this->resolver->resolve($productSaleElementsIds, $currency, $customer);
    }
}
