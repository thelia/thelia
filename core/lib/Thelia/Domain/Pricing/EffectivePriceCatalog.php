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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * The effective prices of the current request, warmed by the page and read by the
 * row.
 *
 * The product loop parses one row at a time and the API transforms one resource at
 * a time, so each of them asks here per sale element; whoever holds the whole page
 * warms it first, and every later question is answered from memory. A sale element
 * asked for without a warm-up costs one resolution of its own, remembered too - a
 * miss included, so the same absent price is never looked up twice.
 *
 * Nothing at all is asked of the database while no rule and no reserved operation
 * is running.
 */
class EffectivePriceCatalog implements ResetInterface
{
    /** @var array<string, array<int, EffectivePrice|false>> per customer and currency: the price, or false for "none" */
    private array $memo = [];

    public function __construct(
        private readonly EffectivePriceResolver $resolver,
        private readonly PricingActivityChecker $activityChecker,
        private readonly SaleAudienceChecker $saleAudienceChecker,
    ) {
    }

    /**
     * Resolves a page of sale elements at once and hands back what it found.
     *
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, EffectivePrice>
     */
    public function warm(array $productSaleElementsIds, Currency $currency, ?Customer $customer): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds || !$this->pricesAnythingFor($customer)) {
            return [];
        }

        $key = $this->keyOf($currency, $customer);
        $missing = array_values(array_filter(
            $productSaleElementsIds,
            fn (int $pseId): bool => !isset($this->memo[$key][$pseId]),
        ));

        if ([] !== $missing) {
            $resolved = $this->resolver->resolve($missing, $currency, $customer);

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

    public function priceFor(int $productSaleElementsId, Currency $currency, ?Customer $customer): ?EffectivePrice
    {
        return $this->warm([$productSaleElementsId], $currency, $customer)[$productSaleElementsId] ?? null;
    }

    /**
     * The lowest effective price among the sale elements of each product, for the
     * readers that show one price per product: the product loop in its complex form.
     *
     * @param list<int> $productIds
     *
     * @return array<int, EffectivePrice> keyed by product id, absent when no sale element of it is priced
     */
    public function lowestForProducts(array $productIds, Currency $currency, ?Customer $customer): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));

        if ([] === $productIds || !$this->pricesAnythingFor($customer)) {
            return [];
        }

        /** @var array<int, int> $productByPse */
        $productByPse = [];

        foreach (ProductSaleElementsQuery::create()
            ->filterByProductId($productIds, Criteria::IN)
            ->select(['Id', 'ProductId'])
            ->find() as $row) {
            $productByPse[(int) $row['Id']] = (int) $row['ProductId'];
        }

        $lowest = [];

        foreach ($this->warm(array_keys($productByPse), $currency, $customer) as $pseId => $price) {
            $productId = $productByPse[$pseId];

            if (!isset($lowest[$productId]) || $price->untaxedPromoPrice < $lowest[$productId]->untaxedPromoPrice) {
                $lowest[$productId] = $price;
            }
        }

        return $lowest;
    }

    public function reset(): void
    {
        $this->memo = [];
    }

    /**
     * Whether anything at all may price for this visitor beyond the catalog columns:
     * what a caller checks before spending a query on gathering what to warm.
     */
    public function pricesAnythingFor(?Customer $customer): bool
    {
        return $this->activityChecker->hasActivePublicRule()
            || (null !== $customer && ($this->activityChecker->hasActiveNamedRule() || $this->saleAudienceChecker->hasActiveReservedSale()));
    }

    private function keyOf(Currency $currency, ?Customer $customer): string
    {
        return ($customer?->getId() ?? 0).'|'.$currency->getId();
    }
}
