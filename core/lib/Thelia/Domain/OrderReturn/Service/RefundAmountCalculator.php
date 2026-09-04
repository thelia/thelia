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

        return $lineTotal;
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

    private function isRoundingOfSums(OrderProduct $orderProduct): bool
    {
        return ConfigQuery::isRoundingModeRoundingOfSums((int) $orderProduct->getOrderId());
    }
}
