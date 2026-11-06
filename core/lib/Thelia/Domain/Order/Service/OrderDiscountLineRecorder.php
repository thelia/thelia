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

namespace Thelia\Domain\Order\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Cart\Discount\CartDiscountLines;
use Thelia\Model\Cart;
use Thelia\Model\Order;
use Thelia\Model\OrderDiscountLine;

/**
 * Freezes on an order the discount lines the modules declared for its cart, the way
 * `order_coupon` freezes the promotions: the invoice and the order page read them back
 * without asking the modules again.
 *
 * The discount of the order is the one of its cart. The lines are served out of it first,
 * each up to its amount, as the cart lists them: what a line was granted is what is
 * written, and what the module may charge to the buyer's balance. A line granted nothing
 * leaves no row.
 */
final readonly class OrderDiscountLineRecorder
{
    public function __construct(
        private CartDiscountLines $cartDiscountLines,
    ) {
    }

    public function record(Order $order, Cart $cart, ConnectionInterface $connection): void
    {
        $lines = $this->cartDiscountLines->of($cart);

        if ([] === $lines) {
            return;
        }

        $granted = CartDiscountLines::serve($lines, (float) $order->getDiscount());

        foreach ($lines as $index => $line) {
            if ($granted[$index] <= 0.0) {
                continue;
            }

            (new OrderDiscountLine())
                ->setOrderId($order->getId())
                ->setLineKey($line->key)
                ->setLabel($line->label)
                ->setAmount((string) $granted[$index])
                ->save($connection);
        }
    }
}
