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
use Thelia\Core\Event\Order\OrderPaymentTransactionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\Service\PaymentTransactionTotalsReader;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * Moves an order along with the money that was reserved, taken or released on it.
 *
 * An authorization puts an unpaid order on hold for capture; the capture of the
 * last amount held makes it paid; a void sends an order on hold back to unpaid. Each
 * move goes through ORDER_UPDATE_STATUS, so the transition graph, the stock, the
 * invoice numbering and the history see it like any other.
 *
 * The hold status is the custom status seeded as `awaiting_capture`: a shop that
 * deleted it keeps its authorized orders unpaid, which is what they are.
 */
final readonly class MoveOrderOnPaymentTransactionListener
{
    public function __construct(
        private PaymentTransactionTotalsReader $totalsReader,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_PAYMENT_TRANSACTION_RECORDED)]
    public function onTransactionRecorded(OrderPaymentTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();

        if (!$transaction->isSucceeded()) {
            return;
        }

        match ($transaction->getTypeEnum()) {
            PaymentTransactionType::AUTHORIZATION => $this->holdForCapture($event),
            PaymentTransactionType::CAPTURE => $this->markPaidOnceFullyCaptured($event),
            PaymentTransactionType::VOID => $this->releaseHold($event),
            default => null,
        };
    }

    private function holdForCapture(OrderPaymentTransactionEvent $event): void
    {
        $order = $event->getOrder();
        $status = $order->getOrderStatus();

        if ($status->isPaid(false) || $status->isCancelled() || $status->isRefunded()) {
            return;
        }

        $holdStatus = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_AWAITING_CAPTURE);

        if (null === $holdStatus || $holdStatus->getId() === $order->getStatusId()) {
            return;
        }

        $this->move($order, (int) $holdStatus->getId(), $event);
    }

    private function markPaidOnceFullyCaptured(OrderPaymentTransactionEvent $event): void
    {
        $order = $event->getOrder();

        if ($order->isPaid(false)) {
            return;
        }

        $totals = $this->totalsReader->forOrder((int) $order->getId());

        // A capture without an authorization is the module taking the price at once;
        // that module says "paid" itself, through the status, and is not second-guessed.
        if (!$totals->hasAuthorization() || $totals->hasSomethingLeftToCapture()) {
            return;
        }

        $this->move($order, (int) OrderStatusQuery::getPaidStatus()->getId(), $event);
    }

    private function releaseHold(OrderPaymentTransactionEvent $event): void
    {
        $order = $event->getOrder();

        if (OrderStatus::CODE_AWAITING_CAPTURE !== $order->getOrderStatus()->getCode()) {
            return;
        }

        $this->move($order, (int) OrderStatusQuery::getNotPaidStatus()->getId(), $event);
    }

    private function move(Order $order, int $statusId, OrderPaymentTransactionEvent $event): void
    {
        $statusEvent = (new OrderEvent($order))
            ->setStatus($statusId)
            ->setSourceModuleCode($event->getSourceModuleCode());

        $this->eventDispatcher->dispatch($statusEvent, TheliaEvents::ORDER_UPDATE_STATUS);
    }
}
