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
use Thelia\Model\SaleUnitProductSaleElementsQuery;

/**
 * The stock level at which a sale element is reported as running low: its own
 * threshold when it has one, the shop-wide one otherwise. The own stock of a sale
 * unit runs low on the threshold of the unit, then on the one of the sale element,
 * then on the shop-wide one.
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

    /**
     * @param list<array{0: int, 1: int}> $stocks sale unit and sale element
     *
     * @return array<string, float> the effective threshold of each known stock of a sale unit, keyed
     *                              "<sale unit id>:<sale element id>", in a single query
     */
    public function forManySaleUnits(array $stocks): array
    {
        if ([] === $stocks) {
            return [];
        }

        $global = $this->settings->lowStockThreshold();
        $thresholds = [];

        $rows = SaleUnitProductSaleElementsQuery::create()
            ->filterBySaleUnitId(array_values(array_unique(array_column($stocks, 0))), Criteria::IN)
            ->filterByProductSaleElementsId(array_values(array_unique(array_column($stocks, 1))), Criteria::IN)
            ->joinProductSaleElements()
            ->withColumn('ProductSaleElements.LowStockThreshold', 'SaleElementThreshold')
            ->select(['SaleUnitId', 'ProductSaleElementsId', 'LowStockThreshold', 'SaleElementThreshold'])
            ->find();

        foreach ($rows as $row) {
            $threshold = $global;

            foreach ([$row['LowStockThreshold'], $row['SaleElementThreshold']] as $own) {
                if (null !== $own && '' !== $own) {
                    $threshold = (float) $own;

                    break;
                }
            }

            $thresholds[$row['SaleUnitId'].':'.$row['ProductSaleElementsId']] = $threshold;
        }

        return $thresholds;
    }

    public function forSaleUnit(int $saleUnitId, int $productSaleElementsId): float
    {
        return $this->forManySaleUnits([[$saleUnitId, $productSaleElementsId]])[$saleUnitId.':'.$productSaleElementsId]
            ?? $this->forOne($productSaleElementsId);
    }
}
