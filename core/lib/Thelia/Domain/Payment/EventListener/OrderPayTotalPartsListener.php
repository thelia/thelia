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

namespace Thelia\Domain\Payment\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Order\OrderPayTotalEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Service\OrderPaymentPartSettlement;

/**
 * What a payment module charges for an order paid with several means is the balance its
 * own part holds, not the total of the order.
 *
 * Last in line, at 16 (the core answers at 128, a module that takes cards off the total,
 * the gift card module for one, at 60): it replaces the total rather than subtracting from
 * it, so the answer is the same whatever the listeners before it did. An order with no part
 * keeps the total they computed.
 *
 * The balance is the one of the complete order, postage and discount included. A caller that
 * leaves the postage or the discount out gets the balance moved by what that leaves out of the
 * total, never below nothing: the balance, plus the total with the event's flags, minus the
 * complete total.
 */
final readonly class OrderPayTotalPartsListener
{
    public function __construct(private OrderPaymentPartSettlement $settlement)
    {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_PAY_GET_TOTAL, priority: 16)]
    public function replaceTheTotalWithTheBalance(OrderPayTotalEvent $event): void
    {
        $balance = $this->settlement->balanceOf($event->getOrder());

        if (null === $balance) {
            return;
        }

        if ($event->isIncludePostage() && $event->isIncludeDiscount()) {
            $event->setTotal((float) $balance);

            return;
        }

        $order = $event->getOrder();
        $completeTax = 0;
        $flaggedTax = 0;
        $complete = $order->getTotalAmount($completeTax, true, true);
        $flagged = $order->getTotalAmount($flaggedTax, $event->isIncludePostage(), $event->isIncludeDiscount());

        $event->setTotal(max(0.0, round((float) $balance + $flagged - $complete, 2)));
    }
}
