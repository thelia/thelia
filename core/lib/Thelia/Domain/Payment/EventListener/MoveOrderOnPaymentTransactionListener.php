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
use Thelia\Domain\Order\Service\OrderStatusTransitionGuard;
use Thelia\Domain\Payment\DTO\PaymentTransactionTotals;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\Service\CurrencyMinorUnit;
use Thelia\Domain\Payment\Service\PaymentAmount;
use Thelia\Domain\Payment\Service\PaymentTransactionTotalsReader;
use Thelia\Log\Tlog;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * Moves an order along with the money that was reserved, taken or released on it.
 *
 * An authorization puts an unpaid order on hold for capture. The order is paid once the
 * authorization is settled — everything captured, or what was not captured released —
 * with no capture still waiting for its answer and something actually taken; released
 * without anything taken, it goes back to unpaid. A remainder smaller than the smallest
 * coin of the currency counts as nothing left.
 *
 * Each move goes through ORDER_UPDATE_STATUS, so the stock, the invoice numbering and
 * the history see it like any other. The journal is the truth and the status follows it:
 * a move the transition graph refuses, or a status listener that fails, is logged and
 * leaves the line written and the provider's notification answered. The event may be
 * raised twice for the same line when a notification is replayed; every move here is
 * decided from the current status and totals, so the second call changes nothing.
 *
 * The hold status is the custom status seeded as `awaiting_capture`: a shop that deleted
 * it keeps its authorized orders unpaid.
 */
final readonly class MoveOrderOnPaymentTransactionListener
{
    public function __construct(
        private PaymentTransactionTotalsReader $totalsReader,
        private EventDispatcherInterface $eventDispatcher,
        private OrderStatusTransitionGuard $transitionGuard,
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
            PaymentTransactionType::CAPTURE, PaymentTransactionType::VOID => $this->settleOnceNothingIsHeld($event),
            default => null,
        };
    }

    private function holdForCapture(OrderPaymentTransactionEvent $event): void
    {
        $order = $event->getOrder();
        $status = $order->getOrderStatus();

        if ($status->isPaid(false) || $status->isCancelled(false) || $status->isRefunded(false)) {
            return;
        }

        $holdStatus = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_AWAITING_CAPTURE);

        if (null === $holdStatus || $holdStatus->getId() === $order->getStatusId()) {
            return;
        }

        $this->move($order, (int) $holdStatus->getId(), $event);
    }

    /**
     * After a capture or a release: once the authorization holds nothing more and no
     * capture waits for its answer, the order is paid if anything was taken, and back to
     * unpaid if it was all released.
     */
    private function settleOnceNothingIsHeld(OrderPaymentTransactionEvent $event): void
    {
        $order = $event->getOrder();
        $status = $order->getOrderStatus();

        if ($status->isPaid(false) || $status->isCancelled(false) || $status->isRefunded(false)) {
            return;
        }

        $totals = $this->totalsReader->forOrder((int) $order->getId());

        // A capture without an authorization is the module taking the price at once;
        // that module says "paid" itself, through the status, and is not second-guessed.
        if (!$totals->hasAuthorization() || !$this->nothingIsHeld($totals, $order)) {
            return;
        }

        if (PaymentAmount::isPositive($totals->captured)) {
            $this->move($order, (int) OrderStatusQuery::getPaidStatus()->getId(), $event);

            return;
        }

        if (OrderStatus::CODE_AWAITING_CAPTURE === $status->getCode()) {
            $this->move($order, (int) OrderStatusQuery::getNotPaidStatus()->getId(), $event);
        }
    }

    private function nothingIsHeld(PaymentTransactionTotals $totals, Order $order): bool
    {
        return !$totals->hasPendingCapture()
            && !PaymentAmount::isPositive($totals->pendingVoid)
            && CurrencyMinorUnit::isBelowSmallestCoin($totals->remainingToCapture, $order->getCurrency()->getCode());
    }

    private function move(Order $order, int $statusId, OrderPaymentTransactionEvent $event): void
    {
        if (!$this->transitionGuard->isAllowed((int) $order->getStatusId(), $statusId)) {
            Tlog::getInstance()->warning(\sprintf(
                'Order %s stays in its status: the transition graph does not allow the move to status #%d the payment journal calls for.',
                (string) $order->getRef(),
                $statusId,
            ));

            return;
        }

        $statusEvent = (new OrderEvent($order))
            ->setStatus($statusId)
            ->setSourceModuleCode($event->getSourceModuleCode());

        try {
            $this->eventDispatcher->dispatch($statusEvent, TheliaEvents::ORDER_UPDATE_STATUS);
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->error(\sprintf(
                'Order %s could not follow its payment journal to status #%d: %s',
                (string) $order->getRef(),
                $statusId,
                $throwable->getMessage(),
            ));
        }
    }
}
