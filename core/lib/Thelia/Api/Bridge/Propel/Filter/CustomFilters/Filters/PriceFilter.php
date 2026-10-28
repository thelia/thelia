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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaAggregatedFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Api\EventListener\ProductPriceCurrencyListener;
use Thelia\Api\Resource\FilterValue;
use Thelia\Domain\Catalog\Product\VisitorPriceReader;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRule;

/**
 * Narrows a listing to a price interval, the one a buyer draws with the two handles of a
 * slider.
 *
 * The price is the one the product card shows: what the visitor pays for the default
 * combination - the price of their group, the promotional price, the one a catalog price rule
 * or a reserved sale gives, then the discounts of their group and their own - in the currency
 * the shop is browsed in, taxes of the visitor's delivery country included unless their group
 * is shown untaxed prices. The front API serves the card the same way (ProductPriceCurrencyListener
 * picks the currency, EffectivePriceListener the price), and this filter asks the same service.
 * A slider drawn on untaxed prices would keep, between 0 and 50, a card reading 55.
 *
 * The two values of the facet are the bounds of the slider, the cheapest and the dearest
 * product of the listing, rounded outwards to whole units. Their title carries the bound, which
 * is what a slider reads, and the selection comes back as `min` and `max` (a single-handle
 * slider sends one figure, read as the maximum).
 *
 * Taxes depend on the product (a tax can read a feature of it), so prices are computed product
 * by product rather than in SQL, and kept for the request. Narrowing the listing adds a
 * condition on the product ids and joins nothing: the order the listing is sorted in is left
 * as it was. Only the products the query holds when the filter is applied are priced, not the
 * whole catalogue: the filters applied after this one can only narrow that set further, so a
 * product left out of it could not have been in the listing. What the resolver priced stays
 * known for the request, so the price order of the same listing reads it without pricing again.
 */
final class PriceFilter implements TheliaFilterInterface, TheliaAggregatedFilterInterface, ResetInterface
{
    use SelectedValuesTrait;

    public const LOWER_BOUND = 1;

    public const UPPER_BOUND = 2;

    /** @var array<string, array<int, float|null>> displayed price of each product priced so far (null: none to show), per currency, country and customer */
    private array $catalogue = [];

    public function __construct(
        private readonly TaxEngine $taxEngine,
        private readonly ProductPriceCurrencyListener $currencyResolver,
        private readonly VisitorPriceReader $visitorPriceReader,
    ) {
    }

    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['price'];
    }

    public function filter(ModelCriteria $query, $value, bool $isMinOrMaxFilter = false, ?int $categoryDepth = null): void
    {
        $bounds = $this->selectedBounds($value);

        if ($bounds === null) {
            return;
        }

        [$min, $max] = $bounds;
        $productIds = [];

        foreach ($this->displayedPrices($this->idsOf($query)) as $productId => $price) {
            if (($min === null || $price >= $min) && ($max === null || $price <= $max)) {
                $productIds[] = $productId;
            }
        }

        if ($productIds === []) {
            $query->where('1 <> 1');

            return;
        }

        $query->filterBy('Id', $productIds, Criteria::IN);
    }

    public function getValue(ActiveRecordInterface $activeRecord, string $locale, $valueSearched = null, ?int $depth = 1): ?array
    {
        if (!$activeRecord instanceof Product) {
            return null;
        }

        $price = $this->displayedPrices([(int) $activeRecord->getId()])[(int) $activeRecord->getId()] ?? null;

        if ($price === null) {
            return null;
        }

        return [
            $this->bound(self::LOWER_BOUND, floor($price), 1),
            $this->bound(self::UPPER_BOUND, ceil($price), 1),
        ];
    }

    public function getAggregatedValues(array $resourceIds, string $locale, $valueSearched = null, ?int $depth = 1): array
    {
        if ($resourceIds === []) {
            return [];
        }

        $prices = $this->displayedPrices(array_values(array_unique(array_map('intval', $resourceIds))));

        if ($prices === []) {
            return [];
        }

        $lower = floor(min($prices));
        $upper = ceil(max($prices));

        // Every product at the same price: there is no interval to draw.
        if ($lower === $upper) {
            return [];
        }

        return [
            $this->bound(self::LOWER_BOUND, $lower, \count($prices)),
            $this->bound(self::UPPER_BOUND, $upper, \count($prices)),
        ];
    }

    public function reset(): void
    {
        $this->catalogue = [];
    }

    /**
     * @return array{0: float|null, 1: float|null}|null
     */
    private function selectedBounds(mixed $value): ?array
    {
        foreach ((array) $value as $group) {
            // A single-handle slider sends one figure for its group: everything up to it.
            if (is_numeric($group)) {
                return [null, (float) $group];
            }
        }

        foreach ($this->splitSelectedValues($value)['bounded'] as $bounds) {
            $min = isset($bounds['min']) && is_numeric($bounds['min']) ? (float) $bounds['min'] : null;
            $max = isset($bounds['max']) && is_numeric($bounds['max']) ? (float) $bounds['max'] : null;

            if ($min === null && $max === null) {
                continue;
            }

            return $min !== null && $max !== null && $min > $max ? [$max, $min] : [$min, $max];
        }

        return null;
    }

    private function bound(int $id, float $price, int $count): FilterValue
    {
        return (new FilterValue())
            ->setId($id)
            ->setTitle((string) (int) $price)
            ->setCount($count);
    }

    /**
     * The products the query holds at this point, which the filters applied after this one can
     * only narrow. Only their id is read: the query of a collection may eager load columns of
     * the relations it serializes. The order is dropped with them: it does not change which
     * products are held, and it may be written on a virtual column of the query (withColumn())
     * that this read no longer selects.
     *
     * @return list<int>
     */
    private function idsOf(ModelCriteria $query): array
    {
        return array_values(array_unique(array_map('intval', (clone $query)->clearSelectColumns()->clearOrderByColumns()->select('Id')->find()->getData())));
    }

    /**
     * The price each product shows on its card, rounded to the cent. A product without a
     * default combination, or whose combination has no price in either the visitor's currency
     * or the default one, has no price to show and is left out.
     *
     * @param list<int> $productIds
     *
     * @return array<int, float>
     */
    private function displayedPrices(array $productIds): array
    {
        $currency = $this->currencyResolver->currentCurrency();
        $country = $this->taxEngine->getDeliveryCountry();
        $customer = $this->visitorPriceReader->currentCustomer();
        $key = $currency->getId().'-'.$country->getId().'-'.($customer?->getId() ?? 0);

        $missing = array_values(array_filter(
            $productIds,
            fn (int $productId): bool => !\array_key_exists($productId, $this->catalogue[$key] ?? []),
        ));

        if ($missing !== []) {
            $computed = $this->computeDisplayedPrices($missing, $currency, $country);

            foreach ($missing as $productId) {
                $this->catalogue[$key][$productId] = $computed[$productId] ?? null;
            }
        }

        $prices = [];

        foreach ($productIds as $productId) {
            $price = $this->catalogue[$key][$productId] ?? null;

            if ($price !== null) {
                $prices[$productId] = $price;
            }
        }

        return $prices;
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, float>
     */
    private function computeDisplayedPrices(array $productIds, Currency $currency, Country $country): array
    {
        $saleElements = ProductSaleElementsQuery::create()
            ->filterByIsDefault(true)
            ->filterByProductId($productIds, Criteria::IN);

        /** @var array<int, array{Id: int, ProductId: int}> $rows */
        $rows = $saleElements->select(['Id', 'ProductId'])->find()->getData();

        if ($rows === []) {
            return [];
        }

        $resolvedPrices = $this->visitorPriceReader->resolve(array_map('intval', array_column($rows, 'Id')), $currency, true);
        $untaxedByProduct = [];

        foreach ($rows as $row) {
            $resolvedPrice = $resolvedPrices[(int) $row['Id']] ?? null;

            if ($resolvedPrice !== null) {
                $untaxedByProduct[(int) $row['ProductId']] = $resolvedPrice->chargedPrice();
            }
        }

        $displaysTaxedPrices = $this->visitorPriceReader->displaysTaxedPrices();
        $displayed = [];

        /** @var Product $product */
        foreach (ProductQuery::create()
            ->joinWithTaxRule(Criteria::LEFT_JOIN)
            ->filterById(array_keys($untaxedByProduct), Criteria::IN)
            ->find() as $product
        ) {
            $untaxed = $untaxedByProduct[(int) $product->getId()];

            // No tax rule means no tax, as on the product card; a group shown untaxed
            // prices is drawn its slider on them.
            $taxed = $displaysTaxedPrices && $product->getTaxRule() instanceof TaxRule
                ? (float) $product->getTaxedPrice($country, $untaxed)
                : $untaxed;

            $displayed[(int) $product->getId()] = round($taxed, 2);
        }

        return $displayed;
    }
}
