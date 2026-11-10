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

namespace Thelia\Tests\Integration\Domain\Subscription;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Core\Event\Subscription\SubscriptionStatusChangeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Subscription\Enum\ActorType;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;
use Thelia\Domain\Subscription\Exception\SubscriptionTransitionRefusedException;
use Thelia\Domain\Subscription\SubscriptionStateMachine;
use Thelia\Model\Subscription;
use Thelia\Model\SubscriptionHistoryQuery;
use Thelia\Model\SubscriptionQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * Every change of status of a subscription, allowed or refused, and the refusal of a change
 * made on a subscription another process moved since it was read.
 */
final class SubscriptionStateMachineTest extends ActionIntegrationTestCase
{
    use SubscriptionScenario;

    /** @var list<SubscriptionStatusChangeEvent> */
    private array $announced = [];

    private ?\Closure $listener = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTheSubscriptionShop();
        $this->listener = function (SubscriptionStatusChangeEvent $event): void {
            $this->announced[] = $event;
        };
        $this->dispatcher->addListener(TheliaEvents::SUBSCRIPTION_STATUS_CHANGE, $this->listener);
    }

    protected function tearDown(): void
    {
        if (null !== $this->listener) {
            $this->dispatcher->removeListener(TheliaEvents::SUBSCRIPTION_STATUS_CHANGE, $this->listener);
        }

        $this->tearDownTheSubscriptionShop();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{SubscriptionStatus, SubscriptionStatus, bool}>
     */
    public static function everyPairOfStatuses(): iterable
    {
        foreach (SubscriptionStatus::cases() as $from) {
            foreach (SubscriptionStatus::cases() as $to) {
                yield $from->value.' to '.$to->value => [$from, $to, $from->canBecome($to)];
            }
        }
    }

    #[DataProvider('everyPairOfStatuses')]
    public function testAChangeIsMadeOnlyWhenTheMapLeadsThere(SubscriptionStatus $from, SubscriptionStatus $to, bool $allowed): void
    {
        $subscription = $this->aSubscriptionIn($from);
        $historyBefore = SubscriptionHistoryQuery::create()->filterBySubscriptionId($subscription->getId())->count();

        try {
            $this->stateMachine()->transition($subscription, $to, ActorType::Admin, 7, 'By hand');

            self::assertTrue($allowed, \sprintf('"%s" became "%s", which the map does not allow.', $from->value, $to->value));
        } catch (SubscriptionTransitionRefusedException) {
            self::assertFalse($allowed, \sprintf('"%s" could not become "%s".', $from->value, $to->value));
        }

        $stored = SubscriptionQuery::create()->findPk($subscription->getId());
        self::assertSame(($allowed ? $to : $from)->value, $stored?->getStatus());
        self::assertSame($historyBefore + ($allowed ? 1 : 0), SubscriptionHistoryQuery::create()->filterBySubscriptionId($subscription->getId())->count());
        self::assertCount($allowed ? 1 : 0, $this->announced);

        if ($allowed) {
            $row = SubscriptionHistoryQuery::create()->filterBySubscriptionId($subscription->getId())->orderById('desc')->findOne();
            self::assertSame([$from->value, $to->value, 'admin', 7, 'By hand'], [$row?->getFromStatus(), $row?->getToStatus(), $row?->getActorType(), $row?->getActorId(), $row?->getNote()]);
            self::assertSame($to, $this->announced[0]->getNewStatus());
            self::assertSame($from, $this->announced[0]->getPreviousStatus());
            self::assertSame($to->value, $this->announced[0]->getSubscription()->getStatus(), 'The event carries the subscription as written.');
        }
    }

    /**
     * Two processes read the subscription active; the first suspends its billing, the second
     * then tries to pause it from what it read. The status stored is no longer the one it
     * read: nothing is written, and it is told so.
     */
    public function testAChangeOnAStatusThatMovedMeanwhileIsRefusedAndWritesNothing(): void
    {
        $subscription = $this->aSubscriptionIn(SubscriptionStatus::Active);
        $readByTheSecondProcess = SubscriptionQuery::create()->findPk($subscription->getId());
        self::assertNotNull($readByTheSecondProcess);

        $this->stateMachine()->transition($subscription, SubscriptionStatus::PaymentFailed, ActorType::System);
        $historyBefore = SubscriptionHistoryQuery::create()->filterBySubscriptionId($subscription->getId())->count();

        try {
            $this->stateMachine()->transition($readByTheSecondProcess, SubscriptionStatus::Paused, ActorType::Customer, 3);
            self::fail('A change was made on a status read before another process moved it.');
        } catch (SubscriptionTransitionRefusedException $refusal) {
            self::assertStringContainsString('changed meanwhile', $refusal->getMessage());
        }

        self::assertSame(SubscriptionStatus::PaymentFailed->value, SubscriptionQuery::create()->findPk($subscription->getId())?->getStatus());
        self::assertSame($historyBefore, SubscriptionHistoryQuery::create()->filterBySubscriptionId($subscription->getId())->count());
        self::assertCount(1, $this->announced, 'Only the change that was made is announced.');
    }

    public function testTheColumnsWrittenWithAChangeGoInTheSameStatement(): void
    {
        $subscription = $this->aSubscriptionIn(SubscriptionStatus::Active);

        $this->stateMachine()->transition($subscription, SubscriptionStatus::PaymentFailed, ActorType::System, null, null, [
            'FailureCount' => 2,
            'NextRetryAt' => new \DateTime('2028-02-03 10:00:00'),
        ]);

        $stored = SubscriptionQuery::create()->findPk($subscription->getId());
        self::assertSame(2, $stored?->getFailureCount());
        self::assertSame('2028-02-03 10:00:00', $stored?->getNextRetryAt('Y-m-d H:i:s'));
        self::assertSame(2, $subscription->getFailureCount(), 'The subscription handed in is read again.');
    }

    private function aSubscriptionIn(SubscriptionStatus $status): Subscription
    {
        [$subscription] = $this->aSubscription();

        // Written straight to the row: the state a test starts from, not a change to judge.
        SubscriptionQuery::create()->filterById($subscription->getId())->update(['Status' => $status->value]);
        $subscription->reload();
        $this->announced = [];

        return $subscription;
    }

    private function stateMachine(): SubscriptionStateMachine
    {
        return $this->getService(SubscriptionStateMachine::class);
    }
}
