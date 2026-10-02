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
 * Reads the GTIN, the manufacturer part number and the manufacturer brand of a batch of
 * combinations, for the modules that build merchant feeds: one query per batch, so a
 * feed does not ask once per combination nor read the table itself.
 */
final readonly class ProductIdentifierReader
{
    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, ProductIdentifiers> keyed by combination id, unknown ids left out
     */
    public function forSaleElements(array $productSaleElementsIds): array
    {
        if ([] === $productSaleElementsIds) {
            return [];
        }

        $rows = ProductSaleElementsQuery::create()
            ->filterById($productSaleElementsIds, Criteria::IN)
            ->useProductQuery()
            ->endUse()
            ->select([
                ProductSaleElementsTableMap::COL_ID,
                ProductSaleElementsTableMap::COL_EAN_CODE,
                ProductSaleElementsTableMap::COL_MPN,
                ProductSaleElementsTableMap::COL_MANUFACTURER_BRAND_ID,
                ProductTableMap::COL_BRAND_ID,
            ])
            ->find();

        $identifiers = [];

        foreach ($rows as $row) {
            $id = (int) $row[ProductSaleElementsTableMap::COL_ID];
            $brandId = $row[ProductSaleElementsTableMap::COL_MANUFACTURER_BRAND_ID] ?? $row[ProductTableMap::COL_BRAND_ID];

            $identifiers[$id] = new ProductIdentifiers(
                $id,
                self::nullIfEmpty($row[ProductSaleElementsTableMap::COL_EAN_CODE]),
                self::nullIfEmpty($row[ProductSaleElementsTableMap::COL_MPN]),
                null === $brandId ? null : (int) $brandId,
            );
        }

        return $identifiers;
    }

    private static function nullIfEmpty(mixed $value): ?string
    {
        return null === $value || '' === $value ? null : (string) $value;
    }
}
