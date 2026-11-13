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

use Thelia\Domain\Pricing\MoneyRounding;
use Thelia\Domain\Pricing\OrderLineRounding;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;

/**
 * Computes the refundable amount of a return from the prices actually paid on
 * the returned lines, discounts and taxes included.
 *
 * A line is totalled by the rounding rule of its order (OrderLineRounding), the
 * one Order::getTotalAmount() totals the order with: the promo price and promo
 * tax are used when the line was bought in promo, so a refund never drifts from
 * what the customer paid.
 *
 * The discount carried by the order is shared over the lines in proportion to
 * what each one weighs, so that returning a whole order refunds what was
 * actually charged for it and not what its lines are labelled at.
 */
final readonly class RefundAmountCalculator
{
    /**
     * The taxed unit price paid for an order product line, rounded as its order rounds a unit.
     */
    public function unitTaxedPrice(OrderProduct $orderProduct): float
    {
        [$unitPrice, $unitTaxes] = $this->unitAmountsOf($orderProduct);

        return $this->roundingOf($orderProduct)->unitAmount($unitPrice, $unitTaxes);
    }

    /**
     * The refundable amount for a single return line, for the given quantity.
     */
    public function lineRefund(OrderReturnLine $line, float $quantity): float
    {
        return $this->lineRefundForProduct($line->getOrderProduct(), $quantity);
    }

    /**
     * The refundable amount for the given quantity of an order product line,
     * rounded half up to the cent as the order charged it: 1.25 kg at 14.82 is
     * a line of 18.525, charged and refunded 18.53.
     */
    public function lineRefundForProduct(OrderProduct $orderProduct, float $quantity): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }

        $order = $orderProduct->getOrder();
        $lineTotal = $this->roundedLikeTheOrder($order, $this->lineTotal($orderProduct, $quantity));

        return $lineTotal - $this->discountShareOf($order, $lineTotal);
    }

    /**
     * The refundable amount for a whole return.
     *
     * @param bool $useReceivedQuantity when true, the received quantities are used
     *                                  (reception step); otherwise the requested ones
     */
    public function compute(OrderReturn $return, bool $useReceivedQuantity = false): float
    {
        $order = $return->getOrder();
        $lines = [];

        foreach ($return->getOrderReturnLines() as $line) {
            $lines[] = [
                'order_product' => $line->getOrderProduct(),
                'quantity' => $useReceivedQuantity ? (float) $line->getQuantityReceived() : (float) $line->getQuantity(),
            ];
        }

        $total = $this->linesRefund($order, $lines);

        if ($return->getIncludePostage()) {
            // Stored tax included, like the postage of a cart.
            $total += (float) $order->getPostage();
        }

        return $total;
    }

    /**
     * The refundable amount for several lines of an order returned together,
     * the postage left out.
     *
     * The lines are added up before being rounded, the way Order::getTotalAmount()
     * adds up the lines it charges: two lines of 18.525 were charged 37.05, and
     * returning both refunds 37.05, not the 37.06 of the two rounded lines.
     *
     * @param iterable<array{order_product: OrderProduct, quantity: float}> $lines
     */
    public function linesRefund(?Order $order, iterable $lines): float
    {
        $linesTotal = 0.0;

        foreach ($lines as $line) {
            if ($line['quantity'] > 0) {
                $linesTotal += $this->lineTotal($line['order_product'], $line['quantity']);
            }
        }

        $linesTotal = $this->roundedLikeTheOrder($order, $linesTotal);

        return $linesTotal - $this->discountShareOf($order, $linesTotal);
    }

    /**
     * The taxed total of the given quantity of a line, before the discount, in
     * the rounding rule of its order.
     */
    private function lineTotal(OrderProduct $orderProduct, float $quantity): float
    {
        [$unitPrice, $unitTaxes] = $this->unitAmountsOf($orderProduct);

        return $this->roundingOf($orderProduct)->lineTotal($quantity, $unitPrice, $unitTaxes);
    }

    /**
     * Rounds an amount as Order::getTotalAmount() rounds the lines it charges: to
     * the cent, except for an order frozen by the 2.4 upgrade, totalled without any
     * rounding (Order::getTotalAmountLegacy()).
     */
    private function roundedLikeTheOrder(?Order $order, float $amount): float
    {
        if (null === $order) {
            return MoneyRounding::toTheCent($amount);
        }

        return OrderLineRounding::ofOrder((int) $order->getId())->orderTotal($amount);
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
    private function discountShareOf(?Order $order, float $lineTotal): float
    {
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

    /**
     * The unit price and the unit taxes paid: the promotional ones when the line was bought in promo.
     *
     * @return array{float, list<float>}
     */
    private function unitAmountsOf(OrderProduct $orderProduct): array
    {
        $wasInPromo = (bool) $orderProduct->getWasInPromo();
        $unitTaxes = [];

        foreach ($orderProduct->getOrderProductTaxes() as $tax) {
            $unitTaxes[] = (float) ($wasInPromo ? $tax->getPromoAmount() : $tax->getAmount());
        }

        return [(float) ($wasInPromo ? $orderProduct->getPromoPrice() : $orderProduct->getPrice()), $unitTaxes];
    }

    private function roundingOf(OrderProduct $orderProduct): OrderLineRounding
    {
        return OrderLineRounding::ofOrder((int) $orderProduct->getOrderId());
    }
}
