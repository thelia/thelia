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

namespace Thelia\Domain\Subscription;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Subscription\SubscriptionInstallmentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\CMS\Publication\PublicationMoment;
use Thelia\Domain\Order\Exception\OrderPlacementConflictException;
use Thelia\Domain\Subscription\Enum\ActorType;
use Thelia\Domain\Subscription\Enum\BillingOutcome;
use Thelia\Domain\Subscription\Enum\PeriodUnit;
use Thelia\Domain\Subscription\Enum\RecurringChargeOutcome;
use Thelia\Domain\Subscription\Enum\SubscriptionOrderState;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\SubscriptionOrderTableMap;
use Thelia\Model\Map\SubscriptionTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Subscription;
use Thelia\Model\SubscriptionOrder;
use Thelia\Model\SubscriptionOrderQuery;
use Thelia\Model\SubscriptionQuery;

/**
 * Bills the installment of a subscription that is due, once.
 *
 * In this order:
 *  1. a lock per subscription, never waited for: a billing already running keeps it;
 *  2. the subscription read again: nothing is done unless an installment is due; a
 *     cancelled subscription whose end has come is ended, without a charge;
 *  3. the row of the installment inserted, or read when it is there, then locked in a short
 *     transaction, in which the order of the installment is placed when the row has none
 *     (or only a cancelled one) and named on the row. The unique key of subscription_order
 *     on (subscription, installment number) is what keeps a second order from being placed
 *     for the same installment by a node that does not share the lock;
 *  4. the charge, outside any transaction, with a key that is the same for every attempt of
 *     the installment, so that a provider asked twice takes the money once;
 *  5. the outcome written: a paid order goes through ORDER_UPDATE_STATUS like any other
 *     (invoice number, stock, mails stay the core's) and the period moves on; a declined
 *     charge is tried again later, and the subscription is suspended, its installment order
 *     cancelled (the stock given back), once the attempts run out. A provider that could
 *     not be reached is tried again within the hour and does not count as declined.
 *
 * No session and no request: the language of the subscription is set on the translator
 * while the order changes status, for whatever renders a text then.
 */
final readonly class SubscriptionInstallmentBiller
{
    private const int ERROR_MAXIMUM_LENGTH = 255;

    public function __construct(
        private SubscriptionBillingLock $lock,
        private SubscriptionStateMachine $stateMachine,
        private InstallmentOrderPlacer $orderPlacer,
        private RecurringPaymentModuleResolver $moduleResolver,
        private BillingCalendar $calendar,
        private SubscriptionRetryPolicy $retryPolicy,
        private EventDispatcherInterface $dispatcher,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public static function idempotencyKeyOf(int $subscriptionId, int $installmentNumber): string
    {
        return \sprintf('subscription-%d-%d', $subscriptionId, $installmentNumber);
    }

    public function bill(int $subscriptionId): BillingOutcome
    {
        $lock = $this->lock->acquire($subscriptionId);

        if (null === $lock) {
            return BillingOutcome::Busy;
        }

        try {
            return $this->billUnderLock($subscriptionId);
        } finally {
            $lock->release();
        }
    }

    private function billUnderLock(int $subscriptionId): BillingOutcome
    {
        // Read from the database, not from the pool: another process may have moved it.
        SubscriptionTableMap::clearInstancePool();
        $subscription = SubscriptionQuery::create()->findPk($subscriptionId);

        if (!$subscription instanceof Subscription) {
            return BillingOutcome::NothingDue;
        }

        $now = $this->now();
        $status = SubscriptionStatus::from((string) $subscription->getStatus());

        if (SubscriptionStatus::Cancelled === $status && self::reached($subscription->getCancelAt(), $now)) {
            return $this->end($subscription, 'The cancellation took effect at the end of the paid period.');
        }

        if (!$this->isDue($subscription, $status, $now)) {
            return BillingOutcome::NothingDue;
        }

        if ($this->everyInstallmentIsBilled($subscription)) {
            if (SubscriptionStatus::Cancelled !== $status) {
                $subscription = $this->stateMachine->transition($subscription, SubscriptionStatus::Cancelled, ActorType::System, null, 'Every installment is billed.', [
                    'CancelAt' => self::mutable($now),
                ]);
            }

            return $this->end($subscription, 'Every installment is billed.');
        }

        $installmentNumber = (int) $subscription->getInstallmentsBilled() + 1;

        try {
            [$installment, $order] = $this->placeInstallment($subscription, $installmentNumber);
        } catch (OrderPlacementConflictException $conflict) {
            $this->logger->warning(\sprintf('The order of installment %d of subscription %d met another order placed at the same moment: tried again later.', $installmentNumber, $subscriptionId));

            return $this->recordFailure($subscription, $this->installmentRow($subscriptionId, $installmentNumber), null, RecurringChargeResult::retryable('The order met another order placed at the same moment.'));
        } catch (\Throwable $throwable) {
            $this->logger->error(\sprintf('The order of installment %d of subscription %d could not be placed: %s', $installmentNumber, $subscriptionId, $throwable->getMessage()));

            return $this->recordFailure($subscription, $this->installmentRow($subscriptionId, $installmentNumber), null, RecurringChargeResult::declined('The order of the installment could not be placed: '.$throwable->getMessage()));
        }

        if (!$order instanceof Order || SubscriptionOrderState::Paid->value === $installment->getState()) {
            return BillingOutcome::AlreadyPaid;
        }

        // Paid meanwhile outside the subscription (the notification of the provider, the
        // back office): recorded as paid, never charged again.
        if ($order->isPaid(false)) {
            return $this->recordPaid($subscription, $installment, $order, RecurringChargeResult::paid('order already paid'), false);
        }

        $result = $this->charge($subscription, $order, $installmentNumber);

        return $result->isPaid()
            ? $this->recordPaid($subscription, $installment, $order, $result, true)
            : $this->recordFailure($subscription, $installment, $order, $result);
    }

    private function isDue(Subscription $subscription, SubscriptionStatus $status, \DateTimeImmutable $now): bool
    {
        return match ($status) {
            SubscriptionStatus::Trial, SubscriptionStatus::Active => self::reached($subscription->getNextBillingAt(), $now),
            // A suspended subscription is billed again only once someone reactivates it.
            SubscriptionStatus::PaymentFailed, SubscriptionStatus::Suspended => self::reached($subscription->getNextRetryAt(), $now),
            // Cancelled during its commitment: the committed installments are still billed.
            SubscriptionStatus::Cancelled => self::reached($subscription->getNextRetryAt(), $now)
                || (self::reached($subscription->getNextBillingAt(), $now)
                    && null !== $subscription->getCancelAt()
                    && $subscription->getNextBillingAt() < $subscription->getCancelAt()),
            SubscriptionStatus::Paused, SubscriptionStatus::Ended => false,
        };
    }

    private function everyInstallmentIsBilled(Subscription $subscription): bool
    {
        return null !== $subscription->getInstallmentCount()
            && (int) $subscription->getInstallmentsBilled() >= (int) $subscription->getInstallmentCount();
    }

    /**
     * @return array{SubscriptionOrder, ?Order} no order when the installment is paid already
     */
    private function placeInstallment(Subscription $subscription, int $installmentNumber): array
    {
        $connection = Propel::getConnection(SubscriptionOrderTableMap::DATABASE_NAME);
        $now = self::mutable($this->now())->format('Y-m-d H:i:s');

        // Inserted alone, before the transaction: a duplicate is not an error and leaves no
        // transaction unable to commit. The unique key decides between two billings.
        $insert = $connection->prepare(
            'INSERT INTO `subscription_order` (`subscription_id`, `installment_number`, `state`, `attempt_count`, `created_at`, `updated_at`)
             VALUES (:subscription, :installment, :state, 0, :created, :updated)
             ON DUPLICATE KEY UPDATE `id` = `id`',
        );
        $insert->bindValue(':subscription', (int) $subscription->getId(), \PDO::PARAM_INT);
        $insert->bindValue(':installment', $installmentNumber, \PDO::PARAM_INT);
        $insert->bindValue(':state', SubscriptionOrderState::Pending->value);
        $insert->bindValue(':created', $now);
        $insert->bindValue(':updated', $now);
        $insert->execute();

        $connection->beginTransaction();

        try {
            $installment = $this->lockedInstallmentRow((int) $subscription->getId(), $installmentNumber, $connection);

            if (SubscriptionOrderState::Paid->value === $installment->getState()) {
                $connection->commit();

                return [$installment, null];
            }

            $order = null === $installment->getOrderId() ? null : OrderQuery::create()->findPk($installment->getOrderId(), $connection);

            // A cancelled order gave its stock back and is never paid: an installment tried
            // again after a suspension gets an order of its own, and the row names it.
            if (!$order instanceof Order || $order->isCancelled(false)) {
                $order = $this->orderPlacer->place($subscription, $connection);
                $installment->setOrderId($order->getId())->save($connection);
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        return [$installment, $order];
    }

    private function lockedInstallmentRow(int $subscriptionId, int $installmentNumber, ConnectionInterface $connection): SubscriptionOrder
    {
        $select = $connection->prepare(
            'SELECT `id` FROM `subscription_order` WHERE `subscription_id` = :subscription AND `installment_number` = :installment ORDER BY `id` DESC LIMIT 1 FOR UPDATE',
        );
        $select->bindValue(':subscription', $subscriptionId, \PDO::PARAM_INT);
        $select->bindValue(':installment', $installmentNumber, \PDO::PARAM_INT);
        $select->execute();
        $id = $select->fetchColumn();

        SubscriptionOrderTableMap::clearInstancePool();
        $installment = false === $id ? null : SubscriptionOrderQuery::create()->findPk((int) $id, $connection);

        if (!$installment instanceof SubscriptionOrder) {
            throw new \LogicException(\sprintf('The row of installment %d of subscription %d vanished.', $installmentNumber, $subscriptionId));
        }

        return $installment;
    }

    private function installmentRow(int $subscriptionId, int $installmentNumber): ?SubscriptionOrder
    {
        SubscriptionOrderTableMap::clearInstancePool();

        return SubscriptionOrderQuery::create()
            ->filterBySubscriptionId($subscriptionId)
            ->filterByInstallmentNumber($installmentNumber)
            ->findOne();
    }

    private function charge(Subscription $subscription, Order $order, int $installmentNumber): RecurringChargeResult
    {
        $module = $this->moduleResolver->chargingModule((int) $subscription->getPaymentModuleId());

        if (null === $module) {
            return RecurringChargeResult::declined('The payment module of the subscription cannot charge installments.');
        }

        $method = null === $subscription->getCustomerPaymentMethodId() ? null : CustomerPaymentMethodQuery::create()
            ->filterById($subscription->getCustomerPaymentMethodId())
            ->filterByCustomerId($subscription->getCustomerId())
            ->filterByPaymentModuleId($subscription->getPaymentModuleId())
            ->findOne();

        if (!$method instanceof CustomerPaymentMethod) {
            return RecurringChargeResult::declined('The subscription has no stored payment method to charge.');
        }

        if ($method->isExpiredOn($this->now())) {
            return RecurringChargeResult::declined('The stored payment method of the subscription has expired.');
        }

        try {
            return $module->chargeOffSession($order, $method, self::idempotencyKeyOf((int) $subscription->getId(), $installmentNumber));
        } catch (\Throwable $throwable) {
            // The contract asks for an answer, not an exception: a module that throws is
            // treated as a provider out of reach, and its message is not kept (it may quote
            // the token).
            $this->logger->error(\sprintf('The payment module threw %s while charging installment %d of subscription %d.', $throwable::class, $installmentNumber, (int) $subscription->getId()));

            return RecurringChargeResult::retryable(\sprintf('The payment module failed (%s).', $throwable::class));
        }
    }

    private function recordPaid(Subscription $subscription, SubscriptionOrder $installment, Order $order, RecurringChargeResult $result, bool $charged): BillingOutcome
    {
        $statusError = null;

        if (!$order->isPaid(false)) {
            try {
                $this->changeOrderStatus($subscription, $order, (int) OrderStatusQuery::getPaidStatus()?->getId());
            } catch (\Throwable $throwable) {
                // The money is taken: the installment is paid whatever the order says, and the
                // merchant is told through the row and the log.
                $statusError = 'Charged, but the order could not be set as paid: '.$throwable->getMessage();
                $this->logger->critical(\sprintf('Installment %d of subscription %d is charged but its order %d could not be set as paid: %s', (int) $installment->getInstallmentNumber(), (int) $subscription->getId(), (int) $order->getId(), $throwable->getMessage()));
            }
        }

        $installment
            ->setState(SubscriptionOrderState::Paid->value)
            ->setAttemptCount((int) $installment->getAttemptCount() + ($charged ? 1 : 0))
            ->setLastError(self::shortened($statusError))
            ->save();

        $periodStart = PublicationMoment::atTheSecondOrNull($subscription->getCurrentPeriodEnd()) ?? $this->now();
        $periodEnd = $this->calendar->periodEnd(
            $periodStart,
            PeriodUnit::from((string) $subscription->getPeriodUnit()),
            max(1, (int) $subscription->getPeriodCount()),
            (int) $subscription->getBillingAnchorDay(),
        );

        $changes = [
            'InstallmentsBilled' => (int) $subscription->getInstallmentsBilled() + 1,
            'CurrentPeriodStart' => self::mutable($periodStart),
            'CurrentPeriodEnd' => self::mutable($periodEnd),
            'NextBillingAt' => self::mutable($periodEnd),
            'FailureCount' => 0,
            'NextRetryAt' => null,
        ];

        $status = SubscriptionStatus::from((string) $subscription->getStatus());

        if (\in_array($status, [SubscriptionStatus::Trial, SubscriptionStatus::PaymentFailed, SubscriptionStatus::Suspended], true)) {
            $subscription = $this->stateMachine->transition($subscription, SubscriptionStatus::Active, ActorType::System, null, \sprintf('Installment %d paid.', (int) $installment->getInstallmentNumber()), $changes);
        } else {
            $subscription = $this->write($subscription, $changes);
        }

        $this->dispatcher->dispatch(new SubscriptionInstallmentEvent($subscription, $installment, $result, $order), TheliaEvents::SUBSCRIPTION_INSTALLMENT_PAID);

        return BillingOutcome::Paid;
    }

    private function recordFailure(Subscription $subscription, ?SubscriptionOrder $installment, ?Order $order, RecurringChargeResult $result): BillingOutcome
    {
        $now = $this->now();
        $retryable = RecurringChargeOutcome::Retryable === $result->outcome;
        $failureCount = (int) $subscription->getFailureCount() + ($retryable ? 0 : 1);
        $exhausted = !$retryable && $failureCount >= $this->retryPolicy->attempts();
        $retryAt = $retryable
            ? $now->modify(\sprintf('+%d seconds', SubscriptionRetryPolicy::UNREACHABLE_PROVIDER_DELAY_SECONDS))
            : $now->modify(\sprintf('+%d days', $this->retryPolicy->intervalDays()));

        if ($installment instanceof SubscriptionOrder) {
            $installment
                ->setState(SubscriptionOrderState::Failed->value)
                ->setAttemptCount((int) $installment->getAttemptCount() + (null === $order ? 0 : 1))
                ->setLastError(self::shortened($result->reason))
                ->save();
        }

        $status = SubscriptionStatus::from((string) $subscription->getStatus());
        $note = \sprintf('Installment %d: %s', null === $installment ? (int) $subscription->getInstallmentsBilled() + 1 : (int) $installment->getInstallmentNumber(), (string) $result->reason);
        $outcome = $retryable ? BillingOutcome::Retryable : BillingOutcome::Failed;

        if ($exhausted && \in_array($status, [SubscriptionStatus::Trial, SubscriptionStatus::Active, SubscriptionStatus::PaymentFailed, SubscriptionStatus::Suspended], true)) {
            if (SubscriptionStatus::PaymentFailed !== $status && SubscriptionStatus::Suspended !== $status) {
                $subscription = $this->stateMachine->transition($subscription, SubscriptionStatus::PaymentFailed, ActorType::System, null, $note);
            }

            $changes = ['FailureCount' => $failureCount, 'NextRetryAt' => null];
            $subscription = SubscriptionStatus::Suspended === $status
                ? $this->write($subscription, $changes)
                : $this->stateMachine->transition($subscription, SubscriptionStatus::Suspended, ActorType::System, null, 'The declined charges ran out.', $changes);

            $this->cancelInstallmentOrder($subscription, $order);
            $outcome = BillingOutcome::Suspended;
        } elseif ($exhausted && SubscriptionStatus::Cancelled === $status) {
            // Cancelled during its commitment and no longer paying: it ends now, the debt is
            // the merchant's to pursue.
            $this->cancelInstallmentOrder($subscription, $order);
            $this->write($subscription, ['FailureCount' => $failureCount, 'NextRetryAt' => null, 'CancelAt' => self::mutable($now)]);
            $outcome = $this->end($subscription, 'The declined charges ran out during the commitment.');
        } else {
            $changes = ['FailureCount' => $failureCount, 'NextRetryAt' => self::mutable($retryAt)];
            $subscription = \in_array($status, [SubscriptionStatus::Trial, SubscriptionStatus::Active], true)
                ? $this->stateMachine->transition($subscription, SubscriptionStatus::PaymentFailed, ActorType::System, null, $note, $changes)
                : $this->write($subscription, SubscriptionStatus::Suspended === $status ? ['FailureCount' => $failureCount, 'NextRetryAt' => null] : $changes);

            if (SubscriptionStatus::Suspended === $status) {
                $this->cancelInstallmentOrder($subscription, $order);
            }
        }

        if ($installment instanceof SubscriptionOrder) {
            $event = new SubscriptionInstallmentEvent($subscription, $installment, $result);

            if ($order instanceof Order) {
                $event->setOrder($order);
            }

            $this->dispatcher->dispatch($event, TheliaEvents::SUBSCRIPTION_INSTALLMENT_FAILED);
        }

        return $outcome;
    }

    /**
     * Ends a cancelled subscription. An installment left unpaid has its order cancelled:
     * nothing more is charged, and the stock comes back.
     */
    private function end(Subscription $subscription, string $note): BillingOutcome
    {
        $pending = $this->installmentRow((int) $subscription->getId(), (int) $subscription->getInstallmentsBilled() + 1);

        if ($pending instanceof SubscriptionOrder && SubscriptionOrderState::Paid->value !== $pending->getState() && null !== $pending->getOrderId()) {
            $this->cancelInstallmentOrder($subscription, OrderQuery::create()->findPk($pending->getOrderId()));
        }

        $this->stateMachine->transition($subscription, SubscriptionStatus::Ended, ActorType::System, null, $note, [
            'NextBillingAt' => null,
            'NextRetryAt' => null,
        ]);

        return BillingOutcome::Ended;
    }

    private function cancelInstallmentOrder(Subscription $subscription, ?Order $order): void
    {
        if (!$order instanceof Order || $order->isCancelled(false) || $order->isPaid(false)) {
            return;
        }

        $this->changeOrderStatus($subscription, $order, (int) OrderStatusQuery::getCancelledStatus()?->getId());
    }

    /**
     * Through ORDER_UPDATE_STATUS, never written to the row, in the language of the
     * subscription: there is no session to take it from.
     */
    private function changeOrderStatus(Subscription $subscription, Order $order, int $statusId): void
    {
        $translator = Translator::getInstance();
        $previousLocale = $translator->getLocale();
        $locale = LangQuery::create()->findPk($subscription->getLangId())?->getLocale();

        if (null !== $locale) {
            $translator->setLocale($locale);
        }

        try {
            $this->dispatcher->dispatch((new OrderEvent($order))->setStatus($statusId), TheliaEvents::ORDER_UPDATE_STATUS);
        } finally {
            $translator->setLocale($previousLocale);
        }
    }

    /**
     * Writes the columns of a subscription that does not change status.
     *
     * @param array<string, mixed> $changes by phpName
     */
    private function write(Subscription $subscription, array $changes): Subscription
    {
        SubscriptionQuery::create()
            ->filterById($subscription->getId())
            ->update($changes + ['UpdatedAt' => self::mutable($this->now())]);

        SubscriptionTableMap::removeInstanceFromPool($subscription);
        $subscription->reload();

        return $subscription;
    }

    private function now(): \DateTimeImmutable
    {
        return PublicationMoment::atTheSecond($this->clock->now());
    }

    private static function reached(?\DateTimeInterface $moment, \DateTimeImmutable $now): bool
    {
        return null !== $moment && $moment <= $now;
    }

    private static function mutable(\DateTimeImmutable $moment): \DateTime
    {
        return \DateTime::createFromImmutable($moment);
    }

    private static function shortened(?string $text): ?string
    {
        return null === $text ? null : mb_substr($text, 0, self::ERROR_MAXIMUM_LENGTH);
    }
}
