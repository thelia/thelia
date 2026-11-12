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

namespace Thelia\Domain\Catalog\Restriction;

/**
 * The SQL of the catalog restrictions, written once for the product rule and for the brands
 * (see {@see CatalogRestrictionRule} and {@see CatalogRestrictionResolver::applyToBrands()}), so
 * that a brand is open by the very rule that opens its products. The ids are integers read from
 * the database, written as they are; every table of a subquery carries its own alias.
 */
final class CatalogRestrictionClauses
{
    /**
     * True for a product left visible: an allow list becomes one parenthesised disjunction (a
     * product, a brand or a category opens it), each deny rule one more conjunct.
     */
    public static function visibleProduct(CatalogRestrictionScope $scope, string $productIdColumn): string
    {
        $conjuncts = [];

        if ($scope->hasAllowList()) {
            $opens = [];

            if ([] !== $scope->allowedProductIds) {
                $opens[] = \sprintf('%s IN (%s)', $productIdColumn, self::list($scope->allowedProductIds));
            }

            if ([] !== $scope->allowedBrandIds) {
                $opens[] = \sprintf(
                    'EXISTS (SELECT 1 FROM product allowed_brand_product WHERE allowed_brand_product.id = %s AND allowed_brand_product.brand_id IN (%s))',
                    $productIdColumn,
                    self::list($scope->allowedBrandIds),
                );
            }

            if ([] !== $scope->allowedCategoryIds) {
                $opens[] = \sprintf(
                    'EXISTS (SELECT 1 FROM product_category allowed_product_category WHERE allowed_product_category.product_id = %s AND allowed_product_category.category_id IN (%s))',
                    $productIdColumn,
                    self::list($scope->allowedCategoryIds),
                );
            }

            $conjuncts[] = \sprintf('(%s)', implode(' OR ', $opens));
        }

        if ([] !== $scope->deniedProductIds) {
            $conjuncts[] = \sprintf('%s NOT IN (%s)', $productIdColumn, self::list($scope->deniedProductIds));
        }

        if ([] !== $scope->deniedBrandIds) {
            $conjuncts[] = \sprintf(
                'NOT EXISTS (SELECT 1 FROM product denied_brand_product WHERE denied_brand_product.id = %s AND denied_brand_product.brand_id IN (%s))',
                $productIdColumn,
                self::list($scope->deniedBrandIds),
            );
        }

        if ([] !== $scope->deniedCategoryIds) {
            $conjuncts[] = \sprintf(
                'NOT EXISTS (SELECT 1 FROM product_category denied_product_category WHERE denied_product_category.product_id = %s AND denied_product_category.category_id IN (%s))',
                $productIdColumn,
                self::list($scope->deniedCategoryIds),
            );
        }

        return 1 === \count($conjuncts) ? $conjuncts[0] : \sprintf('(%s)', implode(' AND ', $conjuncts));
    }

    /**
     * True for a brand left visible, or null when the rules say nothing about brands. A denied
     * brand is closed. With an allow list, a brand is open when a rule lists it or when it
     * carries at least one product the customer may see (online, `visible = 1`, and left open by the
     * clause of {@see self::visibleProduct()}), so that the brand of an open product is never a dead link.
     */
    public static function visibleBrand(CatalogRestrictionScope $scope, string $brandIdColumn): ?string
    {
        $conjuncts = [];

        if ([] !== $scope->deniedBrandIds) {
            $conjuncts[] = \sprintf('%s NOT IN (%s)', $brandIdColumn, self::list($scope->deniedBrandIds));
        }

        if ($scope->hasAllowList()) {
            $opens = [];

            if ([] !== $scope->allowedBrandIds) {
                $opens[] = \sprintf('%s IN (%s)', $brandIdColumn, self::list($scope->allowedBrandIds));
            }

            $opens[] = \sprintf(
                'EXISTS (SELECT 1 FROM product open_brand_product WHERE open_brand_product.brand_id = %s AND open_brand_product.visible = 1 AND %s)',
                $brandIdColumn,
                self::visibleProduct($scope, 'open_brand_product.id'),
            );

            $conjuncts[] = \sprintf('(%s)', implode(' OR ', $opens));
        }

        if ([] === $conjuncts) {
            return null;
        }

        return 1 === \count($conjuncts) ? $conjuncts[0] : \sprintf('(%s)', implode(' AND ', $conjuncts));
    }

    /**
     * @param list<int> $ids
     */
    private static function list(array $ids): string
    {
        return implode(', ', array_map('intval', $ids));
    }
}
