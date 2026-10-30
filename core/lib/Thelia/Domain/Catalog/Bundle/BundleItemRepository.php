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
use Thelia\Model\ProductBundleItem;
use Thelia\Model\ProductBundleItemQuery;
use Thelia\Model\ProductQuery;

final readonly class BundleItemRepository
{
    /**
     * @param list<int> $bundleProductIds
     *
     * @return array<int, list<ProductBundleItem>> keyed by bundle product id, each list in display order; a bundle with no item is absent
     */
    public function forBundles(array $bundleProductIds): array
    {
        $bundleProductIds = array_values(array_unique(array_map('intval', $bundleProductIds)));

        if ([] === $bundleProductIds) {
            return [];
        }

        $items = [];

        foreach (ProductBundleItemQuery::create()
            ->filterByBundleProductId($bundleProductIds, Criteria::IN)
            ->orderByBundleProductId()
            ->orderByPosition()
            ->orderById()
            ->find() as $item) {
            $items[(int) $item->getBundleProductId()][] = $item;
        }

        return $items;
    }

    /**
     * @param list<int> $productIds
     *
     * @return list<int> the products that are bundles
     */
    public function bundleProductIds(array $productIds): array
    {
        if ([] === $productIds) {
            return [];
        }

        return array_map('intval', ProductQuery::create()
            ->filterById(array_values(array_unique($productIds)), Criteria::IN)
            ->filterByIsBundle(1)
            ->select('Id')
            ->find()
            ->toArray());
    }
}
