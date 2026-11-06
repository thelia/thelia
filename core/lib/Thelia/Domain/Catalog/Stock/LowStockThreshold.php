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

namespace Thelia\Domain\Catalog\Stock;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * The stock level at which a sale element is reported as running low: its own
 * threshold when it has one, the shop-wide one otherwise.
 */
final readonly class LowStockThreshold
{
    public function __construct(private StockSettings $settings)
    {
    }

    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, float> the effective threshold of each known sale element, in a single query
     */
    public function forMany(array $productSaleElementsIds): array
    {
        if ([] === $productSaleElementsIds) {
            return [];
        }

        $global = $this->settings->lowStockThreshold();
        $thresholds = [];

        $rows = ProductSaleElementsQuery::create()
            ->filterById($productSaleElementsIds, Criteria::IN)
            ->select(['Id', 'LowStockThreshold'])
            ->find();

        foreach ($rows as $row) {
            $own = $row['LowStockThreshold'];
            $thresholds[(int) $row['Id']] = null === $own || '' === $own ? $global : (float) $own;
        }

        return $thresholds;
    }

    public function forOne(int $productSaleElementsId): float
    {
        return $this->forMany([$productSaleElementsId])[$productSaleElementsId] ?? $this->settings->lowStockThreshold();
    }
}
