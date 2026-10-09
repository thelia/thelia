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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
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
    public const LINK_LIFETIME_WITHOUT_CANCELLATION_IN_SECONDS = 2592000;

    private const PAGE_SIZE = 100;

    public function __construct(
        private UnpaidOrderReminderSettings $settings,
        private UnpaidOrderPaymentLink $paymentLink,
        private MailerFactory $mailer,
        private OrderHistoryRecorder $history,
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

        $lastOrderId = 0;

        do {
            $orders = $this->candidates($now, (int) $schedule->firstDelayInHours(), $statusIds, $lastOrderId);
            $handled = $this->stepsAlreadyHandled(array_map(static fn (Order $order): int => (int) $order->getId(), $orders));

            foreach ($orders as $order) {
                $lastOrderId = (int) $order->getId();
                $ageInHours = intdiv($now->getTimestamp() - $order->getCreatedAt()->getTimestamp(), 3600);
                $step = $schedule->stepReachedAfter($ageInHours);

                if (null === $step || \in_array($step->delayInHours, $handled[$lastOrderId] ?? [], true)) {
                    continue;
                }

                $report->add($dryRun ? $this->planned($order, $step) : $this->apply($order, $step, $schedule, $now));

                if (\count($report->outcomes()) >= $limit) {
                    return $report;
                }
            }

            OrderTableMap::clearInstancePool();
        } while (self::PAGE_SIZE === \count($orders));

        return $report;
    }

    private function apply(Order $order, UnpaidOrderReminderStep $step, UnpaidOrderReminderSchedule $schedule, \DateTimeImmutable $now): UnpaidOrderReminderOutcome
    {
        try {
            if ($step->isCancellation()) {
                $order->setCancelled($this->dispatcher);

                if (true !== OrderQuery::create()->findPk($order->getId())?->getOrderStatus()?->isCancelled(true)) {
                    throw new \RuntimeException('the order did not move to cancelled');
                }
            } else {
                $this->mailer->sendEmailToCustomerOrFail((string) $step->messageCode, $order->getCustomer(), [
                    'order_id' => (int) $order->getId(),
                    'order_ref' => (string) $order->getRef(),
                    'payment_url' => $this->paymentUrl($order, $this->linkExpiry($order, $schedule, $now)),
                ]);
                $this->history->record((int) $order->getId(), OrderHistoryEventType::PAYMENT_REMINDER_SENT->value, ['step' => $step->delayInHours, 'message' => $step->messageCode]);
            }
        } catch (\Throwable $failure) {
            Tlog::getInstance()->error(\sprintf('Unpaid order reminder: the %d hours step of order %s failed: %s', $step->delayInHours, (string) $order->getRef(), $failure->getMessage()));
            $this->history->record((int) $order->getId(), OrderHistoryEventType::PAYMENT_REMINDER_FAILED->value, ['step' => $step->delayInHours], $failure->getMessage());

            return $this->outcome($order, $step, UnpaidOrderReminderOutcome::STATUS_FAILED, $failure->getMessage());
        }

        return $this->outcome($order, $step, UnpaidOrderReminderOutcome::STATUS_DONE);
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
     * @param list<int> $statusIds
     *
     * @return list<Order>
     */
    private function candidates(\DateTimeImmutable $now, int $firstDelayInHours, array $statusIds, int $afterOrderId): array
    {
        $query = OrderQuery::create()
            ->filterByStatusId($statusIds, Criteria::IN)
            ->filterByCreatedAt($now->modify(\sprintf('-%d hours', $firstDelayInHours)), Criteria::LESS_EQUAL)
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
    private function stepsAlreadyHandled(array $orderIds): array
    {
        if ([] === $orderIds) {
            return [];
        }

        $handled = [];
        $entries = OrderHistoryQuery::create()
            ->filterByOrderId($orderIds, Criteria::IN)
            ->filterByEventType([OrderHistoryEventType::PAYMENT_REMINDER_SENT->value, OrderHistoryEventType::PAYMENT_REMINDER_FAILED->value], Criteria::IN)
            ->find();

        foreach ($entries as $entry) {
            $payload = json_decode((string) $entry->getPayload(), true);

            if (\is_array($payload) && isset($payload['step'])) {
                $handled[(int) $entry->getOrderId()][] = (int) $payload['step'];
            }
        }

        return $handled;
    }
}
