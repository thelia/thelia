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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Service\OrderWithNothingToPay;
use Thelia\Model\OrderStatusQuery;

/**
 * Turns paid an order the discount leaves nothing to pay, before its payment module is asked
 * to charge it (Action\Module::pay listens at 128, and skips such an order).
 *
 * At 160, after the instant parts are settled (192): an order paid in parts is theirs. The
 * status goes through ORDER_UPDATE_STATUS like any payment confirmed by a provider, so the
 * stock and every listener of a paid order follow; the confirmation e-mail already went out
 * when the order was placed (ORDER_BEFORE_PAYMENT). The payment module the buyer chose stays
 * on the order. With no response on the event, the tunnel goes on to the confirmation.
 */
final readonly class OrderWithNothingToPayListener
{
    public function __construct(
        private OrderWithNothingToPay $orderWithNothingToPay,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::MODULE_PAY, priority: 160)]
    public function payAnOrderWithNothingToPay(OrderPaymentEvent $event): void
    {
        $order = $event->getOrder();

        if ($order->isPaid(false) || !$this->orderWithNothingToPay->hasNothingToPay($order)) {
            return;
        }

        $this->dispatcher->dispatch(
            (new OrderEvent($order))->setStatus((int) OrderStatusQuery::getPaidStatus()->getId()),
            TheliaEvents::ORDER_UPDATE_STATUS,
        );
    }
}
