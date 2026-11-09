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
use Thelia\Domain\Catalog\Quantity\QuantityScale;
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
 * invoiced with, and an order placed before the line totals were rounded as decimals keeps
 * its floating point line totals. The four order ids those rules turn on are named
 * parameters: a statement built on these expressions binds them with bindRoundingPivots().
 */
final class OrderAmountSql
{
    public const string LEGACY_ROUNDING_PIVOT = 'legacy_rounding_pivot';
    public const string SUM_OF_ROUNDINGS_PIVOT = 'sum_of_roundings_pivot';
    public const string TEXT_PROMO_PRICE_PIVOT = 'text_promo_price_pivot';
    public const string FLOAT_LINE_ROUNDING_PIVOT = 'float_line_rounding_pivot';

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
     * The lines of one order totalled the way it was charged, as a DECIMAL: past the 2.4
     * upgrade, the sum of the order is rounded to the cent (a line of 1.25 kg at 14.82 is
     * 18.525, charged 18.53), brought to six decimals first as MoneyRounding::toTheCent()
     * does, or 18.524999… would be rounded down. An aggregate over the lines of a single
     * order: the statement groups by order, or reads the lines of one order only.
     *
     * @param string $orderIdColumn the id of the order, grouped on or bound from outside the aggregate
     */
    public static function orderLinesTotal(string $orderProductAlias, string $orderIdColumn, bool $taxed): string
    {
        return 'CAST(CASE
                        WHEN '.$orderIdColumn.' <= :'.self::LEGACY_ROUNDING_PIVOT.' THEN SUM('.self::lineTotalOfOrder($orderProductAlias, $orderIdColumn, self::ROUNDING_MODE_LEGACY, $taxed).')
                        ELSE ROUND(CAST(SUM(
                            CASE
                                WHEN '.$orderIdColumn.' <= :'.self::SUM_OF_ROUNDINGS_PIVOT.' THEN '.self::lineTotalOfOrder($orderProductAlias, $orderIdColumn, ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS, $taxed).'
                                ELSE '.self::lineTotalOfOrder($orderProductAlias, $orderIdColumn, ConfigQuery::getOrderRoundingMode(), $taxed).'
                            END
                        ) AS DECIMAL(20,6)), 2)
                    END AS DECIMAL(16,6))';
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
                    SELECT COALESCE('.self::orderLinesTotal('items_line', $orderAlias.'.id', true).', 0)
                    FROM order_product AS items_line
                    WHERE items_line.order_id = '.$orderAlias.'.id
                )';
    }

    /**
     * The discount the order was charged with: never more than its goods, since the total of
     * an order does not go below its postage.
     *
     * @param string|null $itemsTaxedTotal the taxed goods of the order when the statement already
     *                                     computed them, so that they are not computed again
     */
    public static function appliedDiscount(string $orderAlias, ?string $itemsTaxedTotal = null): string
    {
        return 'LEAST(COALESCE('.$orderAlias.'.discount, 0), '.($itemsTaxedTotal ?? self::itemsTaxedTotal($orderAlias)).')';
    }

    /**
     * What the customer paid for the order: its taxed goods, less the discount, plus the postage.
     *
     * @param string|null $itemsTaxedTotal the taxed goods of the order when the statement already
     *                                     computed them, so that they are not computed again
     */
    public static function orderTotal(string $orderAlias, ?string $itemsTaxedTotal = null): string
    {
        $itemsTaxedTotal ??= self::itemsTaxedTotal($orderAlias);

        return '('.$itemsTaxedTotal.' - '.self::appliedDiscount($orderAlias, $itemsTaxedTotal).' + '.$orderAlias.'.postage)';
    }

    public static function bindRoundingPivots(StatementInterface $statement): void
    {
        $statement->bindValue(self::LEGACY_ROUNDING_PIVOT, (int) ConfigQuery::read('last_legacy_rounding_order_id', 0), \PDO::PARAM_INT);
        $statement->bindValue(self::SUM_OF_ROUNDINGS_PIVOT, (int) ConfigQuery::read('last_sum_of_roundings_order_id', 0), \PDO::PARAM_INT);
        $statement->bindValue(self::TEXT_PROMO_PRICE_PIVOT, (int) ConfigQuery::read('last_text_promo_price_order_id', 0), \PDO::PARAM_INT);
        $statement->bindValue(self::FLOAT_LINE_ROUNDING_PIVOT, (int) ConfigQuery::read('last_float_line_rounding_order_id', 0), \PDO::PARAM_INT);
    }

    /**
     * Chooses, line by line, how the promotional price is read. The choice is made on the line and not on the
     * price operand: an IF takes the type of its operands, so a text read for the frozen orders would make the
     * unit price of every order a text.
     */
    private static function lineTotalOfOrder(string $orderProductAlias, string $orderIdColumn, int $roundingMode, bool $taxed): string
    {
        return 'CASE
                                WHEN '.$orderIdColumn.' <= :'.self::TEXT_PROMO_PRICE_PIVOT.' THEN '.self::roundedLineTotal($orderProductAlias, $orderIdColumn, $roundingMode, $taxed, true).'
                                ELSE '.self::roundedLineTotal($orderProductAlias, $orderIdColumn, $roundingMode, $taxed, false).'
                            END';
    }

    /**
     * One line total, rounded where the given mode says to round: on the unit
     * amounts under sum of roundings, on the line total under rounding of sums,
     * nowhere at all for an order frozen by the 2.4 upgrade.
     */
    private static function roundedLineTotal(string $orderProductAlias, string $orderIdColumn, int $roundingMode, bool $taxed, bool $textPromoPrice): string
    {
        $unitPrice = $textPromoPrice ? self::unitPriceAsText($orderProductAlias) : self::unitPrice($orderProductAlias);
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

        $lineTotal = self::soldQuantity($orderProductAlias).' * '.$unitAmount;

        if (ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS !== $roundingMode) {
            return $lineTotal;
        }

        // A DOUBLE line total is rounded as a decimal (18.524999… is 18.53), as
        // Order::getTotalAmount() rounds it, except on a frozen line or on an order placed
        // before the line totals were rounded so: their order was invoiced on the floating
        // point figure.
        if ($textPromoPrice) {
            return 'ROUND('.$lineTotal.', 2)';
        }

        return 'CASE
                                WHEN '.$orderIdColumn.' <= :'.self::FLOAT_LINE_ROUNDING_PIVOT.' THEN ROUND('.$lineTotal.', 2)
                                ELSE ROUND(CAST('.$lineTotal.' AS DECIMAL(20,6)), 2)
                            END';
    }

    /**
     * The quantity of a line as it was sold: the column is a binary floating point number
     * (0.7 kg read back as 0.69999998), and a quantity carries three decimals at most. See
     * Thelia\Model\Order::storedQuantity().
     */
    public static function soldQuantity(string $orderProductAlias): string
    {
        return 'ROUND('.$orderProductAlias.'.quantity, '.QuantityScale::MAXIMUM_DECIMALS.')';
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
