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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderPaymentPartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Exception\OrderStatusTransitionRefusedException;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Domain\Payment\Exception\CancelledOrderPaymentRefusedException;
use Thelia\Domain\Payment\Exception\InstantPaymentRefusedException;
use Thelia\Log\Tlog;
use Thelia\Model\Map\OrderPaymentPartTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderPaymentPartQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\InstantPaymentModuleInterface;

/**
 * Settles the parts of an order that instant payment modules cover, and tells the part a
 * provider covers from them.
 *
 * Locks first, as everywhere an order is written by two requests at once: the order row by
 * its primary key, before anything else is read in the transaction, since the read view of
 * InnoDB freezes at the first plain read; then its parts by their primary keys, in id order.
 * The ids of the parts are read under the lock of the order: they are written once, in the
 * transaction that inserts the order row, so no other transaction can add or take a part
 * while the order is locked, and the read is current. The parts are not locked through the
 * index on the order, which would hold the gap after its last order too: measured on
 * MariaDB 10.11, the parts of any order placed meanwhile then wait for the lock. An order
 * with no part takes no lock on the parts. The instance pool of Propel is emptied once the
 * locks are held: the pool, on in production, would hand back a part read before them,
 * pending when another request has settled it since.
 *
 * Inside a transaction someone else opened (the admin API wraps ORDER_UPDATE_STATUS in one),
 * the commit of this service only takes a nesting level off: the locks are held, and what is
 * written stays undone, until the caller commits. What the modules were told is not: a part
 * a module has given back is announced released, and a caller that rolls back afterwards
 * puts the row back to `settled` although the module released it. The core has no hook to
 * run once the outermost transaction commits; a caller that dispatches a cancellation inside
 * its own transaction answers for not rolling it back after the status was written.
 *
 * A part is `pending` until its module has settled it, then `settled`. This service writes
 * both steps: the first for the instant parts (settleInstantPartsOf), the second for the
 * part of the provider (settleTheProviderPartOf, when the payment is confirmed). A part
 * that is `settled` is never settled again, which is what makes a new payment attempt on
 * the same order safe.
 *
 * A non-primary part is an instant one by construction (the splitter), and the primary part
 * is one when its module says so.
 */
final readonly class OrderPaymentPartSettlement
{
    public function __construct(
        private ContainerInterface $container,
        private EventDispatcherInterface $dispatcher,
        private OrderHistoryRecorder $orderHistoryRecorder,
    ) {
    }

    /**
     * Settles the instant parts of the order that are still pending, in position order,
     * all or none: when a module refuses, the parts settled before it in this pass are
     * released, in reverse order, and put back to pending, and the transaction is rolled
     * back. The release comes before the rollback so that a module whose own effects are
     * written on this connection has them undone once, and one whose effects are external
     * has them compensated.
     *
     * A part is counted as settled as soon as its module has settled it, before its row is
     * written: a write that fails then gives it back like a refusal does.
     *
     * An order found cancelled under the lock is refused before any part is settled: its
     * parts were given back, nothing may be charged for it any more. So is an order that
     * holds a released instant part, whatever its status: the back office may have turned a
     * cancelled order back to unpaid, and a new attempt would charge the provider the balance
     * of a payment whose instant parts were given back.
     *
     * An order with no part is left alone, with no transaction and no lock: an order paid
     * with one means goes the way it always went, cancelled or not. Whether it has parts is
     * a plain read before the lock, which is sound since the parts are written once, with
     * the order row: any read that sees the order sees them.
     *
     * Only what a module says is turned into a refusal of the payment; any other failure (the
     * database, a module that is not an instant one) goes up as it is, once what was settled
     * has been given back and the transaction rolled back.
     *
     * Each part settled is announced after the commit.
     *
     * @throws InstantPaymentRefusedException        when a module refuses or cannot be reached
     * @throws CancelledOrderPaymentRefusedException when the order is cancelled
     */
    public function settleInstantPartsOf(Order $order): void
    {
        if (!$this->hasParts($order)) {
            return;
        }

        $connection = Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        $settled = [];

        try {
            $this->assertCommitableBeforeAskingModules($connection, (int) $order->getId());
            $status = $this->lockTheOrder((int) $order->getId(), $connection);

            if ($status?->isCancelled()) {
                throw CancelledOrderPaymentRefusedException::forOrder((string) $order->getRef());
            }

            $parts = $this->lockThePartsOf((int) $order->getId(), $connection);

            foreach ($parts as $part) {
                if (OrderPaymentPartState::RELEASED->value === $part->getState() && $this->isInstant($part)) {
                    throw CancelledOrderPaymentRefusedException::forOrderWhosePartsWereGivenBack((string) $order->getRef());
                }
            }

            foreach ($parts as $part) {
                if (OrderPaymentPartState::PENDING->value !== $part->getState() || !$this->isInstant($part)) {
                    continue;
                }

                $module = $this->moduleOf($part);
                $wasCommitable = $this->isStillCommitable($connection);

                try {
                    $module->settlePart($part);
                } catch (\Throwable $refusal) {
                    throw InstantPaymentRefusedException::by($refusal);
                }

                $settled[] = $part;
                $this->assertStillCommitable($wasCommitable, $connection, $part);
                $part->setState(OrderPaymentPartState::SETTLED->value)->save($connection);
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $this->takeBack($settled, $connection);
            $connection->rollBack();
            OrderPaymentPartTableMap::clearInstancePool();

            throw $throwable;
        }

        OrderPaymentPartTableMap::clearInstancePool();

        foreach ($settled as $part) {
            $this->dispatcher->dispatch(new OrderPaymentPartEvent($part), TheliaEvents::ORDER_PAYMENT_PART_SETTLE);
        }
    }

    /**
     * Whether the instant parts are all that pays the order: its primary part is an instant
     * one, so there is no part left for a provider to charge.
     */
    public function isPaidByInstantPartsOnly(Order $order): bool
    {
        $primary = $this->primaryPartOf($order);

        return null !== $primary && $this->isInstant($primary);
    }

    /**
     * Whether every part of the order is settled: what an order paid by its instant parts
     * alone must show before the core turns it paid. A part released by a cancellation is
     * never settled again, so an order whose parts were given back is never turned paid by
     * them; a pending one is only left by a settlement that did not run.
     */
    public function areAllPartsSettled(Order $order): bool
    {
        $parts = $this->partsOf($order);

        foreach ($parts as $part) {
            if (OrderPaymentPartState::SETTLED->value !== $part->getState()) {
                return false;
            }
        }

        return [] !== $parts;
    }

    /**
     * Whether the order was refunded at some point and still holds an instant part `settled`.
     *
     * A refund gives no instant part back: the module of a card or a credit note returns the
     * amount on its own, and the part stays `settled`, so that the order, turned unpaid again
     * by the back office, looks to a new payment attempt like one whose card is still spent.
     * Charging the provider the balance alone would then pay it with the card part missing.
     *
     * The mark is the history of the order, which writes every status change, the one to a
     * refunded status included: an order whose history was purged has lost it.
     */
    public function wasRefundedWhileHoldingInstantParts(Order $order): bool
    {
        $holdsASettledInstantPart = false;

        foreach ($this->partsOf($order) as $part) {
            if (OrderPaymentPartState::SETTLED->value === $part->getState() && $this->isInstant($part)) {
                $holdsASettledInstantPart = true;

                break;
            }
        }

        if (!$holdsASettledInstantPart) {
            return false;
        }

        $changes = OrderHistoryQuery::create()
            ->filterByOrderId($order->getId())
            ->filterByEventType(OrderHistoryEventType::STATUS_CHANGED->value)
            ->select('Payload')
            ->find(Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME));
        $refundedCodes = [];

        foreach ($changes as $change) {
            $payload = json_decode((string) $change, true);
            $toStatusCode = \is_array($payload) && \is_string($payload['to'] ?? null) ? $payload['to'] : null;

            if (null === $toStatusCode) {
                continue;
            }

            $refundedCodes[$toStatusCode] ??= (bool) OrderStatusQuery::create()->findOneByCode($toStatusCode)?->isRefunded(false);

            if ($refundedCodes[$toStatusCode]) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the provider charges: null for an order with no part, which is paid as a whole;
     * the amount of the primary part when a provider covers it; nothing when the instant
     * parts cover everything.
     */
    public function balanceOf(Order $order): ?string
    {
        $parts = $this->partsOf($order);

        if ([] === $parts) {
            return null;
        }

        foreach ($parts as $part) {
            if ($part->getIsPrimary() && !$this->isInstant($part)) {
                return (string) $part->getAmount();
            }
        }

        return '0.000000';
    }

    /**
     * Refuses to turn an order paid, unless the transition is forced, while one of its
     * instant parts is not settled: the order would count on a gift card or a credit note
     * that was given back by a cancellation, or that nothing charged.
     *
     * Read under the lock of the order then of its parts, as they are stored, and only when
     * the order is not already in a paid status. The refusal is the one the transition graph
     * gives, with no amount and nothing a module said.
     *
     * The provider of the order that confirms the payment of such an order has charged the
     * buyer all the same: the old session of an order cancelled by a new attempt, which gave
     * the card back. Its part, released by the cancellation, turns `settled` again, and the
     * history of the order says the payment is to be refunded, so that what was charged is
     * not lost from sight. The provider is the module whose code the event carries as its
     * source and that holds the primary part; a paid status asked by anyone else (the back
     * office sets no source) is refused without writing anything. Inside a transaction the
     * caller opened and rolls back, both writes are undone with it.
     *
     * @throws OrderStatusTransitionRefusedException
     */
    public function assertMayBeTurnedPaid(Order $order, int $toStatusId, ?string $sourceModuleCode = null): void
    {
        $connection = Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        $fromStatus = null;
        $refused = false;
        $confirmedAfterRelease = null;

        try {
            $fromStatus = $this->lockTheOrder((int) $order->getId(), $connection);

            if (null !== $fromStatus && !$fromStatus->isPaid(false)) {
                $parts = $this->lockThePartsOf((int) $order->getId(), $connection);

                foreach ($parts as $part) {
                    if ($this->isInstant($part) && OrderPaymentPartState::SETTLED->value !== $part->getState()) {
                        $refused = true;
                    }
                }

                if ($refused) {
                    $confirmedAfterRelease = $this->settleTheReleasedProviderPartOf($parts, $sourceModuleCode, $connection);
                }
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();
            OrderPaymentPartTableMap::clearInstancePool();

            throw $throwable;
        }

        OrderPaymentPartTableMap::clearInstancePool();

        if (!$refused) {
            return;
        }

        if (null !== $confirmedAfterRelease) {
            $this->orderHistoryRecorder->record(
                (int) $order->getId(),
                OrderHistoryEventType::PAYMENT_CONFIRMED_AFTER_RELEASE->value,
                ['payment_part_id' => (int) $confirmedAfterRelease->getId()],
                moduleCode: $sourceModuleCode,
            );
        }

        throw new OrderStatusTransitionRefusedException((string) $order->getRef(), (string) $fromStatus->getCode(), (string) (OrderStatusQuery::create()->findPk($toStatusId)?->getCode() ?? $toStatusId));
    }

    /**
     * The part of the provider, when a cancellation released it and the provider itself
     * confirms the payment, turned `settled` by an update on its primary key conditional on
     * `released`.
     *
     * @param list<OrderPaymentPart> $parts read under the lock
     */
    private function settleTheReleasedProviderPartOf(array $parts, ?string $sourceModuleCode, ConnectionInterface $connection): ?OrderPaymentPart
    {
        foreach ($parts as $part) {
            if (!$part->getIsPrimary() || $this->isInstant($part) || OrderPaymentPartState::RELEASED->value !== $part->getState()) {
                continue;
            }

            if (!$this->isConfirmedByItsProvider($part, $sourceModuleCode)) {
                return null;
            }

            $updated = OrderPaymentPartQuery::create()
                ->filterById($part->getId())
                ->filterByState(OrderPaymentPartState::RELEASED->value)
                ->update(['State' => OrderPaymentPartState::SETTLED->value, 'UpdatedAt' => new \DateTime()], $connection);

            OrderPaymentPartTableMap::clearInstancePool();

            return 1 === $updated ? $part : null;
        }

        return null;
    }

    /**
     * The payment of the order is confirmed, by its provider or from the back office: the
     * part of the provider is settled. One update by primary key, conditional on the state,
     * which neither waits for a release that comes first nor overrides it.
     *
     * A part a cancellation released is settled only when the provider itself confirms
     * (the code of its module is the source of the event): it charged the balance, and the
     * order it pays is paid, its instant parts settled, since the guard let it through. Set
     * paid by anyone else, the part is left released and the gap is logged.
     */
    public function settleTheProviderPartOf(Order $order, ?string $sourceModuleCode = null): void
    {
        $primary = $this->primaryPartOf($order);

        if (null === $primary || $this->isInstant($primary)) {
            return;
        }

        $from = OrderPaymentPartState::PENDING->value;

        if (OrderPaymentPartState::RELEASED->value === $primary->getState()) {
            if (!$this->isConfirmedByItsProvider($primary, $sourceModuleCode)) {
                Tlog::getInstance()->warning(\sprintf('Order %d was turned paid while the payment part %d of its provider is released: nothing says the provider charged it.', (int) $order->getId(), (int) $primary->getId()));

                return;
            }

            $from = OrderPaymentPartState::RELEASED->value;
        }

        OrderPaymentPartQuery::create()
            ->filterById($primary->getId())
            ->filterByState($from)
            ->update(['State' => OrderPaymentPartState::SETTLED->value, 'UpdatedAt' => new \DateTime()]);

        OrderPaymentPartTableMap::clearInstancePool();
    }

    /**
     * Whether the event comes from the module that holds the part: a module confirming its
     * own payment names itself as the source; the back office names none.
     */
    private function isConfirmedByItsProvider(OrderPaymentPart $part, ?string $sourceModuleCode): bool
    {
        if (null === $sourceModuleCode || '' === $sourceModuleCode || null === $part->getPaymentModuleId()) {
            return false;
        }

        return $part->getPaymentModule()?->getCode() === $sourceModuleCode;
    }

    /**
     * The order is cancelled: what its parts took from the buyer inside the shop is given
     * back, once.
     *
     * Under the same locks as the settlement, so that a cancellation and a settlement of the
     * same order, or two cancellations, run one after the other and the second reads what
     * the first wrote. The parts are walked in reverse position order, the way a refused
     * settlement takes them back:
     *
     * - an instant part that is `settled`: its module is asked to release it, then the part
     *   turns `released`, by an update on its primary key conditional on `settled`;
     * - a part that is `pending`, instant or not: nothing was taken, the part turns
     *   `released` without asking its module, and can no longer be settled, since only a
     *   pending part ever is;
     * - the part of a provider that is `settled` is left as it is: giving back what a
     *   provider charged is a refund, which goes through the provider and is not done here;
     * - a `released` part is left alone, which is what makes a cancellation replayed, or
     *   run twice at once, release nothing more.
     *
     * The module is called under the lock: instant modules are the shop's own means (gift
     * card, credit note), they answer at once, and calling them outside the lock would let
     * two cancellations both read the part as settled.
     *
     * A module that throws does not stop the cancellation, which is already written, nor the
     * release of the other parts: its part stays `settled`, which is what lets a later
     * cancellation of the same order try it again, and the failure is logged by part id,
     * without what the module said, which may carry a card number. That holds for a module
     * that keeps to its contract and never rolls back a transaction on the connection of the
     * core: one that does leaves the release unable to commit, whether it opened a nested
     * transaction first or rolled back the only level. The release then stops there, the
     * other modules are not asked to give back a part whose release would be rolled back, the
     * failure is logged with the ids of the parts their module had already given back, and a
     * \LogicException is thrown: nothing is announced.
     *
     * When the connection is already marked for rollback by a transaction someone else
     * opened, no module is asked at all, and the same exception is thrown.
     *
     * Each part a module gave back is announced after the commit; a pending part released
     * without asking its module took nothing, and is not announced.
     *
     * @throws \LogicException when the transaction cannot be committed
     */
    public function releasePartsOf(Order $order): void
    {
        $connection = Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        $released = [];

        try {
            $this->assertCommitableBeforeAskingModules($connection, (int) $order->getId());
            $this->lockTheOrder((int) $order->getId(), $connection);

            foreach (array_reverse($this->lockThePartsOf((int) $order->getId(), $connection)) as $part) {
                $wasCommitable = $this->isStillCommitable($connection);

                $givenBack = $this->release($part, $connection, $wasCommitable);

                if (null === $givenBack) {
                    $alreadyGivenBack = implode(', ', array_map(static fn (OrderPaymentPart $given): int => (int) $given->getId(), $released));
                    Tlog::getInstance()->error(\sprintf('The module of payment part %d of order %d rolled back a transaction it did not open: the release of the order cannot be committed, nothing was written for that part and nothing is announced. Parts their module gave back earlier in this pass, which the rollback puts back to settled and which their module will be asked to give back a second time at the next cancellation: [%s].', (int) $part->getId(), (int) $order->getId(), $alreadyGivenBack));

                    throw new \LogicException(\sprintf('The module of payment part %d of order %d rolled back a transaction it did not open: the release cannot be committed.', (int) $part->getId(), (int) $order->getId()));
                }

                if ($givenBack) {
                    $released[] = $part;
                }
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();
            OrderPaymentPartTableMap::clearInstancePool();

            throw $throwable;
        }

        OrderPaymentPartTableMap::clearInstancePool();

        foreach ($released as $part) {
            $this->dispatcher->dispatch(new OrderPaymentPartEvent($part), TheliaEvents::ORDER_PAYMENT_PART_RELEASE);
        }
    }

    /**
     * Whether its module gave the part back in this call. A pending part is released too,
     * without its module, and answers false: it took nothing. Null when the connection can
     * no longer commit once the module has answered: nothing is written then, since the
     * update of the state would run on a connection that has lost its transaction and its
     * locks, and commit on its own.
     */
    private function release(OrderPaymentPart $part, ConnectionInterface $connection, bool $wasCommitable): ?bool
    {
        $state = $part->getState();

        if (OrderPaymentPartState::PENDING->value !== $state) {
            if (OrderPaymentPartState::SETTLED->value !== $state || !$this->isInstant($part)) {
                return false;
            }

            $failure = null;

            try {
                $this->moduleOf($part)->releasePart($part);
            } catch (\Throwable $throwable) {
                $failure = $throwable;
            }

            if (!$this->isStillCommitableAfter($wasCommitable, $connection)) {
                return null;
            }

            if (null !== $failure) {
                Tlog::getInstance()->error(\sprintf('Payment part %d of order %d could not be released: %s', (int) $part->getId(), (int) $part->getOrderId(), $failure::class));

                return false;
            }
        }

        $updated = OrderPaymentPartQuery::create()
            ->filterById($part->getId())
            ->filterByState($state)
            ->update(['State' => OrderPaymentPartState::RELEASED->value, 'UpdatedAt' => new \DateTime()], $connection);

        OrderPaymentPartTableMap::clearInstancePool();

        if (1 !== $updated) {
            return false;
        }

        // The object says what the row says, for the listeners of the announcement.
        $part->setState(OrderPaymentPartState::RELEASED->value)->resetModified();

        return OrderPaymentPartState::SETTLED->value === $state;
    }

    private function isInsideTransaction(ConnectionInterface $connection): bool
    {
        return !$connection instanceof ConnectionWrapper || $connection->isInTransaction();
    }

    private function isStillCommitable(ConnectionInterface $connection): bool
    {
        return !$connection instanceof ConnectionWrapper || $connection->isCommitable();
    }

    /**
     * Whether the connection can still commit what this service wrote. Compared before and
     * after a module is called, since a transaction someone else opened may have been marked
     * already, which the module has nothing to do with.
     */
    private function isStillCommitableAfter(bool $wasCommitable, ConnectionInterface $connection): bool
    {
        return !$wasCommitable || $this->isStillCommitable($connection);
    }

    /**
     * A transaction someone else opened may be marked for rollback already: nothing written
     * here could be committed, and a module asked to settle or to give back a part would
     * have moved money for nothing.
     *
     * @throws \LogicException when the connection can no longer commit
     */
    private function assertCommitableBeforeAskingModules(ConnectionInterface $connection, int $orderId): void
    {
        if (!$this->isStillCommitable($connection)) {
            throw new \LogicException(\sprintf('The transaction around the payment parts of order %d is already marked for rollback: no payment module is asked.', $orderId));
        }
    }

    /**
     * @throws \LogicException when the module rolled back the transaction of the core
     */
    private function assertStillCommitable(bool $wasCommitable, ConnectionInterface $connection, OrderPaymentPart $part): void
    {
        if (!$this->isStillCommitableAfter($wasCommitable, $connection)) {
            Tlog::getInstance()->error(\sprintf('The module of payment part %d rolled back a transaction it did not open: the settlement of the order cannot be committed.', (int) $part->getId()));

            throw new \LogicException(\sprintf('The module of payment part %d settled it, then rolled back a transaction it did not open: the settlement cannot be committed.', (int) $part->getId()));
        }
    }

    private function hasParts(Order $order): bool
    {
        return OrderPaymentPartQuery::create()
            ->filterByOrderId($order->getId())
            ->exists(Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME));
    }

    private function primaryPartOf(Order $order): ?OrderPaymentPart
    {
        foreach ($this->partsOf($order) as $part) {
            if ($part->getIsPrimary()) {
                return $part;
            }
        }

        return null;
    }

    /**
     * @return list<OrderPaymentPart> in position order, as stored
     */
    private function partsOf(Order $order): array
    {
        OrderPaymentPartTableMap::clearInstancePool();

        return OrderPaymentPartQuery::create()
            ->filterByOrderId($order->getId())
            ->orderByPosition()
            ->orderById()
            ->find(Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME))
            ->getData();
    }

    /**
     * The order row locked by its primary key: the first statement of the transaction.
     *
     * @return OrderStatus|null the status the order holds, as stored; null when the order is gone
     */
    private function lockTheOrder(int $orderId, ConnectionInterface $connection): ?OrderStatus
    {
        $lockTheOrder = $connection->prepare('SELECT `status_id` FROM `order` WHERE `id` = :orderId FOR UPDATE');
        $lockTheOrder->bindValue(':orderId', $orderId, \PDO::PARAM_INT);
        $lockTheOrder->execute();
        $statusId = $lockTheOrder->fetchColumn();
        $lockTheOrder->closeCursor();

        // The statement wrapper of Propel answers an empty string for no row at all.
        return null === $statusId || '' === $statusId ? null : OrderStatusQuery::create()->findPk((int) $statusId, $connection);
    }

    /**
     * The parts of an order whose row is locked, locked by their primary keys.
     *
     * @return list<OrderPaymentPart> in position order, as stored, read under the lock
     */
    private function lockThePartsOf(int $orderId, ConnectionInterface $connection): array
    {
        // Before the parts are read, so that they are hydrated from the rows, not taken
        // from the pool.
        OrderPaymentPartTableMap::clearInstancePool();

        // A plain read, current since the order is locked: see the class comment.
        $ids = array_map(
            'intval',
            OrderPaymentPartQuery::create()
                ->filterByOrderId($orderId)
                ->select('Id')
                ->find($connection)
                ->getData(),
        );

        if ([] === $ids) {
            return [];
        }

        sort($ids);

        $parts = OrderPaymentPartQuery::create()
            ->filterById($ids)
            ->orderById()
            ->lockForUpdate()
            ->find($connection)
            ->getData();

        usort(
            $parts,
            static fn (OrderPaymentPart $a, OrderPaymentPart $b): int => [$a->getPosition(), $a->getId()] <=> [$b->getPosition(), $b->getId()],
        );

        return $parts;
    }

    /**
     * @param list<OrderPaymentPart> $settled
     */
    private function takeBack(array $settled, ConnectionInterface $connection): void
    {
        foreach (array_reverse($settled) as $part) {
            try {
                $this->moduleOf($part)->releasePart($part);
            } catch (\Throwable $throwable) {
                // The others are still given back; the part is left for whoever reads the log.
                Tlog::getInstance()->error(\sprintf('Payment part %d could not be released: %s', (int) $part->getId(), $throwable::class));
            }

            // By a query on its primary key rather than by the object, which may still hold
            // the change whose write failed; and never in the way of the rollback that follows.
            // Not on a connection with no transaction left: a module rolled back the only
            // level, the locks are gone, and the update would run in a transaction of its own
            // and could overwrite what another request has written since.
            if (!$this->isInsideTransaction($connection)) {
                $part->setState(OrderPaymentPartState::PENDING->value)->resetModified();

                continue;
            }

            try {
                OrderPaymentPartQuery::create()
                    ->filterById($part->getId())
                    ->update(['State' => OrderPaymentPartState::PENDING->value, 'UpdatedAt' => new \DateTime()], $connection);
            } catch (\Throwable $throwable) {
                Tlog::getInstance()->error(\sprintf('Payment part %d could not be put back to pending: %s', (int) $part->getId(), $throwable::class));
            }

            $part->setState(OrderPaymentPartState::PENDING->value)->resetModified();
        }
    }

    private function isInstant(OrderPaymentPart $part): bool
    {
        return !$part->getIsPrimary() || $this->instanceOf($part) instanceof InstantPaymentModuleInterface;
    }

    private function moduleOf(OrderPaymentPart $part): InstantPaymentModuleInterface
    {
        $instance = $this->instanceOf($part);

        if (!$instance instanceof InstantPaymentModuleInterface) {
            throw new \LogicException(\sprintf('The module of payment part %d is not an instant payment module.', (int) $part->getId()));
        }

        return $instance;
    }

    private function instanceOf(OrderPaymentPart $part): ?object
    {
        if (null === $part->getPaymentModuleId()) {
            return null;
        }

        try {
            return $part->getPaymentModule()?->getPaymentModuleInstance($this->container);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
