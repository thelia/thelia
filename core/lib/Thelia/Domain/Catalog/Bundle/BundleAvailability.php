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

use Thelia\Model\ProductBundleItem;

/**
 * How many bundles can be sold: the most that every retained component can supply, that
 * is the smallest of floor(stock / quantity) over the components. A retained component
 * that cannot be sold (orphaned, offline, a bundle itself) makes it zero. A virtual
 * component has no stock to run out of; a bundle of virtual components only is unlimited.
 *
 * The stock is read as the cart reads it (see BundleComponentReader). Whether the shop
 * refuses an order beyond it is the setting of the cart, not of this class.
 */
final readonly class BundleAvailability
{
    public const UNLIMITED = \PHP_INT_MAX;

    public function __construct(
        private BundleItemRepository $bundleItemRepository,
        private BundleComponentReader $bundleComponentReader,
    ) {
    }

    /**
     * Of the default composition of each bundle. One query for the products that are bundles,
     * one for all their items, one for the components, one more for the stock of the sale
     * unit: whatever the number of bundles.
     *
     * @param list<int> $bundleProductIds
     *
     * @return array<int, int> keyed by every product id asked; zero for a product that is not a bundle
     */
    public function forBundles(array $bundleProductIds): array
    {
        $bundleProductIds = array_values(array_unique(array_map('intval', $bundleProductIds)));
        $itemsByBundle = $this->bundleItemRepository->forBundles($this->bundleItemRepository->bundleProductIds($bundleProductIds));

        $saleElementsIds = [];

        foreach ($itemsByBundle as $items) {
            foreach ($items as $item) {
                $saleElementsIds[] = $item->getProductSaleElementsId();
            }
        }

        $components = $this->bundleComponentReader->read($saleElementsIds);
        $availability = array_fill_keys($bundleProductIds, 0);

        foreach ($itemsByBundle as $bundleProductId => $items) {
            $availability[$bundleProductId] = self::availabilityOf($items, BundleCompositionResolver::defaultComposition($items, $components), $components);
        }

        return $availability;
    }

    /**
     * Of a composition already validated by BundleCompositionResolver.
     */
    public function forComposition(int $bundleProductId, BundleComposition $composition): int
    {
        return $this->forCompositions([[$bundleProductId, $composition]])[0];
    }

    /**
     * Of several compositions already validated by BundleCompositionResolver. One query for the
     * products that are bundles, one for all their items, one for the components, one more for
     * the stock of the sale unit: whatever the number of compositions.
     *
     * @param array<int|string, array{0: int, 1: BundleComposition}> $compositions the bundle product and the composition, by a key of the caller's
     *
     * @return array<int|string, int> by the same keys; zero for a product that is not a bundle
     */
    public function forCompositions(array $compositions): array
    {
        if ([] === $compositions) {
            return [];
        }

        $itemsByBundle = $this->bundleItemRepository->forBundles(
            $this->bundleItemRepository->bundleProductIds(array_map(static fn (array $request): int => $request[0], array_values($compositions))),
        );

        $saleElementsIds = [];

        foreach ($itemsByBundle as $items) {
            foreach ($items as $item) {
                $saleElementsIds[] = $item->getProductSaleElementsId();
            }
        }

        $components = $this->bundleComponentReader->read($saleElementsIds);
        $availability = [];

        foreach ($compositions as $key => [$bundleProductId, $composition]) {
            $availability[$key] = isset($itemsByBundle[$bundleProductId])
                ? self::availabilityOf($itemsByBundle[$bundleProductId], $composition, $components)
                : 0;
        }

        return $availability;
    }

    /**
     * @param list<ProductBundleItem>     $items
     * @param array<int, BundleComponent> $components
     */
    private static function availabilityOf(array $items, BundleComposition $composition, array $components): int
    {
        $available = null;

        foreach ($items as $item) {
            $quantity = BundleDecimal::toMicro($composition->quantityOf((int) $item->getId()));

            if (0 === $quantity) {
                continue;
            }

            $saleElementsId = $item->getProductSaleElementsId();
            $component = null === $saleElementsId ? null : ($components[$saleElementsId] ?? null);

            if (null === $component || !$component->sellable) {
                return 0;
            }

            if ($component->virtual) {
                $available ??= self::UNLIMITED;

                continue;
            }

            $stock = (int) round(max(0.0, $component->availableQuantity) * BundleDecimal::SCALE);
            $available = min($available ?? self::UNLIMITED, intdiv($stock, $quantity));
        }

        return $available ?? 0;
    }
}
