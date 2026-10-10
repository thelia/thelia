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
use Thelia\Core\Event\Order\OrderPaymentRefundEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Service\PaymentRefundService;

/**
 * The core answer to ORDER_PAYMENT_REFUND: the back office, the admin API and a module's own
 * automation all refund through this event.
 */
final readonly class RefundOrderPaymentListener
{
    public function __construct(
        private PaymentRefundService $refundService,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_PAYMENT_REFUND, priority: 128)]
    public function onRefund(OrderPaymentRefundEvent $event): void
    {
        $event->setTransaction($event->isOffline()
            ? $this->refundService->recordOfflineRefund($event->getOrder(), $event->getAmount(), $event->getReason(), $event->getComment())
            : $this->refundService->refund($event->getOrder(), $event->getAmount(), $event->getReason(), $event->getComment()));
    }
}
