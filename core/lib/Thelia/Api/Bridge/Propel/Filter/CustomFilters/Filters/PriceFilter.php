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
use Thelia\Domain\Pricing\EffectivePrice;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRule;

/**
 * Narrows a listing to a price interval, the one a buyer draws with the two handles of a
 * slider.
 *
 * The price is the one the product card shows: that of the default combination, its promotional
 * price when the combination is on sale or when a catalog price rule or a reserved sale prices
 * it, in the currency the shop is browsed in, taxes of the visitor's delivery country included.
 * The front API serves the card the same way (ProductPriceCurrencyListener picks the currency,
 * EffectivePriceListener the rule price), and this filter asks the same services. A slider
 * drawn on untaxed prices would keep, between 0 and 50, a card reading 55.
 *
 * The two values of the facet are the bounds of the slider, the cheapest and the dearest
 * product of the listing, rounded outwards to whole units. Their title carries the bound, which
 * is what a slider reads, and the selection comes back as `min` and `max` (a single-handle
 * slider sends one figure, read as the maximum).
 *
 * Taxes depend on the product (a tax can read a feature of it), so prices are computed product
 * by product rather than in SQL, and kept for the request. Narrowing the listing adds a
 * condition on the product ids and joins nothing: the order the listing is sorted in is left
 * as it was.
 */
final class PriceFilter implements TheliaFilterInterface, TheliaAggregatedFilterInterface, ResetInterface
{
    use SelectedValuesTrait;

    public const LOWER_BOUND = 1;

    public const UPPER_BOUND = 2;

    /** @var array<string, array<int, float>> displayed prices of every product, per currency, country and customer */
    private array $catalogue = [];

    public function __construct(
        private readonly TaxEngine $taxEngine,
        private readonly ProductPriceCurrencyListener $currencyResolver,
        private readonly EffectivePriceCatalog $effectivePriceCatalog,
        private readonly PricingActivityChecker $activityChecker,
        private readonly CurrentCustomerProvider $currentCustomerProvider,
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

        foreach ($this->displayedPrices(null) as $productId => $price) {
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
     * The price each product shows on its card, rounded to the cent. A product without a
     * default combination, or whose combination has no price in either the visitor's currency
     * or the default one, has no price to show and is left out.
     *
     * @param list<int>|null $productIds null for every product
     *
     * @return array<int, float>
     */
    private function displayedPrices(?array $productIds): array
    {
        $currency = $this->currencyResolver->currentCurrency();
        $country = $this->taxEngine->getDeliveryCountry();
        $customer = $this->pricingCustomer();
        $key = $currency->getId().'-'.$country->getId().'-'.($customer?->getId() ?? 0);

        if (isset($this->catalogue[$key])) {
            return $productIds === null
                ? $this->catalogue[$key]
                : array_intersect_key($this->catalogue[$key], array_flip($productIds));
        }

        $prices = $this->computeDisplayedPrices($productIds, $currency, $country, $customer);

        if ($productIds === null) {
            $this->catalogue[$key] = $prices;
        }

        return $prices;
    }

    /**
     * @param list<int>|null $productIds
     *
     * @return array<int, float>
     */
    private function computeDisplayedPrices(?array $productIds, Currency $currency, Country $country, ?Customer $customer): array
    {
        $saleElements = ProductSaleElementsQuery::create()->filterByIsDefault(true);

        if ($productIds !== null) {
            $saleElements->filterByProductId($productIds, Criteria::IN);
        }

        /** @var array<int, array{Id: int, ProductId: int, Promo: int}> $rows */
        $rows = $saleElements->select(['Id', 'ProductId', 'Promo'])->find()->getData();

        if ($rows === []) {
            return [];
        }

        $defaultCurrency = Currency::getDefaultCurrency();
        $pricesBySaleElement = [];

        /** @var ProductPrice $productPrice */
        foreach (ProductPriceQuery::create()
            ->filterByProductSaleElementsId(array_column($rows, 'Id'), Criteria::IN)
            ->filterByCurrencyId(array_unique([$currency->getId(), $defaultCurrency->getId()]), Criteria::IN)
            ->find() as $productPrice
        ) {
            $pricesBySaleElement[$productPrice->getProductSaleElementsId()][$productPrice->getCurrencyId()] = $productPrice;
        }

        $effectivePrices = $this->effectivePrices(array_map('intval', array_column($rows, 'Id')), $currency, $customer);
        $untaxedByProduct = [];

        foreach ($rows as $row) {
            $effectivePrice = $effectivePrices[(int) $row['Id']] ?? null;
            $untaxed = $effectivePrice instanceof EffectivePrice
                ? $effectivePrice->untaxedPromoPrice
                : $this->untaxedPrice(
                    $pricesBySaleElement[(int) $row['Id']] ?? [],
                    (bool) $row['Promo'],
                    $currency,
                    $defaultCurrency,
                );

            if ($untaxed !== null) {
                $untaxedByProduct[(int) $row['ProductId']] = $untaxed;
            }
        }

        $displayed = [];

        /** @var Product $product */
        foreach (ProductQuery::create()
            ->joinWithTaxRule(Criteria::LEFT_JOIN)
            ->filterById(array_keys($untaxedByProduct), Criteria::IN)
            ->find() as $product
        ) {
            $untaxed = $untaxedByProduct[(int) $product->getId()];

            // No tax rule means no tax, as on the product card.
            $taxed = $product->getTaxRule() instanceof TaxRule
                ? (float) $product->getTaxedPrice($country, $untaxed)
                : $untaxed;

            $displayed[(int) $product->getId()] = round($taxed, 2);
        }

        return $displayed;
    }

    /**
     * The untaxed price of a combination in the visitor's currency, the way
     * ProductSaleElements::getPricesByCurrency() reads it: the price typed in that currency,
     * or the default currency's converted when none was typed.
     *
     * @param array<int, ProductPrice> $prices by currency id
     */
    private function untaxedPrice(array $prices, bool $promo, Currency $currency, Currency $defaultCurrency): ?float
    {
        $price = $prices[$currency->getId()] ?? null;
        $rate = 1.0;

        if (!$price instanceof ProductPrice || $price->getFromDefaultCurrency()) {
            $price = $prices[$defaultCurrency->getId()] ?? null;
            $rate = (float) $currency->getRate() / (float) $defaultCurrency->getRate();
        }

        if (!$price instanceof ProductPrice) {
            return null;
        }

        return (float) ($promo ? $price->getPromoPrice() : $price->getPrice()) * $rate;
    }

    /**
     * The price a catalog price rule or a reserved sale gives the visitor, per sale element.
     *
     * @param list<int> $saleElementIds
     *
     * @return array<int, EffectivePrice>
     */
    private function effectivePrices(array $saleElementIds, Currency $currency, ?Customer $customer): array
    {
        if (!$this->activityChecker->hasActivePublicRule() && !$this->activityChecker->hasVisitorDependentPricing()) {
            return [];
        }

        return $this->effectivePriceCatalog->warm($saleElementIds, $currency, $customer);
    }

    /**
     * The customer a price may depend on. Asked only when something prices per visitor, as
     * EffectivePriceListener does: reading the customer may open the session, and a listing
     * of a shop where nothing prices beyond the catalogue must stay stateless.
     */
    private function pricingCustomer(): ?Customer
    {
        return $this->activityChecker->hasVisitorDependentPricing()
            ? $this->currentCustomerProvider->getCurrentCustomer()
            : null;
    }
}
