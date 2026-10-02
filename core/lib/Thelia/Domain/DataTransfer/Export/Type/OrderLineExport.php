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

namespace Thelia\Domain\DataTransfer\Export\Type;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Propel;
use Thelia\Domain\DataTransfer\Export\SpreadsheetFormulaGuard;

/**
 * One row per order line, with the codes of the item as they were when it was sold.
 *
 * The full order export is one row per order and has no place for them. Everything is
 * read from the order line, never from the catalogue: a GTIN or a part number corrected
 * since does not rewrite what was sold. Bounded by the same optional dates as the order
 * export.
 */
class OrderLineExport extends OrderExport
{
    public const FILE_NAME = 'order_line';
    public const USE_RANGE_DATE = true;

    private const UNIT_PRICE = 'IF(order_product.was_in_promo = 1, order_product.promo_price, order_product.price)';

    protected array $orderAndAliases = [
        'order_ref' => 'order_ref',
        'order_created_at' => 'date',
        'order_invoice_ref' => 'invoice_ref',
        'order_product_line_type' => 'line_type',
        'order_product_product_ref' => 'product_ref',
        'order_product_product_sale_elements_ref' => 'combination_ref',
        'order_product_title' => 'title',
        'order_product_ean_code' => 'gtin',
        'order_product_mpn' => 'mpn',
        'order_product_quantity' => 'quantity',
        'order_product_unit_price' => 'unit_price_excluding_taxes',
        'currency_code' => 'currency',
    ];

    public function beforeSerialize(array $data): array
    {
        return SpreadsheetFormulaGuard::neutralizeColumns(
            parent::beforeSerialize($data),
            ['order_product_ean_code', 'order_product_mpn'],
        );
    }

    protected function getData(): array|string|ModelCriteria
    {
        $con = Propel::getConnection();

        $query = '
            SELECT
                `order`.ref as "order_ref",
                `order`.created_at as "order_created_at",
                `order`.invoice_ref as "order_invoice_ref",
                order_product.line_type as "order_product_line_type",
                order_product.product_ref as "order_product_product_ref",
                order_product.product_sale_elements_ref as "order_product_product_sale_elements_ref",
                order_product.title as "order_product_title",
                order_product.ean_code as "order_product_ean_code",
                order_product.mpn as "order_product_mpn",
                order_product.quantity as "order_product_quantity",
                ROUND('.self::UNIT_PRICE.', 2) as "order_product_unit_price",
                currency.code as "currency_code"
            FROM order_product
            INNER JOIN `order` ON `order`.id = order_product.order_id
            LEFT JOIN currency ON currency.id = `order`.currency_id
            '.$this->buildDateRangeCondition().'
            ORDER BY `order`.created_at DESC, `order`.id DESC, order_product.id ASC
        ';

        $stmt = $con->prepare($query);

        foreach ($this->getDateRangeBounds() as $bound => $date) {
            $stmt->bindValue($bound, $date->format('Y-m-d H:i:s'));
        }

        $stmt->execute();

        return $this->getDataJsonCache($stmt, self::FILE_NAME);
    }
}
