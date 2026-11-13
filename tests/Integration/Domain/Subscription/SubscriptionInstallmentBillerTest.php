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

use Propel\Runtime\Propel;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Thelia\Domain\Subscription\Enum\ActorType;
use Thelia\Domain\Subscription\Enum\BillingOutcome;
use Thelia\Domain\Subscription\Enum\PeriodUnit;
use Thelia\Domain\Subscription\Enum\RecurringChargeOutcome;
use Thelia\Domain\Subscription\Enum\SubscriptionOrderState;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;
use Thelia\Domain\Subscription\SubscriptionBillingLock;
use Thelia\Domain\Subscription\SubscriptionInstallmentBiller;
use Thelia\Domain\Subscription\SubscriptionManager;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Subscription;
use Thelia\Model\SubscriptionHistoryQuery;
use Thelia\Model\SubscriptionOrderQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Payment\RecurringTestPaymentModule;

/**
 * The billing of the installments of a subscription, from the order it was taken with to
 * its end: one order per installment whatever runs twice, a declined card tried again then
 * suspended with its stock given back, a provider out of reach tried again on the same
 * order, a cancellation served to the end of the paid period.
 *
 * The clock starts on 31/01/2028 at 10:00: the periods are anchored on the 31st and the
 * first one ends on the 29th of a leap February.
 */
final class SubscriptionInstallmentBillerTest extends ActionIntegrationTestCase
{
    use SubscriptionScenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTheSubscriptionShop();
    }

    protected function tearDown(): void
    {
        $this->tearDownTheSubscriptionShop();

        parent::tearDown();
    }

    public function testWithoutATrialTheInitialOrderIsTheFirstInstallmentAndTheNextIsDueAtTheEndOfThePeriod(): void
    {
        [$subscription] = $this->aSubscription();

        self::assertSame(SubscriptionStatus::Active->value, $subscription->getStatus());
        self::assertSame(1, $subscription->getInstallmentsBilled());
        self::assertSame(31, $subscription->getBillingAnchorDay());
        self::assertSame('2028-02-29 10:00:00', $subscription->getNextBillingAt('Y-m-d H:i:s'));
        self::assertSame('9.900000', $subscription->getUnitPrice());

        $first = SubscriptionOrderQuery::create()->filterBySubscriptionId($subscription->getId())->find();
        self::assertCount(1, $first);
        self::assertSame(1, $first->getFirst()->getInstallmentNumber());
        self::assertSame((int) $subscription->getInitialOrderId(), $first->getFirst()->getOrderId());
        self::assertSame(SubscriptionOrderState::Paid->value, $first->getFirst()->getState());

        self::assertSame(BillingOutcome::NothingDue, $this->biller()->bill((int) $subscription->getId()));
        self::assertSame([], RecurringTestPaymentModule::$charges);
    }

    public function testATrialSubscriptionIsFirstChargedWhenTheTrialEnds(): void
    {
        [$subscription] = $this->aSubscription(['TrialDays' => 14]);

        self::assertSame(SubscriptionStatus::Trial->value, $subscription->getStatus());
        self::assertSame(0, $subscription->getInstallmentsBilled());
        self::assertSame('2028-02-14 10:00:00', $subscription->getNextBillingAt('Y-m-d H:i:s'));
        self::assertSame(0, SubscriptionOrderQuery::create()->filterBySubscriptionId($subscription->getId())->count(), 'Nothing is paid during the trial.');

        $this->moveTo('2028-02-14 09:59:59');
        self::assertSame(BillingOutcome::NothingDue, $this->biller()->bill((int) $subscription->getId()));

        $this->moveTo('2028-02-14 10:00:00');
        self::assertSame(BillingOutcome::Paid, $this->biller()->bill((int) $subscription->getId()));

        $subscription = $this->reloaded($subscription);
        self::assertSame(SubscriptionStatus::Active->value, $subscription->getStatus());
        self::assertSame(1, $subscription->getInstallmentsBilled());
        self::assertSame('2028-02-14 10:00:00', $subscription->getCurrentPeriodStart('Y-m-d H:i:s'));
        self::assertSame('2028-03-14 10:00:00', $subscription->getNextBillingAt('Y-m-d H:i:s'));
        self::assertSame([SubscriptionInstallmentBiller::idempotencyKeyOf((int) $subscription->getId(), 1)], RecurringTestPaymentModule::chargedKeys());
        self::assertCount(1, $this->installmentOrdersOf($subscription));
    }

    public function testADueInstallmentIsPlacedAsAnOrderChargedOnceAndThePeriodMovesOn(): void
    {
        [$subscription, $product] = $this->aSubscription(quantity: 2);

        $this->moveTo('2028-02-29 10:00:00');
        self::assertSame(BillingOutcome::Paid, $this->biller()->bill((int) $subscription->getId()));

        $subscription = $this->reloaded($subscription);
        $orders = $this->installmentOrdersOf($subscription);

        self::assertCount(1, $orders);
        self::assertTrue($orders[0]->isPaid(false), 'The order of the installment goes paid through the status flow.');
        self::assertSame((int) $subscription->getPaymentModuleId(), $orders[0]->getPaymentModuleId());
        self::assertSame((int) $this->carrier->getId(), $orders[0]->getDeliveryModuleId());
        self::assertSame(2.0, (float) $orders[0]->getOrderProducts()->getFirst()?->getQuantity());
        self::assertSame('9.900000', $orders[0]->getOrderProducts()->getFirst()?->getPrice(), 'The frozen price of the plan, not the price of the product.');
        self::assertNotSame($orders[0]->getDeliveryOrderAddressId(), OrderQuery::create()->findPk($subscription->getInitialOrderId())?->getDeliveryOrderAddressId(), 'Each order keeps an address of its own.');

        self::assertSame(2, $subscription->getInstallmentsBilled());
        self::assertSame('2028-02-29 10:00:00', $subscription->getCurrentPeriodStart('Y-m-d H:i:s'));
        self::assertSame('2028-03-31 10:00:00', $subscription->getNextBillingAt('Y-m-d H:i:s'), 'Back to the anchor day once the month allows it.');
        self::assertSame([SubscriptionInstallmentBiller::idempotencyKeyOf((int) $subscription->getId(), 2)], RecurringTestPaymentModule::chargedKeys());
        self::assertSame(8.0, (float) ProductSaleElementsQuery::create()->findPk($this->saleElementsOf($product)->getId())?->getQuantity(), 'A physical product leaves the stock with each installment.');
    }

    public function testASecondRunOnTheSameInstallmentDoesNothing(): void
    {
        [$subscription] = $this->aSubscription();
        $this->moveTo('2028-02-29 10:00:00');

        self::assertSame(BillingOutcome::Paid, $this->biller()->bill((int) $subscription->getId()));
        self::assertSame(BillingOutcome::NothingDue, $this->biller()->bill((int) $subscription->getId()));

        self::assertCount(1, RecurringTestPaymentModule::$charges);
        self::assertSame(1, $this->ordersPlacedFor($subscription));
    }

    /**
     * The billing that holds the lock leaves nothing to a second one started while the
     * provider is still answering the first.
     */
    public function testABillingStartedWhileAnotherOneChargesTheSameSubscriptionDoesNothing(): void
    {
        [$subscription] = $this->aSubscription();
        $this->moveTo('2028-02-29 10:00:00');

        $secondRun = null;
        RecurringTestPaymentModule::$duringNextCharge = function () use ($subscription, &$secondRun): void {
            $secondRun = $this->biller()->bill((int) $subscription->getId());
        };

        self::assertSame(BillingOutcome::Paid, $this->biller()->bill((int) $subscription->getId()));

        self::assertSame(BillingOutcome::Busy, $secondRun);
        self::assertCount(1, RecurringTestPaymentModule::$charges, 'The provider is asked once.');
        self::assertSame(1, $this->ordersPlacedFor($subscription));
    }

    /**
     * Two nodes that do not share the lock store: the second billing reads the row of the
     * installment the first one wrote, and charges its order again with the same key, which
     * the provider takes once. It never places a second order.
     */
    public function testABillingOnANodeWithoutTheSharedLockChargesTheSameOrderWithTheSameKey(): void
    {
        [$subscription] = $this->aSubscription();
        $this->moveTo('2028-02-29 10:00:00');

        $otherNode = new SubscriptionInstallmentBiller(
            new SubscriptionBillingLock(new LockFactory(new InMemoryStore())),
            ...$this->dependenciesOfTheBillerBeyondItsLock(),
        );

        RecurringTestPaymentModule::$duringNextCharge = static function () use ($otherNode, $subscription): void {
            $otherNode->bill((int) $subscription->getId());
        };

        $this->biller()->bill((int) $subscription->getId());

        self::assertSame(1, $this->ordersPlacedFor($subscription), 'One order for the installment.');
        self::assertSame(1, SubscriptionOrderQuery::create()->filterBySubscriptionId($subscription->getId())->filterByInstallmentNumber(2)->count());
        self::assertSame(
            array_fill(0, 2, SubscriptionInstallmentBiller::idempotencyKeyOf((int) $subscription->getId(), 2)),
            RecurringTestPaymentModule::chargedKeys(),
        );
        self::assertSame(
            [RecurringTestPaymentModule::$charges[0]['orderId']],
            array_values(array_unique(array_column(RecurringTestPaymentModule::$charges, 'orderId'))),
        );
        self::assertSame(2, $this->reloaded($subscription)->getInstallmentsBilled());
    }

    public function testAProviderOutOfReachIsTriedAgainWithinTheHourOnTheSameOrder(): void
    {
        [$subscription] = $this->aSubscription();
        $this->moveTo('2028-02-29 10:00:00');
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Retryable;

        self::assertSame(BillingOutcome::Retryable, $this->biller()->bill((int) $subscription->getId()));

        $subscription = $this->reloaded($subscription);
        self::assertSame(SubscriptionStatus::PaymentFailed->value, $subscription->getStatus());
        self::assertSame(0, $subscription->getFailureCount(), 'A provider out of reach declined nothing.');
        self::assertSame('2028-02-29 11:00:00', $subscription->getNextRetryAt('Y-m-d H:i:s'));
        $order = $this->installmentOrdersOf($subscription)[0];
        self::assertFalse($order->isCancelled(false));

        $this->moveTo('2028-02-29 10:30:00');
        self::assertSame(BillingOutcome::NothingDue, $this->biller()->bill((int) $subscription->getId()));

        $this->moveTo('2028-02-29 11:00:00');
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Paid;
        self::assertSame(BillingOutcome::Paid, $this->biller()->bill((int) $subscription->getId()));

        $subscription = $this->reloaded($subscription);
        self::assertSame(SubscriptionStatus::Active->value, $subscription->getStatus());
        self::assertNull($subscription->getNextRetryAt());
        self::assertSame(1, $this->ordersPlacedFor($subscription), 'No order left behind by the attempt that failed.');
        self::assertSame([$order->getId(), $order->getId()], array_column(RecurringTestPaymentModule::$charges, 'orderId'));
        self::assertSame(2, SubscriptionOrderQuery::create()->filterBySubscriptionId($subscription->getId())->filterByInstallmentNumber(2)->findOne()?->getAttemptCount());
    }

    public function testDeclinedChargesAreTriedAgainThenSuspendTheSubscriptionAndGiveTheStockBack(): void
    {
        [$subscription, $product] = $this->aSubscription(stock: 10, quantity: 2);
        $saleElementsId = (int) $this->saleElementsOf($product)->getId();
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Declined;

        $this->moveTo('2028-02-29 10:00:00');
        self::assertSame(BillingOutcome::Failed, $this->biller()->bill((int) $subscription->getId()));
        $subscription = $this->reloaded($subscription);
        self::assertSame(SubscriptionStatus::PaymentFailed->value, $subscription->getStatus());
        self::assertSame(1, $subscription->getFailureCount());
        self::assertSame('2028-03-02 10:00:00', $subscription->getNextRetryAt('Y-m-d H:i:s'));
        self::assertSame(8.0, (float) ProductSaleElementsQuery::create()->findPk($saleElementsId)?->getQuantity(), 'The order of the installment holds its stock while it is tried again.');

        $this->moveTo('2028-03-02 10:00:00');
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::ActionRequired;
        self::assertSame(BillingOutcome::Failed, $this->biller()->bill((int) $subscription->getId()));
        self::assertSame(2, $this->reloaded($subscription)->getFailureCount());

        $this->moveTo('2028-03-04 10:00:00');
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Declined;
        self::assertSame(BillingOutcome::Suspended, $this->biller()->bill((int) $subscription->getId()));

        $subscription = $this->reloaded($subscription);
        self::assertSame(SubscriptionStatus::Suspended->value, $subscription->getStatus());
        self::assertNull($subscription->getNextRetryAt());
        $orders = $this->installmentOrdersOf($subscription);
        self::assertCount(1, $orders, 'The three charges were made on the same order.');
        self::assertTrue($orders[0]->isCancelled(false));
        self::assertSame(10.0, (float) ProductSaleElementsQuery::create()->findPk($saleElementsId)?->getQuantity(), 'The cancelled order gave its stock back.');
        self::assertSame(array_fill(0, 3, SubscriptionInstallmentBiller::idempotencyKeyOf((int) $subscription->getId(), 2)), RecurringTestPaymentModule::chargedKeys());

        $this->moveTo('2028-04-30 10:00:00');
        self::assertSame(BillingOutcome::NothingDue, $this->biller()->bill((int) $subscription->getId()), 'A suspended subscription is no longer charged.');
        self::assertCount(3, RecurringTestPaymentModule::$charges);
        self::assertSame(
            ['active>payment_failed', 'payment_failed>suspended'],
            $this->statusChangesOf($subscription),
        );
    }

    public function testAReactivatedSubscriptionIsChargedAgainOnANewOrderAndBecomesActiveOncePaid(): void
    {
        [$subscription] = $this->aSubscription();
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Declined;

        foreach (['2028-02-29 10:00:00', '2028-03-02 10:00:00', '2028-03-04 10:00:00'] as $moment) {
            $this->moveTo($moment);
            $this->biller()->bill((int) $subscription->getId());
        }

        $subscription = $this->reloaded($subscription);
        self::assertSame(SubscriptionStatus::Suspended->value, $subscription->getStatus());
        $cancelledOrderId = $this->installmentOrdersOf($subscription)[0]->getId();

        $this->moveTo('2028-03-10 15:00:00');
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Paid;
        $this->getService(SubscriptionManager::class)->reactivate($subscription, ActorType::Admin, 1);

        $subscription = $this->reloaded($subscription);
        self::assertSame(SubscriptionStatus::Active->value, $subscription->getStatus(), 'Charged at once, without a worker.');
        self::assertSame(0, $subscription->getFailureCount());
        self::assertSame(2, $subscription->getInstallmentsBilled());
        self::assertSame('2028-03-10 15:00:00', $subscription->getCurrentPeriodStart('Y-m-d H:i:s'), 'The period paid starts with the payment: the weeks of the suspension are not charged.');
        self::assertSame('2028-04-10 15:00:00', $subscription->getNextBillingAt('Y-m-d H:i:s'));
        self::assertSame(10, $subscription->getBillingAnchorDay(), 'The periods are anchored on the day it was paid.');

        $installment = SubscriptionOrderQuery::create()->filterBySubscriptionId($subscription->getId())->filterByInstallmentNumber(2)->findOne();
        self::assertNotNull($installment);
        self::assertSame(SubscriptionOrderState::Paid->value, $installment->getState());
        self::assertNotSame($cancelledOrderId, $installment->getOrderId(), 'A cancelled order is never paid: the retry gets an order of its own.');
        self::assertTrue(OrderQuery::create()->findPk($installment->getOrderId())?->isPaid(false));
        self::assertSame(2, $this->ordersPlacedFor($subscription), 'The cancelled order and the paid one, nothing else.');
    }

    public function testASubscriptionReactivatedMonthsAfterItsSuspensionIsChargedOnceThenWaitsForItsNextPeriod(): void
    {
        [$subscription] = $this->aSubscription();
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Declined;

        foreach (['2028-02-29 10:00:00', '2028-03-02 10:00:00', '2028-03-04 10:00:00'] as $moment) {
            $this->moveTo($moment);
            $this->biller()->bill((int) $subscription->getId());
        }

        self::assertSame(SubscriptionStatus::Suspended->value, $this->reloaded($subscription)->getStatus());

        $this->moveTo('2028-07-10 09:00:00');
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Paid;
        $this->getService(SubscriptionManager::class)->reactivate($this->reloaded($subscription), ActorType::Customer, (int) $subscription->getCustomerId());

        $subscription = $this->reloaded($subscription);
        self::assertSame(SubscriptionStatus::Active->value, $subscription->getStatus());
        self::assertSame('2028-08-10 09:00:00', $subscription->getNextBillingAt('Y-m-d H:i:s'), 'The next installment comes a period after the payment, not on the date due before the suspension.');
        $charges = \count(RecurringTestPaymentModule::$charges);
        $orders = $this->ordersPlacedFor($subscription);

        foreach (['2028-07-10 09:15:00', '2028-07-11 09:00:00', '2028-08-01 09:00:00'] as $moment) {
            $this->moveTo($moment);
            self::assertSame(BillingOutcome::NothingDue, $this->biller()->bill((int) $subscription->getId()), 'No missed month is charged at '.$moment.'.');
        }

        self::assertCount($charges, RecurringTestPaymentModule::$charges);
        self::assertSame($orders, $this->ordersPlacedFor($subscription), 'No order is placed for the months of the suspension.');

        $this->moveTo('2028-08-10 09:00:00');
        self::assertSame(BillingOutcome::Paid, $this->biller()->bill((int) $subscription->getId()));
        self::assertSame('2028-09-10 09:00:00', $this->reloaded($subscription)->getNextBillingAt('Y-m-d H:i:s'));
    }

    public function testAnInstallmentPaidOnceItsPeriodIsOverStartsANewPeriodWithThePayment(): void
    {
        [$subscription] = $this->aSubscription(['PeriodUnit' => PeriodUnit::Week->value]);
        self::assertSame('2028-02-07 10:00:00', $subscription->getNextBillingAt('Y-m-d H:i:s'));

        $this->moveTo('2028-02-07 10:00:00');
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Retryable;
        self::assertSame(BillingOutcome::Retryable, $this->biller()->bill((int) $subscription->getId()));

        // The provider stays out of reach for more than a week.
        $this->moveTo('2028-02-15 12:00:00');
        RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Paid;
        self::assertSame(BillingOutcome::Paid, $this->biller()->bill((int) $subscription->getId()));

        $subscription = $this->reloaded($subscription);
        self::assertSame('2028-02-22 12:00:00', $subscription->getNextBillingAt('Y-m-d H:i:s'), 'Never a next installment in the past, due again at once.');
        $this->moveTo('2028-02-15 12:15:00');
        self::assertSame(BillingOutcome::NothingDue, $this->biller()->bill((int) $subscription->getId()));
    }

    public function testACancelledSubscriptionIsServedUntilTheEndOfItsPeriodThenEndsWithoutACharge(): void
    {
        [$subscription] = $this->aSubscription();

        $this->moveTo('2028-02-10 09:00:00');
        $subscription = $this->getService(SubscriptionManager::class)->cancel($subscription, ActorType::Customer, (int) $subscription->getCustomerId());

        self::assertSame(SubscriptionStatus::Cancelled->value, $subscription->getStatus());
        self::assertSame('2028-02-29 10:00:00', $subscription->getCancelAt('Y-m-d H:i:s'));
        self::assertSame(BillingOutcome::NothingDue, $this->biller()->bill((int) $subscription->getId()));

        $this->moveTo('2028-02-29 10:00:00');
        self::assertSame(BillingOutcome::Ended, $this->biller()->bill((int) $subscription->getId()));

        $subscription = $this->reloaded($subscription);
        self::assertSame(SubscriptionStatus::Ended->value, $subscription->getStatus());
        self::assertNull($subscription->getNextBillingAt());
        self::assertSame([], RecurringTestPaymentModule::$charges);
        self::assertSame(0, $this->ordersPlacedFor($subscription));
        self::assertSame(['active>cancelled', 'cancelled>ended'], $this->statusChangesOf($subscription));
    }

    public function testASubscriptionInItsCommitmentIsCancelledAtTheEndOfTheCommitmentAndBilledUntilThen(): void
    {
        [$subscription] = $this->aSubscription(['CommitmentCount' => 3]);

        $this->moveTo('2028-02-10 09:00:00');
        $subscription = $this->getService(SubscriptionManager::class)->cancel($subscription, ActorType::Customer);
        self::assertSame('2028-04-30 10:00:00', $subscription->getCancelAt('Y-m-d H:i:s'), 'Three periods from the first one.');

        foreach (['2028-02-29 10:00:00', '2028-03-31 10:00:00'] as $moment) {
            $this->moveTo($moment);
            self::assertSame(BillingOutcome::Paid, $this->biller()->bill((int) $subscription->getId()));
        }

        $this->moveTo('2028-04-30 10:00:00');
        self::assertSame(BillingOutcome::Ended, $this->biller()->bill((int) $subscription->getId()));
        self::assertCount(2, RecurringTestPaymentModule::$charges, 'The two committed installments after the first one, nothing after.');
    }

    public function testASubscriptionOfAFixedNumberOfInstallmentsEndsOnceTheLastPeriodIsServed(): void
    {
        [$subscription] = $this->aSubscription(['InstallmentCount' => 2]);

        $this->moveTo('2028-02-29 10:00:00');
        self::assertSame(BillingOutcome::Paid, $this->biller()->bill((int) $subscription->getId()));

        $this->moveTo('2028-03-31 10:00:00');
        self::assertSame(BillingOutcome::Ended, $this->biller()->bill((int) $subscription->getId()));

        self::assertSame(SubscriptionStatus::Ended->value, $this->reloaded($subscription)->getStatus());
        self::assertCount(1, RecurringTestPaymentModule::$charges);
    }

    /**
     * A membership leaves the stock once, with the order it is taken with; the installments
     * that follow do not take from it, even with a module that leaves the stock to the
     * payment (the core then takes it when the order turns paid, virtual or not).
     */
    public function testTheInstallmentsOfAVirtualProductLeaveTheStockAlone(): void
    {
        RecurringTestPaymentModule::$managesStockOnCreation = false;
        [$subscription, $product] = $this->aSubscription(stock: 10, virtual: true);

        foreach (['2028-02-29 10:00:00', '2028-03-31 10:00:00'] as $moment) {
            $this->moveTo($moment);
            self::assertSame(BillingOutcome::Paid, $this->biller()->bill((int) $subscription->getId()));
        }

        self::assertCount(2, $this->installmentOrdersOf($subscription));
        self::assertSame(10.0, (float) ProductSaleElementsQuery::create()->findPk($this->saleElementsOf($product)->getId())?->getQuantity());
    }

    public function testTheInstallmentsOfAPhysicalProductTakeTheStockWhenTheModuleLeavesItToThePayment(): void
    {
        RecurringTestPaymentModule::$managesStockOnCreation = false;
        [$subscription, $product] = $this->aSubscription(stock: 10);

        foreach (['2028-02-29 10:00:00', '2028-03-31 10:00:00'] as $moment) {
            $this->moveTo($moment);
            self::assertSame(BillingOutcome::Paid, $this->biller()->bill((int) $subscription->getId()));
        }

        self::assertSame(8.0, (float) ProductSaleElementsQuery::create()->findPk($this->saleElementsOf($product)->getId())?->getQuantity());
    }

    public function testAMethodForgottenByTheCustomerDeclinesTheInstallment(): void
    {
        [$subscription, , $method] = $this->aSubscription();
        $method->delete($this->getPropelConnection());

        $this->moveTo('2028-02-29 10:00:00');
        self::assertSame(BillingOutcome::Failed, $this->biller()->bill((int) $subscription->getId()));

        self::assertSame([], RecurringTestPaymentModule::$charges, 'Nothing is sent to the provider.');
        self::assertSame('The subscription has no stored payment method to charge.', SubscriptionOrderQuery::create()->filterBySubscriptionId($subscription->getId())->filterByInstallmentNumber(2)->findOne()?->getLastError());
    }

    /**
     * Production keeps the instance pool on, the tests turn it off: a model read once and
     * kept in the pool must not hide what the billing wrote meanwhile.
     */
    public function testTheBillingHoldsWithTheInstancePoolOfProduction(): void
    {
        Propel::enableInstancePooling();

        try {
            [$subscription] = $this->aSubscription();
            RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Declined;

            $this->moveTo('2028-02-29 10:00:00');
            self::assertSame(BillingOutcome::Failed, $this->biller()->bill((int) $subscription->getId()));

            $this->moveTo('2028-03-02 10:00:00');
            self::assertSame(BillingOutcome::Failed, $this->biller()->bill((int) $subscription->getId()));
            self::assertSame(2, $this->reloaded($subscription)->getFailureCount());
            self::assertSame('2028-03-04 10:00:00', $this->reloaded($subscription)->getNextRetryAt('Y-m-d H:i:s'));

            $this->moveTo('2028-03-03 10:00:00');
            self::assertSame(BillingOutcome::NothingDue, $this->biller()->bill((int) $subscription->getId()), 'The retry date read is the one written, not the one kept in the pool.');

            $this->moveTo('2028-03-04 10:00:00');
            RecurringTestPaymentModule::$outcome = RecurringChargeOutcome::Paid;
            self::assertSame(BillingOutcome::Paid, $this->biller()->bill((int) $subscription->getId()));
            self::assertSame(BillingOutcome::NothingDue, $this->biller()->bill((int) $subscription->getId()));

            $subscription = $this->reloaded($subscription);
            self::assertSame(SubscriptionStatus::Active->value, $subscription->getStatus());
            self::assertSame(2, $subscription->getInstallmentsBilled());
            self::assertSame(1, $this->ordersPlacedFor($subscription));
            self::assertCount(3, RecurringTestPaymentModule::$charges);
        } finally {
            Propel::disableInstancePooling();
        }
    }

    /**
     * What the biller of the container was built with, but the lock: another node runs the
     * same code against the same database.
     *
     * @return list<object>
     */
    private function dependenciesOfTheBillerBeyondItsLock(): array
    {
        $biller = $this->biller();
        $constructor = new \ReflectionMethod(SubscriptionInstallmentBiller::class, '__construct');

        return array_map(
            static fn (\ReflectionParameter $parameter): object => (new \ReflectionProperty(SubscriptionInstallmentBiller::class, $parameter->getName()))->getValue($biller),
            \array_slice($constructor->getParameters(), 1),
        );
    }

    /**
     * @return list<string> "from>to" for each change of status, oldest first
     */
    private function statusChangesOf(Subscription $subscription): array
    {
        $changes = [];

        foreach (SubscriptionHistoryQuery::create()->filterBySubscriptionId($subscription->getId())->orderById()->find() as $row) {
            if (null !== $row->getFromStatus() && $row->getFromStatus() !== $row->getToStatus()) {
                $changes[] = $row->getFromStatus().'>'.$row->getToStatus();
            }
        }

        return $changes;
    }
}
