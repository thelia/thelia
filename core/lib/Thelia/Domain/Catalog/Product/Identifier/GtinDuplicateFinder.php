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

namespace Thelia\Domain\Catalog\Product\Identifier;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Finds the combinations that share a GTIN.
 *
 * A duplicate is reported, never refused: it is nearly always a typing mistake, but two
 * combinations of the same physical item legitimately carry the same code. There is no
 * unique index for the same reason; the plain index on `ean_code` keeps these lookups off
 * a full scan.
 */
final readonly class GtinDuplicateFinder
{
    /**
     * The other combinations carrying the code of the given one, empty when it has none.
     *
     * @return list<GtinSharer>
     */
    public function sharersOf(int $productSaleElementsId): array
    {
        return $this->sharersAmong([$productSaleElementsId])[$productSaleElementsId] ?? [];
    }

    /**
     * For each given combination that carries a code, the other combinations carrying it
     * too. Two queries whatever the number of combinations, so a grid of combinations or
     * a feed batch asks once.
     *
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, list<GtinSharer>> keyed by combination id, combinations without a duplicate left out
     */
    public function sharersAmong(array $productSaleElementsIds): array
    {
        if ([] === $productSaleElementsIds) {
            return [];
        }

        $codeById = [];

        $coded = ProductSaleElementsQuery::create()
            ->filterById($productSaleElementsIds, Criteria::IN)
            ->filterByEanCode('', Criteria::NOT_EQUAL)
            ->select([ProductSaleElementsTableMap::COL_ID, ProductSaleElementsTableMap::COL_EAN_CODE])
            ->find();

        foreach ($coded as $row) {
            $codeById[(int) $row[ProductSaleElementsTableMap::COL_ID]] = (string) $row[ProductSaleElementsTableMap::COL_EAN_CODE];
        }

        if ([] === $codeById) {
            return [];
        }

        $carriersByCode = [];

        $carriers = ProductSaleElementsQuery::create()
            ->filterByEanCode(array_values(array_unique($codeById)), Criteria::IN)
            ->useProductQuery()
            ->endUse()
            ->select([
                ProductSaleElementsTableMap::COL_ID,
                ProductSaleElementsTableMap::COL_REF,
                ProductSaleElementsTableMap::COL_EAN_CODE,
                ProductSaleElementsTableMap::COL_PRODUCT_ID,
                ProductTableMap::COL_REF,
            ])
            ->orderById()
            ->find();

        foreach ($carriers as $carrier) {
            $carriersByCode[(string) $carrier[ProductSaleElementsTableMap::COL_EAN_CODE]][] = new GtinSharer(
                (int) $carrier[ProductSaleElementsTableMap::COL_ID],
                (string) $carrier[ProductSaleElementsTableMap::COL_REF],
                (int) $carrier[ProductSaleElementsTableMap::COL_PRODUCT_ID],
                (string) $carrier[ProductTableMap::COL_REF],
            );
        }

        $sharersById = [];

        foreach ($codeById as $id => $code) {
            $sharers = array_values(array_filter(
                $carriersByCode[$code] ?? [],
                static fn (GtinSharer $sharer): bool => $sharer->productSaleElementsId !== $id,
            ));

            if ([] !== $sharers) {
                $sharersById[$id] = $sharers;
            }
        }

        return $sharersById;
    }
}
