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

namespace Thelia\Domain\Payment\Service;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderNothingToPayEvent;
use Thelia\Core\Event\Order\OrderPayTotalEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Order;

/**
 * Whether an order is left with nothing to pay, so that the core pays it instead of its
 * payment module: a coupon that covers the whole order, a module that takes the rest off.
 *
 * Decided on the total the payment modules read (ORDER_PAY_GET_TOTAL, postage and discount
 * included), never on a figure of its own: the order a module would have charged nothing is
 * the one the core pays. An order paid in several parts is left to its parts, which turn it
 * paid themselves when the instant means cover it (OrderPaymentPartSettlementListener).
 */
final readonly class OrderWithNothingToPay
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private OrderPaymentPartSettlement $orderPaymentPartSettlement,
    ) {
    }

    public function hasNothingToPay(Order $order): bool
    {
        if (null !== $this->orderPaymentPartSettlement->balanceOf($order)) {
            return false;
        }

        $totalEvent = (new OrderPayTotalEvent($order))
            ->setTax(0)
            ->setIncludePostage(true)
            ->setIncludeDiscount(true);

        $this->dispatcher->dispatch($totalEvent, TheliaEvents::ORDER_PAY_GET_TOTAL);

        if (round((float) $totalEvent->getTotal(), 2) > 0.0) {
            return false;
        }

        $nothingToPayEvent = new OrderNothingToPayEvent($order);

        $this->dispatcher->dispatch($nothingToPayEvent, TheliaEvents::ORDER_NOTHING_TO_PAY);

        return !$nothingToPayEvent->isRefused();
    }
}
