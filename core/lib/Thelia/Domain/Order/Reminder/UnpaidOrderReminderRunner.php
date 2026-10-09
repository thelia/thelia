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

namespace Thelia\Domain\Order\Reminder;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\MessageQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Tools\URL;

/**
 * Applies the reminder schedule to the orders waiting for their payment, one order and
 * one step at a time. Each order is in the last step it reached; that step is sent once
 * (the order history says whether it was, or whether it failed), and the steps it is
 * already past are never sent late. A cancellation goes through the status change any
 * cancellation goes through, which gives the stock back.
 *
 * Bounded: a run acts on at most $limit orders, oldest first, and the next run goes on.
 * Meant for a scheduled task; two runs at once are kept apart by the command's lock.
 */
final readonly class UnpaidOrderReminderRunner
{
    /**
     * The route of the front theme that hands an unpaid order back to the checkout, its
     * token as the "token" parameter.
     */
    public const PAYMENT_ROUTE = 'order_payment_resume';

    /**
     * How long the link of a reminder is accepted when the schedule ends without a
     * cancellation; with one, the link dies with the order.
     */
    public const LINK_LIFETIME_WITHOUT_CANCELLATION_IN_SECONDS = self::LAST_REMINDER_WINDOW_IN_HOURS * 3600;

    /**
     * How long, after the last step, an order of a schedule without cancellation is still
     * read: the link of its last mail is accepted that long.
     */
    public const LAST_REMINDER_WINDOW_IN_HOURS = 720;

    private const PAGE_SIZE = 100;

    public function __construct(
        private UnpaidOrderReminderSettings $settings,
        private UnpaidOrderPaymentLink $paymentLink,
        private MailerFactory $mailer,
        private EventDispatcherInterface $dispatcher,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function run(\DateTimeImmutable $now, int $limit, bool $dryRun): UnpaidOrderReminderReport
    {
        $report = new UnpaidOrderReminderReport();
        $schedule = $this->settings->schedule();
        $statusIds = $this->awaitingPaymentStatusIds();

        if ($schedule->isEmpty() || [] === $statusIds || $limit < 1) {
            return $report;
        }

        // A step naming a message the shop does not have waits for it: the orders in that
        // step are left as they are, not marked as failed for good.
        $missingMessages = $this->missingMessages($schedule);
        $report->setMissingMessages($missingMessages);

        if ([] !== $missingMessages) {
            Tlog::getInstance()->error(\sprintf('Unpaid order reminder: no mail message is named %s, the steps sending it wait.', implode(', ', $missingMessages)));
        }

        $lastOrderId = 0;

        do {
            $orders = $this->candidates($now, $schedule, $statusIds, $lastOrderId);
            $handled = $this->stepsAlreadyHandled(array_map(static fn (Order $order): int => (int) $order->getId(), $orders));

            foreach ($orders as $order) {
                $lastOrderId = (int) $order->getId();
                $ageInHours = intdiv($now->getTimestamp() - $order->getCreatedAt()->getTimestamp(), 3600);
                $step = $schedule->stepReachedAfter($ageInHours);

                if (null === $step || \in_array($step->delayInHours, $handled[$lastOrderId] ?? [], true) || \in_array($step->messageCode, $missingMessages, true)) {
                    continue;
                }

                $outcome = $dryRun ? $this->planned($order, $step) : $this->apply($order, $step, $schedule, $now);

                if (null === $outcome) {
                    continue;
                }

                $report->add($outcome);

                if (\count($report->outcomes()) >= $limit) {
                    return $report;
                }
            }

            OrderTableMap::clearInstancePool();
        } while (self::PAGE_SIZE === \count($orders));

        return $report;
    }

    /**
     * Read again under a lock of the order row, since the run read it: a payment may have
     * come in while the run was busy with the orders before it, and the object read then
     * still says unpaid. Nothing is done to an order that no longer waits, nor twice.
     *
     * A mail is claimed before it is sent: the history entry is written, then the mail
     * goes; a mail that cannot leave turns the entry into a failed one. Sent at most
     * once, even when the database fails right after the mail.
     *
     * @return UnpaidOrderReminderOutcome|null null when the order needs nothing any more
     */
    private function apply(Order $order, UnpaidOrderReminderStep $step, UnpaidOrderReminderSchedule $schedule, \DateTimeImmutable $now): ?UnpaidOrderReminderOutcome
    {
        $orderId = (int) $order->getId();
        $connection = Propel::getWriteConnection(OrderTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            if (!$this->stillAwaitsThisStep($orderId, $step, $connection)) {
                $connection->commit();

                return null;
            }

            OrderTableMap::removeInstanceFromPool($order);
            $order = OrderQuery::create()->findPk($orderId, $connection) ?? throw new \RuntimeException('the order is gone');

            if ($step->isCancellation()) {
                $order->setCancelled($this->dispatcher);
                $connection->commit();
            } else {
                $customer = $order->getCustomer();

                if (null === $customer || null !== $customer->getAnonymizedAt()) {
                    throw new \RuntimeException('the customer has no address to write to any more');
                }

                $claim = $this->writeEntry($orderId, OrderHistoryEventType::PAYMENT_REMINDER_SENT, ['step' => $step->delayInHours, 'message' => $step->messageCode], null, $connection);
                $connection->commit();
            }
        } catch (\Throwable $failure) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return $this->failed($order, $step, $failure->getMessage());
        }

        if ($step->isCancellation()) {
            OrderTableMap::removeInstanceFromPool($order);

            return true === OrderQuery::create()->findPk($orderId)?->getOrderStatus()?->isCancelled(true)
                ? $this->outcome($order, $step, UnpaidOrderReminderOutcome::STATUS_DONE)
                : $this->failed($order, $step, 'the order did not move to cancelled');
        }

        try {
            $this->mailer->sendEmailToCustomerOrFail((string) $step->messageCode, $customer, [
                'order_id' => $orderId,
                'order_ref' => (string) $order->getRef(),
                'payment_url' => $this->paymentUrl($order, $this->linkExpiry($order, $schedule, $now)),
            ]);
        } catch (\Throwable $failure) {
            try {
                $claim->delete();
            } catch (\Throwable $deletion) {
                Tlog::getInstance()->error(\sprintf('Unpaid order reminder: the mail of order %s did not leave, and its history entry says it did: %s', (string) $order->getRef(), $deletion->getMessage()));
            }

            return $this->failed($order, $step, $failure->getMessage());
        }

        return $this->outcome($order, $step, UnpaidOrderReminderOutcome::STATUS_DONE);
    }

    private function stillAwaitsThisStep(int $orderId, UnpaidOrderReminderStep $step, ConnectionInterface $connection): bool
    {
        $statement = $connection->prepare('SELECT `status_id` FROM `order` WHERE `id` = :id FOR UPDATE');
        $statement->execute([':id' => $orderId]);
        $statusId = $statement->fetchColumn();

        // Propel answers '' rather than false when no row matches.
        if (!is_numeric($statusId) || true !== OrderStatusQuery::create()->findPk((int) $statusId, $connection)?->isNotPaid(true)) {
            return false;
        }

        return !\in_array($step->delayInHours, $this->stepsAlreadyHandled([$orderId], $connection)[$orderId] ?? [], true);
    }

    /**
     * A step that failed is written down and not tried again: retried at every run, an
     * address the mailer refuses would take a place in every batch for good.
     */
    private function failed(Order $order, UnpaidOrderReminderStep $step, string $reason): UnpaidOrderReminderOutcome
    {
        Tlog::getInstance()->error(\sprintf('Unpaid order reminder: the %d hours step of order %s failed: %s', $step->delayInHours, (string) $order->getRef(), $reason));

        try {
            $this->writeEntry((int) $order->getId(), OrderHistoryEventType::PAYMENT_REMINDER_FAILED, ['step' => $step->delayInHours], $reason, Propel::getWriteConnection(OrderTableMap::DATABASE_NAME));
        } catch (\Throwable $failure) {
            Tlog::getInstance()->error(\sprintf('Unpaid order reminder: the failure of order %s could not be written: %s', (string) $order->getRef(), $failure->getMessage()));
        }

        return $this->outcome($order, $step, UnpaidOrderReminderOutcome::STATUS_FAILED, $reason);
    }

    /**
     * Written here rather than through OrderHistoryRecorder, which swallows its failures:
     * the entry is what says a step was done, a write that did not happen must be known.
     *
     * @param array<string, int|string|null> $payload
     */
    private function writeEntry(int $orderId, OrderHistoryEventType $type, array $payload, ?string $comment, ConnectionInterface $connection): OrderHistory
    {
        $entry = (new OrderHistory())
            ->setOrderId($orderId)
            ->setEventType($type->value)
            ->setActorType(OrderHistoryActorType::SYSTEM->value)
            ->setPayload(json_encode($payload, \JSON_THROW_ON_ERROR))
            ->setComment(null === $comment ? null : mb_substr($comment, 0, 1000))
            ->setVisibleToCustomer(0);
        $entry->save($connection);

        return $entry;
    }

    /**
     * @return list<string>
     */
    private function missingMessages(UnpaidOrderReminderSchedule $schedule): array
    {
        $missing = [];

        foreach ($schedule->steps() as $step) {
            if (!$step->isCancellation() && null === MessageQuery::create()->findOneByName($step->messageCode)) {
                $missing[] = (string) $step->messageCode;
            }
        }

        return array_values(array_unique($missing));
    }

    private function planned(Order $order, UnpaidOrderReminderStep $step): UnpaidOrderReminderOutcome
    {
        return $this->outcome($order, $step, UnpaidOrderReminderOutcome::STATUS_PLANNED);
    }

    private function outcome(Order $order, UnpaidOrderReminderStep $step, string $status, ?string $error = null): UnpaidOrderReminderOutcome
    {
        return new UnpaidOrderReminderOutcome(
            (int) $order->getId(),
            (string) $order->getRef(),
            $step->delayInHours,
            $step->isCancellation() ? UnpaidOrderReminderOutcome::ACTION_CANCEL : UnpaidOrderReminderOutcome::ACTION_REMIND,
            $status,
            $error,
        );
    }

    /**
     * The link dies when the order is cancelled, or a month after it was sent.
     */
    private function linkExpiry(Order $order, UnpaidOrderReminderSchedule $schedule, \DateTimeImmutable $now): int
    {
        $steps = $schedule->steps();
        $last = $steps[\count($steps) - 1];

        return $last->isCancellation()
            ? $order->getCreatedAt()->getTimestamp() + $last->delayInHours * 3600
            : $now->getTimestamp() + self::LINK_LIFETIME_WITHOUT_CANCELLATION_IN_SECONDS;
    }

    private function paymentUrl(Order $order, int $expiresAt): string
    {
        $token = $this->paymentLink->createToken($order, $expiresAt);

        try {
            return $this->urlGenerator->generate(self::PAYMENT_ROUTE, ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RouteNotFoundException) {
            // A front theme without the route: the mail still reminds, and leads to the shop.
            Tlog::getInstance()->warning(\sprintf('The front theme has no "%s" route: the payment reminder of order %s links to the home page.', self::PAYMENT_ROUTE, (string) $order->getRef()));

            return URL::getInstance()->absoluteUrl('/');
        }
    }

    /**
     * Every status that means "waiting for its payment", the custom ones declared
     * equivalent to not paid included.
     *
     * @return list<int>
     */
    private function awaitingPaymentStatusIds(): array
    {
        $ids = [];

        foreach (OrderStatusQuery::create()->find() as $status) {
            if ($status->isNotPaid(true)) {
                $ids[] = (int) $status->getId();
            }
        }

        return $ids;
    }

    /**
     * Orders waiting for their payment and old enough for the first step. Without a
     * cancellation, an unpaid order stays unpaid for good: once its last reminder could
     * no longer be paid from, the run stops reading it.
     *
     * @param list<int> $statusIds
     *
     * @return list<Order>
     */
    private function candidates(\DateTimeImmutable $now, UnpaidOrderReminderSchedule $schedule, array $statusIds, int $afterOrderId): array
    {
        $steps = $schedule->steps();
        $last = $steps[\count($steps) - 1];
        $createdAt = ['max' => $now->modify(\sprintf('-%d hours', (int) $schedule->firstDelayInHours()))];

        if (!$last->isCancellation()) {
            $createdAt['min'] = $now->modify(\sprintf('-%d hours', $last->delayInHours + self::LAST_REMINDER_WINDOW_IN_HOURS));
        }

        $query = OrderQuery::create()
            ->filterByStatusId($statusIds, Criteria::IN)
            ->filterByCreatedAt($createdAt)
            ->filterById($afterOrderId, Criteria::GREATER_THAN)
            ->orderById()
            ->limit(self::PAGE_SIZE);

        $excludedCodes = $this->settings->excludedPaymentModuleCodes();

        if ([] !== $excludedCodes) {
            $excludedIds = ModuleQuery::create()->filterByCode($excludedCodes, Criteria::IN)->select(['Id'])->find()->toArray();

            if ([] !== $excludedIds) {
                $query->filterByPaymentModuleId(array_map('intval', $excludedIds), Criteria::NOT_IN);
            }
        }

        return iterator_to_array($query->find(), false);
    }

    /**
     * The steps already sent or failed, by order: a step is handled once.
     *
     * @param list<int> $orderIds
     *
     * @return array<int, list<int>>
     */
    private function stepsAlreadyHandled(array $orderIds, ?ConnectionInterface $connection = null): array
    {
        if ([] === $orderIds) {
            return [];
        }

        $handled = [];
        $entries = OrderHistoryQuery::create()
            ->filterByOrderId($orderIds, Criteria::IN)
            ->filterByEventType([OrderHistoryEventType::PAYMENT_REMINDER_SENT->value, OrderHistoryEventType::PAYMENT_REMINDER_FAILED->value], Criteria::IN)
            ->find($connection);

        foreach ($entries as $entry) {
            $payload = json_decode((string) $entry->getPayload(), true);

            if (\is_array($payload) && isset($payload['step'])) {
                $handled[(int) $entry->getOrderId()][] = (int) $payload['step'];
            }
        }

        return $handled;
    }
}
