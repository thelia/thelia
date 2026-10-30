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

use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;

/**
 * The stock a catalog reader shows for a bundle: the number of bundles its components allow
 * (default composition), never the quantity of the sale element that stands for the bundle,
 * which stays at zero. The one place that answers it for the loops, the front API and the
 * data access of the themes.
 *
 * A bundle of virtual components only is unlimited; a quantity shown has to fit an integer
 * field, so the answer is capped at DISPLAY_LIMIT.
 *
 * A shop without a bundle answers nothing and asks nothing of the database; one that has some
 * asks once per call, whatever the number of products.
 */
final readonly class BundleDisplayedStock
{
    public const DISPLAY_LIMIT = 2147483647;

    public function __construct(
        private BundleCatalogState $bundleCatalogState,
        private BundleItemRepository $bundleItemRepository,
        private BundleAvailability $bundleAvailability,
    ) {
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, int> keyed by product id, the bundles among the products only: a product
     *                         that is not a bundle is absent, so the reader keeps its own quantity
     */
    public function forProducts(array $productIds): array
    {
        if (!$this->bundleCatalogState->hasBundles()) {
            return [];
        }

        $bundleProductIds = $this->bundleItemRepository->bundleProductIds(array_values(array_unique(array_map('intval', $productIds))));

        if ([] === $bundleProductIds) {
            return [];
        }

        $displayed = [];

        foreach ($this->bundleAvailability->forBundles($bundleProductIds) as $bundleProductId => $available) {
            $displayed[$bundleProductId] = min($available, self::DISPLAY_LIMIT);
        }

        return $displayed;
    }

    /**
     * The same, for the models a page of a loop holds: products, or sale elements (by their
     * product). Anything else is none of its business.
     *
     * @param iterable<object> $models
     *
     * @return array<int, int> keyed by product id, the bundles only
     */
    public function forModels(iterable $models): array
    {
        if (!$this->bundleCatalogState->hasBundles()) {
            return [];
        }

        $productIds = [];

        foreach ($models as $model) {
            if ($model instanceof Product) {
                $productIds[] = (int) $model->getId();
            } elseif ($model instanceof ProductSaleElements) {
                $productIds[] = (int) $model->getProductId();
            }
        }

        return $this->forProducts($productIds);
    }
}
