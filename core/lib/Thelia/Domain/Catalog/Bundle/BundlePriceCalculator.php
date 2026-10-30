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

namespace Thelia\Domain\Catalog\Bundle;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Model\Currency;
use Thelia\Model\ProductBundleItem;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * The price of the bundles priced by their components: the sum of the components, in the
 * currency, less the discount of the bundle. A bundle in fixed mode is not computed here:
 * its price is the one of its own sale element, read like the price of any product.
 *
 * A component costs what its sale element costs alone: the promo price when the sale element
 * is on promotion, its price otherwise, converted from the default currency when the sale
 * element has no price of its own in the currency (what ProductSaleElements::getPricesByCurrency
 * answers, read for the whole batch at once). The rules and the reserved operations of
 * EffectivePriceResolver do not apply to a component: the caller that wants them for the
 * bundle applies them to the result.
 *
 * Amounts are decimal strings of six decimals and never go through a float, but for the
 * conversion between currencies, whose rate is one.
 *
 * A composition that names an item the bundle no longer has, or a retained item whose sale
 * element was deleted, has no price: forCompositions() refuses it with the
 * exception of BundleCompositionResolver, as a price that silently left the missing component
 * out would be lower than the bundle is worth. forBundles(), which prices what a catalogue
 * shows, leaves such a bundle out of its answer: it cannot be sold, and a listing must not fail for it.
 */
final readonly class BundlePriceCalculator
{
    public function __construct(
        private BundleItemRepository $bundleItemRepository,
        private BundleComponentReader $bundleComponentReader,
    ) {
    }

    /**
     * Of the default composition of each bundle. The queries do not depend on the number of
     * bundles.
     *
     * @param list<int> $bundleProductIds
     *
     * @return array<int, BundlePrice> keyed by product id; only the bundles in computed mode that have a price to show
     */
    public function forBundles(array $bundleProductIds, Currency $currency): array
    {
        $settings = $this->settings($bundleProductIds);

        if ([] === $settings) {
            return [];
        }

        $itemsByBundle = $this->bundleItemRepository->forBundles(array_keys($settings));
        $components = $this->bundleComponentReader->read(array_merge(...array_map(
            static fn (array $items): array => array_map(static fn (ProductBundleItem $item): ?int => $item->getProductSaleElementsId(), $items),
            array_values($itemsByBundle),
        )));
        $requests = [];

        foreach (array_keys($settings) as $bundleProductId) {
            $items = $itemsByBundle[$bundleProductId] ?? [];
            $composition = BundleCompositionResolver::defaultComposition($items, $components);

            if (null === self::refusalFor($bundleProductId, $items, $composition)) {
                $requests[$bundleProductId] = [$bundleProductId, $composition];
            }
        }

        return $this->compute($settings, $itemsByBundle, $requests, $currency);
    }

    /**
     * Of compositions already validated by BundleCompositionResolver, for a whole cart at
     * once: the queries do not depend on the number of lines.
     *
     * @param array<int|string, array{0: int, 1: BundleComposition}> $requests bundle product id and composition, by a key of the caller's
     *
     * @return array<int|string, BundlePrice> by the same keys; a request on a product that is not a bundle in computed mode is absent
     *
     * @throws InvalidBundleCompositionException a composition names an item the bundle does not have, or an item with no sale element
     */
    public function forCompositions(array $requests, Currency $currency): array
    {
        if ([] === $requests) {
            return [];
        }

        $bundleProductIds = array_values(array_unique(array_map(static fn (array $request): int => $request[0], $requests)));
        $settings = $this->settings($bundleProductIds);

        if ([] === $settings) {
            return [];
        }

        return $this->compute($settings, $this->bundleItemRepository->forBundles(array_keys($settings)), $requests, $currency);
    }

    /**
     * @param list<int> $bundleProductIds
     *
     * @return array<int, array{discount: string, type: BundleDiscountType}> the bundles in computed mode, by product id
     */
    private function settings(array $bundleProductIds): array
    {
        if ([] === $bundleProductIds) {
            return [];
        }

        $settings = [];

        foreach (ProductQuery::create()
            ->filterById(array_values(array_unique($bundleProductIds)), Criteria::IN)
            ->filterByIsBundle(1)
            ->filterByBundlePriceMode(BundlePriceMode::Computed->value)
            ->select(['Id', 'BundleDiscount', 'BundleDiscountType'])
            ->find() as $row) {
            $settings[(int) $row['Id']] = [
                'discount' => (string) $row['BundleDiscount'],
                'type' => BundleDiscountType::tryFrom((string) $row['BundleDiscountType']) ?? BundleDiscountType::Percent,
            ];
        }

        return $settings;
    }

    /**
     * @param array<int, array{discount: string, type: BundleDiscountType}> $settings
     * @param array<int, list<ProductBundleItem>>                           $itemsByBundle
     * @param array<int|string, array{0: int, 1: BundleComposition}>        $requests
     *
     * @return array<int|string, BundlePrice>
     */
    private function compute(array $settings, array $itemsByBundle, array $requests, Currency $currency): array
    {
        $saleElementsIds = [];

        foreach ($requests as [$bundleProductId, $composition]) {
            if (!isset($settings[$bundleProductId])) {
                continue;
            }

            foreach ($itemsByBundle[$bundleProductId] ?? [] as $item) {
                if (null !== $item->getProductSaleElementsId() && '0' !== $composition->quantityOf((int) $item->getId())) {
                    $saleElementsIds[] = (int) $item->getProductSaleElementsId();
                }
            }
        }

        $unitPrices = $this->unitPrices($saleElementsIds, $currency);
        $prices = [];

        foreach ($requests as $key => [$bundleProductId, $composition]) {
            $setting = $settings[$bundleProductId] ?? null;

            if (null === $setting) {
                continue;
            }

            $refusal = self::refusalFor($bundleProductId, $itemsByBundle[$bundleProductId] ?? [], $composition);

            if (null !== $refusal) {
                throw $refusal();
            }

            $full = 0;

            foreach ($itemsByBundle[$bundleProductId] ?? [] as $item) {
                $saleElementsId = $item->getProductSaleElementsId();
                $quantity = BundleDecimal::toMicro($composition->quantityOf((int) $item->getId()));

                if (null === $saleElementsId || 0 === $quantity) {
                    continue;
                }

                $full += BundleDecimal::multiply($unitPrices[$saleElementsId], $quantity);
            }

            $prices[$key] = new BundlePrice(
                BundleDecimal::format($full),
                BundleDecimal::format($this->discounted($full, $setting['discount'], $setting['type'], $currency)),
            );
        }

        return $prices;
    }

    /**
     * Why a composition cannot be priced, or null: it names an item the bundle does not have,
     * or a retained item whose sale element is gone. The reason is only a reason: the exception
     * reads the catalogue to name the component, so it is built by the caller that throws it,
     * never for a bundle that is merely left out of a list.
     *
     * @param list<ProductBundleItem> $items
     *
     * @return (\Closure(): InvalidBundleCompositionException)|null
     */
    private static function refusalFor(int $bundleProductId, array $items, BundleComposition $composition): ?\Closure
    {
        $known = [];

        foreach ($items as $item) {
            $known[(int) $item->getId()] = $item;
        }

        foreach ($composition->lines as $line) {
            $item = $known[$line->bundleItemId] ?? null;

            if (null === $item) {
                return static fn (): InvalidBundleCompositionException => InvalidBundleCompositionException::unknownItem($line->bundleItemId, $bundleProductId);
            }

            if (null === $item->getProductSaleElementsId()) {
                return static fn (): InvalidBundleCompositionException => InvalidBundleCompositionException::componentNotSellable($line->bundleItemId);
            }
        }

        return null;
    }

    private function discounted(int $full, string $discount, BundleDiscountType $type, Currency $currency): int
    {
        $discountMicro = BundleDecimal::toMicro($discount);

        if (BundleDiscountType::Percent === $type) {
            $ratio = max(0, intdiv(100 * BundleDecimal::SCALE - $discountMicro + 50, 100));

            return BundleDecimal::multiply($full, $ratio);
        }

        $defaultCurrency = Currency::getDefaultCurrency();

        if ((int) $currency->getId() !== (int) $defaultCurrency->getId()) {
            $discountMicro = (int) round($discountMicro * $currency->getRate() / $defaultCurrency->getRate());
        }

        return max(0, $full - $discountMicro);
    }

    /**
     * @param list<int> $saleElementsIds
     *
     * @return array<int, int> millionths of the currency, by sale element id
     */
    private function unitPrices(array $saleElementsIds, Currency $currency): array
    {
        $saleElementsIds = array_values(array_unique($saleElementsIds));

        if ([] === $saleElementsIds) {
            return [];
        }

        $defaultCurrency = Currency::getDefaultCurrency();
        $onPromotion = [];

        foreach (ProductSaleElementsQuery::create()->filterById($saleElementsIds, Criteria::IN)->select(['Id', 'Promo'])->find() as $row) {
            $onPromotion[(int) $row['Id']] = (bool) $row['Promo'];
        }

        $rows = [];

        foreach (ProductPriceQuery::create()
            ->filterByProductSaleElementsId($saleElementsIds, Criteria::IN)
            ->filterByCurrencyId([(int) $currency->getId(), (int) $defaultCurrency->getId()], Criteria::IN)
            ->find() as $productPrice) {
            $rows[(int) $productPrice->getProductSaleElementsId()][(int) $productPrice->getCurrencyId()] = $productPrice;
        }

        $unitPrices = [];

        foreach ($saleElementsIds as $saleElementsId) {
            $own = $rows[$saleElementsId][(int) $currency->getId()] ?? null;
            $promo = $onPromotion[$saleElementsId] ?? false;

            if (null !== $own && !$own->getFromDefaultCurrency()) {
                $unitPrices[$saleElementsId] = BundleDecimal::toMicro((string) ($promo ? $own->getPromoPrice() : $own->getPrice()));

                continue;
            }

            $default = $rows[$saleElementsId][(int) $defaultCurrency->getId()] ?? null;

            if (null === $default) {
                throw new \RuntimeException('Cannot find product prices for currency id: `'.$currency->getId().'`');
            }

            $micro = BundleDecimal::toMicro((string) ($promo ? $default->getPromoPrice() : $default->getPrice()));

            $unitPrices[$saleElementsId] = (int) $currency->getId() === (int) $defaultCurrency->getId()
                ? $micro
                : (int) round($micro * $currency->getRate() / $defaultCurrency->getRate());
        }

        return $unitPrices;
    }
}
