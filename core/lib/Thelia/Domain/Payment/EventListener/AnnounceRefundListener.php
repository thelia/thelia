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
use Thelia\Core\Event\Order\OrderPaymentTransactionEvent;
use Thelia\Core\Event\Order\OrderRefundedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\Service\RefundRequestContext;

/**
 * Sends ORDER_REFUNDED once per refund line, when it is written succeeded or its pending
 * answer arrives: from Thelia, from the provider's notification or from its back office.
 * The reason and the comment travel when the refund was asked from Thelia in the same
 * request; otherwise nobody gave them.
 *
 * After the journal moved the order, so the event sees it refunded when it is.
 */
final readonly class AnnounceRefundListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private RefundRequestContext $requests,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_PAYMENT_TRANSACTION_RECORDED, priority: -16)]
    public function onTransactionRecorded(OrderPaymentTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();

        if ($event->isReplay() || PaymentTransactionType::REFUND->value !== $transaction->getType() || !$transaction->isSucceeded()) {
            return;
        }

        $request = $this->requests->of((int) $transaction->getOrderId());

        $this->eventDispatcher->dispatch(
            new OrderRefundedEvent(
                $event->getOrder(),
                $transaction,
                $request['reason'] ?? null,
                $request['comment'] ?? null,
                $request['offline'] ?? false,
            ),
            TheliaEvents::ORDER_REFUNDED,
        );
    }
}
