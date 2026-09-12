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

namespace Thelia\Domain\OrderReturn\Service;

use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;

/**
 * Computes the refundable amount of a return from the prices actually paid on
 * the returned lines, discounts and taxes included.
 *
 * The per-unit taxed price is built the same way Order::getTotalAmount() builds
 * the order total (Order::buildTotalAmountQuery): the promo price and promo tax
 * are used when the line was bought in promo, and the rounding follows the
 * order rounding mode, so a refund never drifts from what the customer paid.
 *
 * The discount carried by the order is shared over the lines in proportion to
 * what each one weighs, so that returning a whole order refunds what was
 * actually charged for it and not what its lines are labelled at.
 */
final readonly class RefundAmountCalculator
{
    /**
     * The taxed unit price paid for an order product line.
     */
    public function unitTaxedPrice(OrderProduct $orderProduct): float
    {
        $wasInPromo = (bool) $orderProduct->getWasInPromo();

        $unitPrice = (float) ($wasInPromo ? $orderProduct->getPromoPrice() : $orderProduct->getPrice());

        $unitTax = 0.0;
        foreach ($orderProduct->getOrderProductTaxes() as $tax) {
            $unitTax += (float) ($wasInPromo ? $tax->getPromoAmount() : $tax->getAmount());
        }

        if (!$this->isRoundingOfSums($orderProduct)) {
            // Sum of roundings: each unit amount is charged rounded to the cent.
            $unitPrice = round($unitPrice, 2);
            $unitTax = round($unitTax, 2);
        }

        return $unitPrice + $unitTax;
    }

    /**
     * The refundable amount for a single return line, for the given quantity.
     */
    public function lineRefund(OrderReturnLine $line, float $quantity): float
    {
        return $this->lineRefundForProduct($line->getOrderProduct(), $quantity);
    }

    /**
     * The refundable amount for the given quantity of an order product line.
     */
    public function lineRefundForProduct(OrderProduct $orderProduct, float $quantity): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }

        $lineTotal = $this->unitTaxedPrice($orderProduct) * $quantity;

        if ($this->isRoundingOfSums($orderProduct)) {
            // Rounding of sums: only the line total is rounded.
            $lineTotal = round($lineTotal, 2);
        }

        return $lineTotal - $this->discountShareOf($orderProduct, $lineTotal);
    }

    /**
     * The refundable amount for a whole return.
     *
     * @param bool $useReceivedQuantity when true, the received quantities are used
     *                                  (reception step); otherwise the requested ones
     */
    public function compute(OrderReturn $return, bool $useReceivedQuantity = false): float
    {
        $total = 0.0;

        foreach ($return->getOrderReturnLines() as $line) {
            $quantity = $useReceivedQuantity
                ? (float) $line->getQuantityReceived()
                : (float) $line->getQuantity();

            $total += $this->lineRefund($line, $quantity);
        }

        if ($return->getIncludePostage()) {
            $order = $return->getOrder();
            $total += (float) $order->getPostage() + (float) $order->getPostageTax();
        }

        return $total;
    }

    /**
     * The share of the order discount the line carries, in proportion to what
     * the line weighs in the order.
     *
     * An order discount - a coupon, a merchant rebate - is stored once on the
     * order and taken off its total (Order::getTotalAmount), not off the lines:
     * an order of 100 paid 50 keeps lines totalling 100. Refunding the lines at
     * face value would therefore hand back twice what the customer paid, so the
     * discount has to be shared out over the lines it was granted on.
     *
     * The share is computed on the taxed amounts, which is where the core
     * subtracts the discount too: `refund_amount` is a single taxed figure, so
     * there is no separate tax total for the untaxed share of the discount
     * (Calculator::computeUntaxedOrderDiscount) to be taken off.
     */
    private function discountShareOf(OrderProduct $orderProduct, float $lineTotal): float
    {
        $order = $orderProduct->getOrder();

        if (null === $order || $lineTotal <= 0.0) {
            return 0.0;
        }

        $discount = (float) $order->getDiscount();

        if ($discount <= 0.0) {
            return 0.0;
        }

        $orderLinesTotal = $this->orderLinesTotal($order);

        if ($orderLinesTotal <= 0.0) {
            return 0.0;
        }

        // A discount wider than the order itself - the core clamps the total at
        // zero rather than paying the customer - refunds the line down to zero
        // and no further.
        return min($discount * ($lineTotal / $orderLinesTotal), $lineTotal);
    }

    /**
     * What the lines of the order add up to, before the discount and without
     * the postage: the base the discount is shared over.
     *
     * Asked of the order itself so that the promo prices, the per-unit taxes,
     * the rounding mode and the pre-2.4 legacy rounding are the ones the order
     * was charged with, and stay that way if the core ever changes how it
     * totals an order.
     */
    private function orderLinesTotal(Order $order): float
    {
        $tax = 0.0;

        return (float) $order->getTotalAmount($tax, includePostage: false, includeDiscount: false);
    }

    private function isRoundingOfSums(OrderProduct $orderProduct): bool
    {
        return ConfigQuery::isRoundingModeRoundingOfSums((int) $orderProduct->getOrderId());
    }
}
