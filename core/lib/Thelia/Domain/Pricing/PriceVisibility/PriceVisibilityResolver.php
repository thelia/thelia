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

namespace Thelia\Domain\Pricing\PriceVisibility;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContextInterface;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Who sees the price of a product, and who may buy it: the one place every reader of a price
 * and every door of the cart ask.
 *
 * The mode of a product is the one it states, else the one of the shop
 * ({@see PriceDisplayMode}). A shop where the mode is visible everywhere (the activity probe
 * says so, in the statement every request already pays) answers visible without reading a
 * product: nothing here costs a query until a product or the shop hides a price.
 *
 * A product that may be bought is one whose mode lets this visitor see its price and that no
 * exclusive price list closes (a sale element the list does not price is shown, never sold).
 */
final class PriceVisibilityResolver implements ResetInterface
{
    /** @var array<int, PriceDisplayMode|null> the mode each product states, null when it follows the shop */
    private array $stated = [];

    public function __construct(
        private readonly CatalogActivityProbe $catalogActivityProbe,
        private readonly CustomerPriceContextInterface $customerPriceContext,
    ) {
    }

    /**
     * Whether the shop or a product hides a price from somebody: the guard of everything
     * below, which answers visible without a query when it is false.
     */
    public function isActive(): bool
    {
        return $this->catalogActivityProbe->read()->hasNonVisiblePriceDisplay;
    }

    /**
     * Whether something may close a sale element to a buyer, the mode of a product or an
     * exclusive price list: the guard of the cart, which asks nothing more while it is false.
     */
    public function mayRefuseAPurchase(): bool
    {
        $activity = $this->catalogActivityProbe->read();

        return $activity->hasNonVisiblePriceDisplay || $activity->hasPriceLists;
    }

    /**
     * Whether a price list that is on may refuse a purchase (an exclusive one closes what it
     * does not hold): the only reason to resolve the price of a sale element that is read without
     * its prices, the line of a cart.
     */
    public function priceListsMayRefuseAPurchase(): bool
    {
        return $this->catalogActivityProbe->read()->hasPriceLists;
    }

    /**
     * The mode that applies to the product: its own, else the one of the shop. It is the same
     * for every visitor, who is nevertheless asked for: what the mode lets them see is
     * {@see PriceDisplayMode::showsPrice()}.
     */
    public function modeFor(Product $product, ?Customer $customer): PriceDisplayMode
    {
        if (!$this->isActive()) {
            return PriceDisplayMode::Visible;
        }

        return PriceDisplayMode::fromProductValue($product->getPriceDisplayMode()) ?? PriceDisplayMode::fromShopConfiguration();
    }

    public function modeOfProductId(int $productId): PriceDisplayMode
    {
        if (!$this->isActive()) {
            return PriceDisplayMode::Visible;
        }

        $this->preload([$productId]);

        return $this->stated[$productId] ?? PriceDisplayMode::fromShopConfiguration();
    }

    /**
     * Reads, in one statement, the mode the products state: the page of a listing is then
     * answered from memory. Nothing is read while every price is visible.
     *
     * @param list<int> $productIds
     */
    public function preload(array $productIds): void
    {
        if (!$this->isActive()) {
            return;
        }

        $missing = array_values(array_filter(
            array_unique(array_map('intval', $productIds)),
            fn (int $productId): bool => !\array_key_exists($productId, $this->stated),
        ));

        if ([] === $missing) {
            return;
        }

        foreach ($missing as $productId) {
            $this->stated[$productId] = null;
        }

        /** @var list<array{Id: int|string, PriceDisplayMode: string|null}> $rows */
        $rows = ProductQuery::create()
            ->filterById($missing, Criteria::IN)
            ->select(['Id', 'PriceDisplayMode'])
            ->find()
            ->getData();

        foreach ($rows as $row) {
            $this->stated[(int) $row['Id']] = PriceDisplayMode::fromProductValue($row['PriceDisplayMode']);
        }
    }

    public function showsPriceOf(int $productId, ?Customer $customer): bool
    {
        return $this->modeOfProductId($productId)->showsPrice($customer);
    }

    /**
     * The products of the list whose price this visitor is not shown.
     *
     * @param list<int> $productIds
     *
     * @return list<int>
     */
    public function productsWithHiddenPrice(array $productIds, ?Customer $customer): array
    {
        if (!$this->isActive()) {
            return [];
        }

        $this->preload($productIds);

        return array_values(array_filter(
            array_unique(array_map('intval', $productIds)),
            fn (int $productId): bool => !$this->showsPriceOf($productId, $customer),
        ));
    }

    /**
     * The condition a query on products adds to keep the ones whose price this visitor is shown,
     * null when every price is: the filters of a price only ever see the products that show
     * theirs, so that a filter does not tell the price it hides.
     *
     * Written on the id column of a product, whatever table it is in. The values it names are the
     * three modes: nothing is bound.
     */
    public function shownPriceClause(string $productIdColumn, ?Customer $customer): ?string
    {
        if (!$this->isActive()) {
            return null;
        }

        $shown = array_map(
            static fn (PriceDisplayMode $mode): string => "'".$mode->value."'",
            array_values(array_filter(PriceDisplayMode::cases(), static fn (PriceDisplayMode $mode): bool => $mode->showsPrice($customer))),
        );
        $known = array_map(static fn (PriceDisplayMode $mode): string => "'".$mode->value."'", PriceDisplayMode::cases());
        $shopShows = PriceDisplayMode::fromShopConfiguration()->showsPrice($customer);

        return \sprintf(
            '%s IN (SELECT price_display_product.id FROM `product` price_display_product WHERE price_display_product.price_display_mode IN (%s) OR ((price_display_product.price_display_mode IS NULL OR price_display_product.price_display_mode NOT IN (%s)) AND %d = 1))',
            $productIdColumn,
            implode(', ', $shown),
            implode(', ', $known),
            $shopShows ? 1 : 0,
        );
    }

    /**
     * The sale elements this visitor may not put in a cart: those of a product whose mode does
     * not let them buy, and those an exclusive price list does not price.
     *
     * @param list<int>       $productSaleElementsIds
     * @param array<int, int> $quantities             sale element id => quantity wanted, 1 when absent
     *
     * @return list<int>
     */
    public function unpurchasableSaleElements(array $productSaleElementsIds, ?Customer $customer, Currency $currency, array $quantities = []): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds || !$this->mayRefuseAPurchase()) {
            return [];
        }

        $activity = $this->catalogActivityProbe->read();

        $refused = [];

        if ($activity->hasNonVisiblePriceDisplay) {
            /** @var list<array{Id: int|string, ProductId: int|string}> $rows */
            $rows = ProductSaleElementsQuery::create()
                ->filterById($productSaleElementsIds, Criteria::IN)
                ->select(['Id', 'ProductId'])
                ->find()
                ->getData();

            $this->preload(array_map(static fn (array $row): int => (int) $row['ProductId'], $rows));

            foreach ($rows as $row) {
                if (!$this->modeOfProductId((int) $row['ProductId'])->purchasable($customer)) {
                    $refused[(int) $row['Id']] = (int) $row['Id'];
                }
            }
        }

        $remaining = array_values(array_diff($productSaleElementsIds, $refused));

        if ($activity->hasPriceLists && [] !== $remaining) {
            foreach ($this->customerPriceContext->resolve($remaining, $currency, $customer, $quantities) as $productSaleElementsId => $price) {
                if (!$price->purchasable) {
                    $refused[$productSaleElementsId] = $productSaleElementsId;
                }
            }
        }

        sort($refused);

        return $refused;
    }

    public function reset(): void
    {
        $this->stated = [];
    }
}
