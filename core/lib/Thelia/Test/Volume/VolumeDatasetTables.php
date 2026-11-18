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

namespace Thelia\Test\Volume;

/**
 * The tables a volume dataset writes, children before parents, and how its rows are found in
 * each: by the identifier range of a table of the manifest, read in one of its columns. The
 * purge walks the list in this order; the fingerprint reads it too.
 */
final class VolumeDatasetTables
{
    /**
     * table => [column holding the identifier, table of the manifest range, columns of the sort, reference column or null].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string|null}>
     */
    public const TABLES = [
        'order_product_tax' => ['id', 'order_product_tax', 'id', null],
        'order_product' => ['id', 'order_product', 'id', 'product_ref'],
        'order' => ['id', 'order', 'id', 'ref'],
        'order_address' => ['id', 'order_address', 'id', null],
        'cart' => ['id', 'cart', 'id', null],
        'address' => ['id', 'address', 'id', null],
        'customer' => ['id', 'customer', 'id', 'ref'],
        'rewriting_url' => ['id', 'rewriting_url', 'id', null],
        'product_image_i18n' => ['id', 'product_image', 'id, locale', null],
        'product_image' => ['id', 'product_image', 'id', null],
        'feature_product' => ['id', 'feature_product', 'id', null],
        'attribute_combination' => ['product_sale_elements_id', 'product_sale_elements', 'product_sale_elements_id, attribute_id, attribute_av_id', null],
        'product_price' => ['product_sale_elements_id', 'product_sale_elements', 'product_sale_elements_id, currency_id', null],
        'product_sale_elements' => ['id', 'product_sale_elements', 'id', 'ref'],
        'product_category' => ['product_id', 'product', 'product_id, category_id', null],
        'product_i18n' => ['id', 'product', 'id, locale', null],
        'product' => ['id', 'product', 'id', 'ref'],
        'category_i18n' => ['id', 'category', 'id, locale', null],
        'category' => ['id', 'category', 'id', null],
        'feature_template' => ['id', 'feature_template', 'id', null],
        'attribute_template' => ['id', 'attribute_template', 'id', null],
        'feature_av_i18n' => ['id', 'feature_av', 'id, locale', null],
        'feature_av' => ['id', 'feature_av', 'id', null],
        'feature_i18n' => ['id', 'feature', 'id, locale', null],
        'feature' => ['id', 'feature', 'id', null],
        'attribute_av_i18n' => ['id', 'attribute_av', 'id, locale', null],
        'attribute_av' => ['id', 'attribute_av', 'id', null],
        'attribute_i18n' => ['id', 'attribute', 'id, locale', null],
        'attribute' => ['id', 'attribute', 'id', null],
        'template_i18n' => ['id', 'template', 'id, locale', null],
        'template' => ['id', 'template', 'id', null],
    ];

    /**
     * The WHERE clause and its parameters that select the dataset rows of a table, or null when
     * the manifest has no range for it.
     *
     * @return array{0: string, 1: list<int|string>}|null
     */
    public static function condition(VolumeDatasetManifest $manifest, string $table): ?array
    {
        [$column, $rangeTable, , $referenceColumn] = self::TABLES[$table];
        $range = $manifest->range($rangeTable);
        if (null === $range) {
            return null;
        }

        $sql = \sprintf('`%s` BETWEEN ? AND ?', $column);
        $parameters = [$range[0], $range[1]];
        if (null !== $referenceColumn) {
            $sql .= \sprintf(' AND `%s` LIKE ?', $referenceColumn);
            $parameters[] = VolumeDatasetManifest::REF_PREFIX.'%';
        }

        return [$sql, $parameters];
    }
}
