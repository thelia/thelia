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
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Service\OrderPaymentPartSettlement;

/**
 * Gives back the parts of an order once it is cancelled, whoever cancels it: the back
 * office, the API, the failed return of a provider, or a new payment attempt that cannot
 * present the unpaid order again.
 *
 * After Action\Order::updateStatus, at 128, so that a transition the status graph refuses
 * releases nothing, and in a transaction of its own, opened once the status is written.
 *
 * A part released is never settled again, since only pending parts are. An order cancelled
 * and then set back to paid, which the graph allows when the shop has drawn no transition
 * from cancelled, is refused by OrderPaymentPartPaidStatusGuardListener while an instant part
 * of it is released; forced from the back office, it keeps its parts released.
 *
 * Inside a transaction the caller opened (the admin API), the parts are released, and the
 * modules told, before that transaction commits: see OrderPaymentPartSettlement.
 *
 * Refunded orders are left alone: a refund across several parts is another matter.
 */
final readonly class OrderPaymentPartReleaseListener
{
    public function __construct(
        private OrderPaymentPartSettlement $settlement,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 100)]
    public function releaseThePartsOfACancelledOrder(OrderEvent $event): void
    {
        $order = $event->getOrder();

        if ($order->isCancelled()) {
            $this->settlement->releasePartsOf($order);
        }
    }
}
