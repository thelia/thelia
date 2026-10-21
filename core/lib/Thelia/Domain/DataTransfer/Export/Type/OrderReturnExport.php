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
use Thelia\Domain\DataTransfer\Export\JsonFileAbstractExport;
use Thelia\Domain\DataTransfer\Export\SpreadsheetFormulaGuard;
use Thelia\Model\OrderReturnStatus;

/**
 * One row per returned line, the latest returns first, bounded by the optional dates of
 * the export screen on the day the return was opened.
 *
 * The reason is the title the customer picked, as it was when the return was opened
 * (the snapshot kept on the return, never a title renamed since), or their own words
 * when they picked none; the comment column holds their words only when a title took
 * the reason column. The combination is the attribute values of the
 * order line as they were sold. The type is the code of the expected resolution, and
 * "settled" is 1 once the effective status of the return is settled.
 */
class OrderReturnExport extends JsonFileAbstractExport
{
    public const FILE_NAME = 'order_return';
    public const USE_RANGE_DATE = true;

    protected array $orderAndAliases = [
        'return_date' => 'Date',
        'order_ref' => 'Order',
        'product_title' => 'Product',
        'combination' => 'Combination values',
        'combination_ref' => 'Ref',
        'reason' => 'Reason',
        'expected_resolution' => 'Type',
        'expected_exchange' => 'Expected exchange',
        'settled' => 'Settled',
        'comment' => 'Comment',
    ];

    public function beforeSerialize(array $data): array
    {
        // Free texts, typed by the customer or the merchant: a value that starts like a
        // formula is not run by a spreadsheet.
        return SpreadsheetFormulaGuard::neutralizeColumns(
            parent::beforeSerialize($data),
            ['product_title', 'combination', 'reason', 'expected_exchange', 'comment'],
        );
    }

    protected function getData(): array|string|ModelCriteria
    {
        $statement = Propel::getConnection()->prepare('
            SELECT
                order_return.created_at AS "return_date",
                `order`.ref AS "order_ref",
                order_product.title AS "product_title",
                (
                    SELECT GROUP_CONCAT(combination.attribute_av_title ORDER BY combination.id SEPARATOR " / ")
                    FROM order_product_attribute_combination combination
                    WHERE combination.order_product_id = order_product.id
                ) AS "combination",
                order_product.product_sale_elements_ref AS "combination_ref",
                COALESCE(NULLIF(order_return.reason_title, \'\'), order_return.customer_comment) AS "reason",
                order_return.expected_resolution AS "expected_resolution",
                order_return.expected_exchange AS "expected_exchange",
                IF(
                    IF(order_return_status.protected_status = 1, order_return_status.code, COALESCE(NULLIF(order_return_status.equivalent_code, \'\'), order_return_status.code)) = :settled,
                    1,
                    0
                ) AS "settled",
                IF(NULLIF(order_return.reason_title, \'\') IS NULL, NULL, order_return.customer_comment) AS "comment"
            FROM order_return_line
            INNER JOIN order_return ON order_return.id = order_return_line.order_return_id
            INNER JOIN order_return_status ON order_return_status.id = order_return.status_id
            INNER JOIN order_product ON order_product.id = order_return_line.order_product_id
            INNER JOIN `order` ON `order`.id = order_return.order_id
            '.$this->buildDateRangeCondition().'
            ORDER BY order_return.created_at DESC, order_return.id DESC, order_return_line.id ASC
        ');
        $statement->bindValue('settled', OrderReturnStatus::CODE_SETTLED);

        foreach ($this->getDateRangeBounds() as $bound => $date) {
            $statement->bindValue($bound, $date->format('Y-m-d H:i:s'));
        }

        $statement->execute();

        return $this->getDataJsonCache($statement, self::FILE_NAME);
    }

    /**
     * @return array<string, \DateTimeInterface>
     */
    private function getDateRangeBounds(): array
    {
        $bounds = [];

        foreach (['start', 'end'] as $bound) {
            if (($this->rangeDate[$bound] ?? null) instanceof \DateTimeInterface) {
                $bounds[$bound] = $this->rangeDate[$bound];
            }
        }

        return $bounds;
    }

    private function buildDateRangeCondition(): string
    {
        $comparisons = ['start' => '>=', 'end' => '<='];
        $conditions = [];

        foreach (array_keys($this->getDateRangeBounds()) as $bound) {
            $conditions[] = 'order_return.created_at '.$comparisons[$bound].' :'.$bound;
        }

        return [] === $conditions ? '' : 'WHERE '.implode(' AND ', $conditions);
    }
}
