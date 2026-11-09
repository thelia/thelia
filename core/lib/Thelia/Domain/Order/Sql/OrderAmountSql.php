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

namespace Thelia\Domain\Order\Sql;

use Propel\Runtime\Connection\StatementInterface;
use Thelia\Model\ConfigQuery;

/**
 * The amounts of an order written in SQL, for the queries that total many orders at once:
 * Thelia\Model\Order::getTotalAmount() is the reference every expression mirrors, and a
 * query that disagrees with it states an amount the customer was never charged.
 *
 * The rule an order was charged with depends on when it was placed: an order frozen by the
 * 2.4 upgrade was totalled without any rounding, an order placed before a shop switched to
 * rounding of sums keeps the historical rule, and the rest follow the shop. Whatever the
 * rule, an order placed while order_product.promo_price was a text keeps the total it was
 * invoiced with. The three order ids those rules turn on are named parameters: a statement
 * built on these expressions binds them with bindRoundingPivots().
 */
final class OrderAmountSql
{
    public const string LEGACY_ROUNDING_PIVOT = 'legacy_rounding_pivot';
    public const string SUM_OF_ROUNDINGS_PIVOT = 'sum_of_roundings_pivot';
    public const string TEXT_PROMO_PRICE_PIVOT = 'text_promo_price_pivot';

    /**
     * Orders placed before Thelia 2.4 were totalled without any rounding at all.
     * ConfigQuery has no constant for it: it is not a mode a shop can be set to,
     * only a state some rows are frozen in.
     */
    private const int ROUNDING_MODE_LEGACY = 0;

    /**
     * The total of one order line, with or without its taxes, rounded by the rule of its
     * order. The expression is a floating point number for the lines of the frozen orders:
     * a caller that sums it casts the SUM to a DECIMAL, not each line, because a CASE takes
     * the type of its widest branch and would turn a cast line back into a DOUBLE.
     *
     * @param string $orderProductAlias the order_product table or its alias in the statement
     * @param string $orderIdColumn     the column holding the id of the order of the line, quoted when it needs it
     */
    public static function lineTotal(string $orderProductAlias, string $orderIdColumn, bool $taxed): string
    {
        return '
                        CASE
                            WHEN '.$orderIdColumn.' <= :'.self::LEGACY_ROUNDING_PIVOT.' THEN '.self::lineTotalOfOrder($orderProductAlias, $orderIdColumn, self::ROUNDING_MODE_LEGACY, $taxed).'
                            WHEN '.$orderIdColumn.' <= :'.self::SUM_OF_ROUNDINGS_PIVOT.' THEN '.self::lineTotalOfOrder($orderProductAlias, $orderIdColumn, ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS, $taxed).'
                            ELSE '.self::lineTotalOfOrder($orderProductAlias, $orderIdColumn, ConfigQuery::getOrderRoundingMode(), $taxed).'
                        END';
    }

    public static function lineTaxedTotal(string $orderProductAlias, string $orderIdColumn): string
    {
        return self::lineTotal($orderProductAlias, $orderIdColumn, true);
    }

    /**
     * The goods of an order with their taxes, before discount and postage: the sum of its
     * taxed lines, 0 for an order without any line.
     *
     * @param string $orderAlias the `order` table or its alias in the statement
     */
    public static function itemsTaxedTotal(string $orderAlias): string
    {
        return '(
                    SELECT CAST(COALESCE(SUM('.self::lineTaxedTotal('items_line', 'items_line.order_id').'), 0) AS DECIMAL(16,6))
                    FROM order_product AS items_line
                    WHERE items_line.order_id = '.$orderAlias.'.id
                )';
    }

    /**
     * The discount the order was charged with: never more than its goods, since the total of
     * an order does not go below its postage.
     */
    public static function appliedDiscount(string $orderAlias): string
    {
        return 'LEAST('.$orderAlias.'.discount, '.self::itemsTaxedTotal($orderAlias).')';
    }

    /**
     * What the customer paid for the order: its taxed goods, less the discount, plus the postage.
     */
    public static function orderTotal(string $orderAlias): string
    {
        return '('.self::itemsTaxedTotal($orderAlias).' - '.self::appliedDiscount($orderAlias).' + '.$orderAlias.'.postage)';
    }

    public static function bindRoundingPivots(StatementInterface $statement): void
    {
        $statement->bindValue(self::LEGACY_ROUNDING_PIVOT, (int) ConfigQuery::read('last_legacy_rounding_order_id', 0), \PDO::PARAM_INT);
        $statement->bindValue(self::SUM_OF_ROUNDINGS_PIVOT, (int) ConfigQuery::read('last_sum_of_roundings_order_id', 0), \PDO::PARAM_INT);
        $statement->bindValue(self::TEXT_PROMO_PRICE_PIVOT, (int) ConfigQuery::read('last_text_promo_price_order_id', 0), \PDO::PARAM_INT);
    }

    /**
     * Chooses, line by line, how the promotional price is read. The choice is made on the line and not on the
     * price operand: an IF takes the type of its operands, so a text read for the frozen orders would make the
     * unit price of every order a text.
     */
    private static function lineTotalOfOrder(string $orderProductAlias, string $orderIdColumn, int $roundingMode, bool $taxed): string
    {
        return 'CASE
                                WHEN '.$orderIdColumn.' <= :'.self::TEXT_PROMO_PRICE_PIVOT.' THEN '.self::roundedLineTotal($orderProductAlias, $roundingMode, $taxed, self::unitPriceAsText($orderProductAlias)).'
                                ELSE '.self::roundedLineTotal($orderProductAlias, $roundingMode, $taxed, self::unitPrice($orderProductAlias)).'
                            END';
    }

    /**
     * One line total, rounded where the given mode says to round: on the unit
     * amounts under sum of roundings, on the line total under rounding of sums,
     * nowhere at all for an order frozen by the 2.4 upgrade.
     */
    private static function roundedLineTotal(string $orderProductAlias, int $roundingMode, bool $taxed, string $unitPrice): string
    {
        $roundUnitAmounts = ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS === $roundingMode;

        $unitAmount = $roundUnitAmounts ? 'ROUND('.$unitPrice.', 2)' : $unitPrice;

        if ($taxed) {
            $unitTax = 'IF('.$orderProductAlias.'.was_in_promo, order_product_tax.promo_amount, order_product_tax.amount)';
            $unitTax = $roundUnitAmounts ? 'ROUND('.$unitTax.', 2)' : $unitTax;

            $unitAmount = '('.$unitAmount.' + (
                                SELECT COALESCE(SUM('.$unitTax.'), 0)
                                FROM order_product_tax
                                WHERE order_product_tax.order_product_id = '.$orderProductAlias.'.id
                            ))';
        }

        $lineTotal = $orderProductAlias.'.quantity * '.$unitAmount;

        return ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS === $roundingMode
            ? 'ROUND('.$lineTotal.', 2)'
            : $lineTotal;
    }

    private static function unitPrice(string $orderProductAlias): string
    {
        return 'IF('.$orderProductAlias.'.was_in_promo = 1, '.$orderProductAlias.'.promo_price, '.$orderProductAlias.'.price)';
    }

    /**
     * The same unit price with the promotional price read as a text, the way it was read
     * while order_product.promo_price was a VARCHAR: the type of IF() is the one of its
     * operands, so the price of a line that is not on promotion is read as text too.
     */
    private static function unitPriceAsText(string $orderProductAlias): string
    {
        return 'IF('.$orderProductAlias.'.was_in_promo = 1, COALESCE(CAST('.$orderProductAlias.'.promo_price AS CHAR), \'\'), '.$orderProductAlias.'.price)';
    }
}
