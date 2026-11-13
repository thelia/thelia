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

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Domain\Subscription\Enum\ActorType;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;
use Thelia\Domain\Subscription\SubscriptionLifecycle;
use Thelia\Model\SubscriptionQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Payment\RecurringTestPaymentModule;

/**
 * The scheduled run, from the command line: no request, no session, the jobs run at once
 * without a worker.
 */
final class SubscriptionBillDueCommandTest extends ActionIntegrationTestCase
{
    use SubscriptionScenario;

    /** @var list<Request> */
    private array $requestsTakenAway = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTheSubscriptionShop();
    }

    protected function tearDown(): void
    {
        $requestStack = $this->getService(RequestStack::class);

        foreach (array_reverse($this->requestsTakenAway) as $request) {
            $requestStack->push($request);
        }

        $this->tearDownTheSubscriptionShop();

        parent::tearDown();
    }

    public function testTheCommandBillsWhatIsDueAndOnlyThatWithoutARequest(): void
    {
        [$due] = $this->aSubscription();
        [$notYet] = $this->aSubscription();
        [$cancelled] = $this->aSubscription();
        [$paused] = $this->aSubscription(['Pausable' => 1]);

        $this->moveTo('2028-02-15 10:00:00');
        SubscriptionQuery::create()->filterById($notYet->getId())->update(['NextBillingAt' => new \DateTime('2028-03-15 10:00:00')]);
        $this->getService(SubscriptionLifecycle::class)->cancel($cancelled, ActorType::Customer);
        $this->getService(SubscriptionLifecycle::class)->pause($paused, ActorType::Customer);
        // Nothing left to bill on it: found by its cancellation date alone.
        SubscriptionQuery::create()->filterById($cancelled->getId())->update(['NextBillingAt' => null]);

        $this->moveTo('2028-02-29 10:00:00');
        self::assertSame([(int) $due->getId(), (int) $cancelled->getId()], array_values(array_intersect(
            SubscriptionQuery::dueIdsAt(new \DateTimeImmutable('2028-02-29 10:00:00')),
            [(int) $due->getId(), (int) $notYet->getId(), (int) $cancelled->getId(), (int) $paused->getId()],
        )));

        $this->takeTheRequestsAway();
        $tester = new CommandTester((new Application(self::$kernel))->find('subscription:bill-due'));

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        self::assertSame(SubscriptionStatus::Active->value, $this->reloaded($due)->getStatus());
        self::assertSame(2, $this->reloaded($due)->getInstallmentsBilled());
        self::assertTrue($this->installmentOrdersOf($due)[0]->isPaid(false));
        self::assertSame(1, $this->reloaded($notYet)->getInstallmentsBilled());
        self::assertSame(SubscriptionStatus::Ended->value, $this->reloaded($cancelled)->getStatus());
        self::assertSame(SubscriptionStatus::Paused->value, $this->reloaded($paused)->getStatus());
        self::assertCount(1, RecurringTestPaymentModule::$charges, 'Only the subscription that was due is charged.');
        self::assertStringContainsString('2 subscription(s) sent to billing', $tester->getDisplay());
    }

    public function testADryRunSendsNothing(): void
    {
        [$due] = $this->aSubscription();
        $this->moveTo('2028-02-29 10:00:00');

        $tester = new CommandTester((new Application(self::$kernel))->find('subscription:bill-due'));
        $tester->execute(['--dry-run' => true]);

        self::assertStringContainsString(\sprintf('Subscription #%d (would be billed)', $due->getId()), $tester->getDisplay());
        self::assertSame([], RecurringTestPaymentModule::$charges);
        self::assertSame(1, $this->reloaded($due)->getInstallmentsBilled());
    }

    private function takeTheRequestsAway(): void
    {
        $requestStack = $this->getService(RequestStack::class);

        while (null !== $request = $requestStack->pop()) {
            $this->requestsTakenAway[] = $request;
        }

        self::assertNull($requestStack->getCurrentRequest());
    }
}
