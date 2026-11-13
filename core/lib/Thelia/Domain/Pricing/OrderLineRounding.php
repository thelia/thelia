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

namespace Thelia\Domain\Pricing;

use Thelia\Domain\Catalog\Quantity\QuantityScale;
use Thelia\Model\ConfigQuery;

/**
 * How the lines of an order were totalled when it was invoiced, in SQL for the totals read
 * from the database and in PHP for the amounts computed line by line: the order total, the
 * export of the orders, the sales journal and the refunds all read their lines through it, so
 * that a full refund gives back, to the cent, the goods the order charged.
 *
 * - Before 2.4 (last_legacy_rounding_order_id), nothing was rounded, not even the quantity.
 * - Under the sum of roundings, each unit amount is rounded to the cent before the quantity.
 * - Under the rounding of sums, only the line total is rounded to the cent.
 *
 * Two pivots keep, on the orders placed before them, the floating point arithmetic they were
 * invoiced with: a promotional price stored as a text (last_text_promo_price_order_id), and a
 * line total rounded as a DOUBLE (last_float_line_rounding_order_id). MariaDB rounds a DOUBLE
 * half to even (ROUND(0.125e0, 2) is 0.12) and a DECIMAL half away from zero: the PHP side
 * follows the same choice.
 */
final readonly class OrderLineRounding
{
    private const UNIT_PRICE = 'IF(order_product.was_in_promo = 1, order_product.promo_price, order_product.price)';

    /**
     * An IF takes the type of its operands: a text promotional price makes the unit price of
     * every line of the order a text, the price of a line that is not on promotion included.
     */
    private const UNIT_PRICE_AS_TEXT = 'IF(order_product.was_in_promo = 1, COALESCE(CAST(order_product.promo_price AS CHAR), \'\'), order_product.price)';

    private const UNIT_TAX = 'IF(order_product.was_in_promo = 1, order_product_tax.promo_amount, order_product_tax.amount)';

    private const STORED_DECIMALS = 6;

    private function __construct(
        private bool $legacy,
        private bool $roundingOfSums,
        private bool $textPromoPrice,
        private bool $floatLineRounding,
    ) {
    }

    public static function ofOrder(int $orderId): self
    {
        if (ConfigQuery::isOrderWithLegacyRounding($orderId)) {
            return self::legacy(ConfigQuery::isOrderWithTextPromoPrice($orderId));
        }

        return self::inMode(
            ConfigQuery::getOrderRoundingMode($orderId),
            ConfigQuery::isOrderWithTextPromoPrice($orderId),
            ConfigQuery::isOrderWithFloatLineRounding($orderId),
        );
    }

    public static function legacy(bool $textPromoPrice): self
    {
        return new self(true, false, $textPromoPrice, true);
    }

    public static function inMode(int $roundingMode, bool $textPromoPrice, bool $floatLineRounding): self
    {
        return new self(false, ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS === $roundingMode, $textPromoPrice, $floatLineRounding);
    }

    /**
     * The price of one unit, its taxes included, as the order charged it before the quantity.
     *
     * @param list<float> $unitTaxes
     */
    public function unitAmount(float $unitPrice, array $unitTaxes): float
    {
        if ($this->legacy || $this->roundingOfSums) {
            return $this->textPromoPrice
                ? $unitPrice + self::asStored(array_sum($unitTaxes))
                : self::asStored($unitPrice + array_sum($unitTaxes));
        }

        $unitTax = self::asStored(array_sum(array_map(MoneyRounding::toTheCent(...), $unitTaxes)));

        return $this->textPromoPrice
            ? self::roundAsDouble($unitPrice, 2) + $unitTax
            : self::asStored(MoneyRounding::toTheCent($unitPrice) + $unitTax);
    }

    /**
     * The total of a line, taxes included when some are given, before the order total is
     * rounded (see orderTotal()).
     *
     * @param list<float> $unitTaxes
     */
    public function lineTotal(float $quantity, float $unitPrice, array $unitTaxes = []): float
    {
        if ($this->legacy) {
            return $quantity * $unitPrice + $quantity * array_sum($unitTaxes);
        }

        $lineTotal = self::roundAsDouble($quantity, QuantityScale::MAXIMUM_DECIMALS) * $this->unitAmount($unitPrice, $unitTaxes);

        if (!$this->roundingOfSums) {
            return $lineTotal;
        }

        return $this->textPromoPrice || $this->floatLineRounding
            ? self::roundAsDouble($lineTotal, 2)
            : MoneyRounding::toTheCent($lineTotal);
    }

    /**
     * A line excluding tax and its tax. The tax is the gap between the two totals, the way
     * Order::getTotalAmount() takes it out of the order.
     *
     * @param list<float> $unitTaxes
     *
     * @return array{float, float}
     */
    public function lineAmounts(float $quantity, float $unitPrice, array $unitTaxes): array
    {
        if ($this->legacy) {
            return [$quantity * $unitPrice, $quantity * array_sum($unitTaxes)];
        }

        $amount = $this->lineTotal($quantity, $unitPrice);

        return [$amount, $this->lineTotal($quantity, $unitPrice, $unitTaxes) - $amount];
    }

    /**
     * The order total from the sum of its line totals: rounded to the cent, except for an
     * order placed before 2.4, which keeps the figure it was invoiced with.
     */
    public function orderTotal(float $sumOfLineTotals): float
    {
        return $this->legacy ? $sumOfLineTotals : MoneyRounding::toTheCent($sumOfLineTotals);
    }

    /**
     * lineTotal() in SQL, on a row of order_product, before the order total is rounded.
     */
    public function lineTotalSql(bool $taxed): string
    {
        $unitPrice = $this->textPromoPrice ? self::UNIT_PRICE_AS_TEXT : self::UNIT_PRICE;
        $unitTax = self::UNIT_TAX;

        if (!$this->legacy && !$this->roundingOfSums) {
            $unitPrice = 'ROUND('.$unitPrice.', 2)';
            $unitTax = 'ROUND('.$unitTax.', 2)';
        }

        $unitAmount = $taxed
            ? '('.$unitPrice.' + (
                SELECT COALESCE(SUM('.$unitTax.'), 0)
                FROM order_product_tax
                WHERE order_product_tax.order_product_id = order_product.id
            ))'
            : $unitPrice;

        if ($this->legacy) {
            return 'order_product.quantity * '.$unitAmount;
        }

        // The quantity column is a binary floating point number: a single precision FLOAT kept
        // 0.7 kg as 0.69999998. A quantity carries three decimals at most.
        $lineTotal = 'ROUND(order_product.quantity, '.QuantityScale::MAXIMUM_DECIMALS.') * '.$unitAmount;

        if (!$this->roundingOfSums) {
            return $lineTotal;
        }

        // A DOUBLE line total of 1.25 x 14.82 is 18.524999…, which ROUND() would bring down to
        // 18.52 where the cart charged 18.53: it is rounded as a decimal at six places.
        return $this->textPromoPrice || $this->floatLineRounding
            ? 'ROUND('.$lineTotal.', 2)'
            : 'ROUND(CAST('.$lineTotal.' AS DECIMAL(20,'.self::STORED_DECIMALS.')), 2)';
    }

    /**
     * The DOUBLE MariaDB reads from a figure stored as a DECIMAL(16,6).
     */
    private static function asStored(float $amount): float
    {
        return round($amount, self::STORED_DECIMALS);
    }

    /**
     * ROUND() of MariaDB on a DOUBLE: rint(value × 10^places) / 10^places, half to even.
     */
    private static function roundAsDouble(float $value, int $places): float
    {
        $scale = (float) (10 ** $places);
        $scaled = $value * $scale;
        $rounded = floor($scaled);
        $fraction = $scaled - $rounded;

        if ($fraction > 0.5 || (0.5 === $fraction && 0.0 !== fmod($rounded, 2.0))) {
            ++$rounded;
        }

        return $rounded / $scale;
    }
}
