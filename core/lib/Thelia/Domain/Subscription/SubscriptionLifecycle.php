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

use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Thelia\Domain\CMS\Publication\PublicationMoment;
use Thelia\Domain\Payment\StoredPaymentMethodSelection;
use Thelia\Domain\Subscription\Enum\ActorType;
use Thelia\Domain\Subscription\Enum\PeriodUnit;
use Thelia\Domain\Subscription\Enum\SubscriptionAction;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;
use Thelia\Domain\Subscription\Exception\SubscriptionActionRefusedException;
use Thelia\Domain\Subscription\Message\BillSubscriptionInstallment;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\Map\SubscriptionTableMap;
use Thelia\Model\Subscription;
use Thelia\Model\SubscriptionQuery;

/**
 * What a customer or an administrator does to a subscription, with who did it recorded in
 * its history. A gesture the state does not allow (SubscriptionAction) is refused before
 * anything is written, and every change of status goes through SubscriptionStateMachine.
 */
final readonly class SubscriptionLifecycle
{
    public function __construct(
        private SubscriptionStateMachine $stateMachine,
        private BillingCalendar $calendar,
        private StoredPaymentMethodSelection $storedPaymentMethodSelection,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Served until the end of the paid period, or of the commitment when it ends later; the
     * installments of the commitment still due are billed until then.
     */
    public function cancel(Subscription $subscription, ActorType $actorType, ?int $actorId = null, ?string $note = null): Subscription
    {
        $this->assertAllowed(SubscriptionAction::Cancel, $subscription);

        return $this->stateMachine->transition($subscription, SubscriptionStatus::Cancelled, $actorType, $actorId, $note ?? 'Cancelled.', [
            'CancelAt' => \DateTime::createFromImmutable($this->cancellationDateOf($subscription)),
        ]);
    }

    public function pause(Subscription $subscription, ActorType $actorType, ?int $actorId = null, ?string $note = null): Subscription
    {
        if (1 !== (int) $subscription->getPausable()) {
            throw SubscriptionActionRefusedException::notPausable();
        }

        $this->assertAllowed(SubscriptionAction::Pause, $subscription);

        return $this->stateMachine->transition($subscription, SubscriptionStatus::Paused, $actorType, $actorId, $note ?? 'Paused.', [
            'PausedAt' => $this->now(),
        ]);
    }

    /**
     * Billed again at the end of the period it was paused in. When that end is past, a new
     * period starts now, anchored on today: the paused weeks are not charged. Only a paused
     * subscription is resumed.
     */
    public function resume(Subscription $subscription, ActorType $actorType, ?int $actorId = null, ?string $note = null): Subscription
    {
        $this->assertAllowed(SubscriptionAction::Resume, $subscription);

        $now = $this->now();
        $periodEnd = $subscription->getCurrentPeriodEnd();
        $changes = ['PausedAt' => null];

        if (null === $periodEnd || $periodEnd < $now) {
            $changes += [
                'BillingAnchorDay' => $this->calendar->anchorDayOf($now),
                'CurrentPeriodEnd' => $now,
                'NextBillingAt' => $now,
            ];
        } else {
            $changes['NextBillingAt'] = $periodEnd;
        }

        return $this->stateMachine->transition($subscription, SubscriptionStatus::Active, $actorType, $actorId, $note ?? 'Resumed.', $changes);
    }

    /**
     * Tries the failed installment again now. The subscription becomes active only once the
     * charge is paid; until then it keeps its status.
     */
    public function reactivate(Subscription $subscription, ActorType $actorType, ?int $actorId = null, ?string $note = null): Subscription
    {
        $this->assertAllowed(SubscriptionAction::Reactivate, $subscription);

        $subscription = $this->retryNow($subscription);
        $this->stateMachine->recordNote($subscription, $actorType, $actorId, $note ?? 'Retry asked.');
        $this->bus->dispatch(new BillSubscriptionInstallment((int) $subscription->getId()));

        return $this->reloaded($subscription);
    }

    /**
     * The stored method the installments are charged to from now on: a method of the same
     * customer and of the payment module of the subscription. A subscription waiting on a
     * failed installment is tried again with it at once.
     */
    public function changePaymentMethod(Subscription $subscription, CustomerPaymentMethod $method, ActorType $actorType, ?int $actorId = null): Subscription
    {
        $this->assertAllowed(SubscriptionAction::ChangePaymentMethod, $subscription);

        if ((int) $method->getPaymentModuleId() !== (int) $subscription->getPaymentModuleId()) {
            throw SubscriptionActionRefusedException::methodOfAnotherModule();
        }

        $method = $this->storedPaymentMethodSelection->usableMethod(
            $subscription->getCustomer(),
            (int) $subscription->getPaymentModuleId(),
            (int) $method->getId(),
        );

        SubscriptionQuery::create()
            ->filterById($subscription->getId())
            ->update(['CustomerPaymentMethodId' => $method->getId(), 'UpdatedAt' => $this->now()]);
        $subscription = $this->reloaded($subscription);

        $this->stateMachine->recordNote($subscription, $actorType, $actorId, 'Payment method changed.');

        $status = SubscriptionStatus::from((string) $subscription->getStatus());

        if (SubscriptionStatus::Suspended === $status || SubscriptionStatus::PaymentFailed === $status) {
            return $this->reactivate($subscription, $actorType, $actorId, 'Retry with the new payment method.');
        }

        return $subscription;
    }

    /**
     * The gestures the subscription allows as it stands, in the order a screen offers them.
     *
     * @return list<SubscriptionAction>
     */
    public function allowedActions(Subscription $subscription): array
    {
        return SubscriptionAction::allowedIn(self::statusOf($subscription), 1 === (int) $subscription->getPausable());
    }

    private function assertAllowed(SubscriptionAction $action, Subscription $subscription): void
    {
        $status = self::statusOf($subscription);

        if (!$action->isAllowedIn($status, 1 === (int) $subscription->getPausable())) {
            throw SubscriptionActionRefusedException::notInState($action, $status);
        }
    }

    private static function statusOf(Subscription $subscription): SubscriptionStatus
    {
        return SubscriptionStatus::from((string) $subscription->getStatus());
    }

    private function cancellationDateOf(Subscription $subscription): \DateTimeImmutable
    {
        $periodEnd = PublicationMoment::atTheSecondOrNull($subscription->getCurrentPeriodEnd()) ?? PublicationMoment::atTheSecond($this->clock->now());
        $committed = (int) $subscription->getCommitmentCount();
        $remaining = $committed - (int) $subscription->getInstallmentsBilled();

        if ($remaining <= 0) {
            return $periodEnd;
        }

        // The current period ends the last installment billed; each committed one still due
        // adds a period.
        return $this->calendar->advance(
            $periodEnd,
            PeriodUnit::from((string) $subscription->getPeriodUnit()),
            max(1, (int) $subscription->getPeriodCount()),
            (int) $subscription->getBillingAnchorDay(),
            $remaining,
        );
    }

    private function retryNow(Subscription $subscription): Subscription
    {
        SubscriptionQuery::create()
            ->filterById($subscription->getId())
            ->update(['NextRetryAt' => $this->now(), 'UpdatedAt' => $this->now()]);

        return $this->reloaded($subscription);
    }

    private function reloaded(Subscription $subscription): Subscription
    {
        SubscriptionTableMap::removeInstanceFromPool($subscription);
        $subscription->reload();

        return $subscription;
    }

    private function now(): \DateTime
    {
        return \DateTime::createFromImmutable(PublicationMoment::atTheSecond($this->clock->now()));
    }
}
