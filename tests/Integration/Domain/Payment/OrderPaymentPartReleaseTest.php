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

namespace Thelia\Tests\Integration\Domain\Payment;

use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\Order\OrderPaymentPartEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Order\Exception\OrderStatusTransitionRefusedException;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Domain\Payment\Exception\CancelledOrderPaymentRefusedException;
use Thelia\Domain\Payment\Service\OrderPaymentPartSettlement;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\OrderPaymentPartTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderPaymentPartQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Tests\Support\Payment\BalanceChargingTestPaymentModule;
use Thelia\Tests\Support\Payment\InstantTestPaymentModule;
use Thelia\Tests\Support\Payment\SecondInstantTestPaymentModule;
use Thelia\Tests\Support\Payment\UsesInstantPaymentModules;

/**
 * Giving back the parts of an order once it is cancelled, whoever cancels it.
 *
 * MODULE_PAY is not stubbed: the payment modules are the test modules, installed in the
 * container under their service ids, and the order is placed and paid again through the
 * same CheckoutPaymentService the theme and the API use. The cart is worth 100.00 and its
 * postage is free.
 */
final class OrderPaymentPartReleaseTest extends IntegrationTestCase
{
    use ForgetsPooledModels;
    use UsesInstantPaymentModules;

    private FixtureFactory $factory;

    private Module $instantModule;

    private Module $secondInstantModule;

    private Module $providerModule;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $container = static::getContainer();
        $connection = $this->getPropelConnection();

        $this->instantModule = $this->installPaymentModule(InstantTestPaymentModule::class, $container, $connection);
        $this->secondInstantModule = $this->installPaymentModule(SecondInstantTestPaymentModule::class, $container, $connection);
        $this->providerModule = $this->installPaymentModule(BalanceChargingTestPaymentModule::class, $container, $connection);
        $this->turnMandatoryConsentsOff();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];
        $this->uninstallThePaymentModules(static::getContainer());
        $this->getService(ConsentProvider::class)->forgetCache();
        self::forgetPooledModels();
        Propel::disableInstancePooling();

        parent::tearDown();
    }

    public function testASettledInstantPartIsReleasedOnceWhenTheOrderIsCancelled(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        [$instantPart, $providerPart] = $this->partsOf($order);
        $announced = $this->recordTheReleases();

        $this->cancel($order);

        self::assertTrue($this->reread($order)->isCancelled());
        self::assertSame(['settle', 'release'], InstantTestPaymentModule::$calls);
        self::assertSame([$instantPart[5]], InstantTestPaymentModule::$releasedPartIds, 'The card is given back, once.');
        self::assertSame(
            [OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value],
            array_column($this->partsOf($order), 2),
            'The pending part of the provider can no longer be settled either.',
        );
        self::assertSame(
            [[$instantPart[5], OrderPaymentPartState::RELEASED->value, false]],
            $announced->getArrayCopy(),
            'One announcement per part its module gave back, once the transaction of the release is over; the pending part of the provider, which took nothing, is not announced.',
        );
    }

    public function testACancellationReplayedReleasesNothingMore(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        $this->cancel($order);
        $announced = $this->recordTheReleases();

        $this->cancel($order);

        self::assertSame(['settle', 'release'], InstantTestPaymentModule::$calls, 'releasePart is not called again.');
        self::assertCount(0, $announced);
    }

    public function testTheCardIsGivenBackWhenTheBuyerComesBackFromAFailedPayment(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        // The provider said no: its part is still pending, the card part is spent.
        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::PENDING->value],
            array_column($this->partsOf($order), 2),
        );
        $this->getService(SecurityContext::class)->setCustomerUser(
            CustomerQuery::create()->findPk($order->getCustomerId(), $this->getPropelConnection()) ?? throw new \RuntimeException('No customer.'),
        );

        $this->getService(CheckoutPaymentService::class)->cancel((int) $order->getId());

        self::assertTrue($this->reread($order)->isCancelled());
        self::assertSame([$this->partsOf($order)[0][5]], InstantTestPaymentModule::$releasedPartIds);
        self::assertSame(
            [OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value],
            array_column($this->partsOf($order), 2),
        );
    }

    public function testANewAttemptThatCannotReuseTheUnpaidOrderReleasesItsParts(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $first = $this->pay($cart);
        $firstInstantPartId = $this->partsOf($first)[0][5];

        // The buyer puts 40 on the card now: the unpaid order no longer describes the cart.
        InstantTestPaymentModule::$appliedAmount = '40';
        $second = $this->pay($cart);

        self::assertNotSame($first->getId(), $second->getId());
        self::assertTrue($this->reread($first)->isCancelled());
        self::assertSame(
            [OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value],
            array_column($this->partsOf($first), 2),
        );
        self::assertSame([$firstInstantPartId], InstantTestPaymentModule::$releasedPartIds);
        self::assertSame(['settle', 'release', 'settle'], InstantTestPaymentModule::$calls, 'The first card part is given back before the new one is spent.');
        self::assertSame(
            [['40.000000', OrderPaymentPartState::SETTLED->value], ['60.000000', OrderPaymentPartState::PENDING->value]],
            array_map(static fn (array $part): array => [$part[1], $part[2]], $this->partsOf($second)),
        );
    }

    public function testAPartItsModuleFailsToReleaseStaysSettledAndTheOthersAreReleased(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        SecondInstantTestPaymentModule::$secondAppliedAmount = '20';
        $order = $this->pay($cart);
        [$firstPart, $secondPart, $providerPart] = $this->partsOf($order);
        InstantTestPaymentModule::$unreleasablePartIds = [$firstPart[5]];
        $announced = $this->recordTheReleases();

        $this->cancel($order);

        self::assertTrue($this->reread($order)->isCancelled(), 'The cancellation stands.');
        self::assertSame(['settle', 'settle', 'release', 'release'], InstantTestPaymentModule::$calls);
        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value],
            array_column($this->partsOf($order), 2),
            'The part that failed is left settled, for a later attempt; the others are released.',
        );
        self::assertSame([$secondPart[5]], array_column($announced->getArrayCopy(), 0), 'Only what a module gave back is announced.');

        // The module is back: cancelling again gives back that part, and that part only.
        InstantTestPaymentModule::$unreleasablePartIds = [];
        $this->cancel($order);

        self::assertSame([$secondPart[5], $firstPart[5]], InstantTestPaymentModule::$releasedPartIds);
        self::assertSame(
            [OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value],
            array_column($this->partsOf($order), 2),
        );
    }

    /**
     * The buyer came back and paid with a new attempt that could not reuse the order: the
     * order was cancelled and its card part given back. The provider then confirms the
     * session of that old order. The order is not turned paid, since the card it counted on
     * was given back, but what the provider charged stays visible: its part is settled and
     * the history of the order says it is to be refunded. The pool of Propel is on, as in
     * production, and holds the parts as they were before the confirmation.
     */
    public function testTheProviderConfirmingACancelledOrderWhoseCardWasGivenBackIsRefusedAndRecorded(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $first = $this->pay($cart);
        InstantTestPaymentModule::$appliedAmount = '40';
        $second = $this->pay($cart);
        self::assertNotSame($first->getId(), $second->getId());
        self::assertTrue($this->reread($first)->isCancelled());
        $providerPartId = $this->partsOf($first)[1][5];
        Propel::enableInstancePooling();
        self::forgetPooledModels();
        OrderPaymentPartQuery::create()->filterByOrderId($first->getId())->find($this->getPropelConnection());

        try {
            $this->confirmThePayment($first, source: BalanceChargingTestPaymentModule::getModuleCode());
            self::fail('The payment of a cancelled order whose card was given back must be refused.');
        } catch (OrderStatusTransitionRefusedException $refused) {
            self::assertStringNotContainsString('30.0', $refused->getMessage(), 'No amount in the message.');
            self::assertStringNotContainsString('70.0', $refused->getMessage(), 'No amount in the message.');
        }

        self::assertTrue($this->reread($first)->isCancelled(), 'The order stays cancelled.');
        self::assertSame(
            [OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::SETTLED->value],
            array_column($this->partsOf($first), 2),
            'The card stays given back; what the provider charged is recorded as settled.',
        );
        $entries = OrderHistoryQuery::create()
            ->filterByOrderId($first->getId())
            ->filterByEventType('payment_confirmed_after_release')
            ->find($this->getPropelConnection());
        self::assertCount(1, $entries, 'The history says a payment is to be refunded.');
        self::assertSame(['payment_part_id' => $providerPartId], $entries->getFirst()->getDecodedPayload());
        self::assertSame(['settle', 'release', 'settle'], InstantTestPaymentModule::$calls, 'Nothing is settled again.');

        // The provider notifies twice: still refused, and recorded once.
        try {
            $this->confirmThePayment($first, source: BalanceChargingTestPaymentModule::getModuleCode());
            self::fail('The second notification must be refused as well.');
        } catch (OrderStatusTransitionRefusedException) {
        }

        self::assertCount(1, OrderHistoryQuery::create()->filterByOrderId($first->getId())->filterByEventType('payment_confirmed_after_release')->find($this->getPropelConnection()));
        self::assertTrue($this->reread($first)->isCancelled());
    }

    /**
     * The back office, or a module that is not the provider of the order, asks for a paid
     * status without forcing it: refused, and nothing is written, since no provider said it
     * charged anything.
     */
    public function testAPaidStatusNotFromTheProviderOfACancelledOrderIsRefusedWithoutAnyWrite(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        $this->cancel($order);

        foreach ([null, InstantTestPaymentModule::getModuleCode()] as $source) {
            try {
                $this->confirmThePayment($order, source: $source);
                self::fail('A paid status must be refused on a cancelled order whose card was given back.');
            } catch (OrderStatusTransitionRefusedException) {
            }
        }

        self::assertTrue($this->reread($order)->isCancelled());
        self::assertSame(
            [OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value],
            array_column($this->partsOf($order), 2),
        );
        self::assertCount(0, OrderHistoryQuery::create()->filterByOrderId($order->getId())->filterByEventType('payment_confirmed_after_release')->find($this->getPropelConnection()));
    }

    /**
     * The card could not be given back at the cancellation and is still spent: the order may
     * be paid. When its provider confirms the payment, its part, released by the
     * cancellation, is settled, since the provider charged it; when the back office turns it
     * paid, it is left released and the gap is logged.
     */
    public function testTheProviderConfirmingAnOrderWhoseCardWasKeptSettlesItsReleasedPart(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        InstantTestPaymentModule::$unreleasablePartIds = [$this->partsOf($order)[0][5]];
        $this->cancel($order);
        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::RELEASED->value],
            array_column($this->partsOf($order), 2),
        );

        $this->confirmThePayment($order, source: BalanceChargingTestPaymentModule::getModuleCode());

        self::assertTrue($this->reread($order)->isPaid(false));
        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::SETTLED->value],
            array_column($this->partsOf($order), 2),
        );
    }

    public function testTheBackOfficeTurningAnOrderWhoseCardWasKeptPaidLeavesTheProviderPartReleased(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        InstantTestPaymentModule::$unreleasablePartIds = [$this->partsOf($order)[0][5]];
        $this->cancel($order);

        $this->confirmThePayment($order);

        self::assertTrue($this->reread($order)->isPaid(false));
        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::RELEASED->value],
            array_column($this->partsOf($order), 2),
            'No provider said it charged the balance.',
        );
    }

    /**
     * An order paid with one means only has no part: cancelled, its payment is attempted
     * the way it always was, its module is asked to pay.
     */
    public function testACancelledOrderWithNoPartIsPaidTheWayItWas(): void
    {
        InstantTestPaymentModule::$appliedAmount = '0';
        $order = $this->pay($this->cartPaidWith($this->providerModule));
        self::assertSame([], $this->partsOf($order));
        $this->cancel($order);
        BalanceChargingTestPaymentModule::reset();

        $this->dispatcher()->dispatch(new OrderPaymentEvent($this->reread($order)), TheliaEvents::MODULE_PAY);

        self::assertSame([(int) $order->getId()], BalanceChargingTestPaymentModule::$paidOrderIds);
    }

    /**
     * The back office forces the transition: the order is turned paid, and nothing is
     * settled for it, since the card part was given back and the provider never confirmed.
     */
    public function testTheBackOfficeMayForceACancelledOrderWhoseCardWasGivenBackToPaid(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        $this->cancel($order);

        $this->confirmThePayment($order, forced: true);

        self::assertTrue($this->reread($order)->isPaid(false));
        self::assertSame(['settle', 'release'], InstantTestPaymentModule::$calls, 'Nothing is settled again.');
        self::assertSame(
            [OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value],
            array_column($this->partsOf($order), 2),
            'No part goes back to pending or settled.',
        );
        self::assertCount(0, OrderHistoryQuery::create()->filterByOrderId($order->getId())->filterByEventType('payment_confirmed_after_release')->find($this->getPropelConnection()));
    }

    /**
     * An instant part still pending: a settlement that did not run. Turning the order paid
     * would count on a card nothing charged, so only the back office, forcing it, may.
     */
    public function testAnOrderWithAPendingInstantPartIsTurnedPaidOnlyWhenForced(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        $this->getPropelConnection()
            ->exec('UPDATE `order_payment_part` SET `state` = \'pending\' WHERE `id` = '.(int) $this->partsOf($order)[0][5]);

        try {
            $this->confirmThePayment($order);
            self::fail('A paid status must be refused while an instant part is pending.');
        } catch (OrderStatusTransitionRefusedException) {
        }

        self::assertTrue($this->reread($order)->isNotPaid(true), 'The order is left unpaid.');
        self::assertSame(
            [OrderPaymentPartState::PENDING->value, OrderPaymentPartState::PENDING->value],
            array_column($this->partsOf($order), 2),
        );

        $this->confirmThePayment($order, forced: true);

        self::assertTrue($this->reread($order)->isPaid(false), 'Forced, the transition is applied.');
        self::assertSame(
            [OrderPaymentPartState::PENDING->value, OrderPaymentPartState::SETTLED->value],
            array_column($this->partsOf($order), 2),
            'The instant part is not settled by a forced status: the back office answers for it.',
        );
        self::assertSame(['settle'], InstantTestPaymentModule::$calls);
    }

    /**
     * A new attempt to pay a cancelled order, by a module or a replay: refused before any
     * part is settled and before its module is asked to pay.
     */
    public function testAPaymentOfACancelledOrderIsRefusedBeforeItsProviderIsAskedToPay(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        $this->cancel($order);
        BalanceChargingTestPaymentModule::reset();

        try {
            $this->dispatcher()->dispatch(new OrderPaymentEvent($this->reread($order)), TheliaEvents::MODULE_PAY);
            self::fail('The payment of a cancelled order must be refused.');
        } catch (CancelledOrderPaymentRefusedException) {
        }

        self::assertSame([], BalanceChargingTestPaymentModule::$paidOrderIds, 'The provider is not asked to pay.');
        self::assertSame(['settle', 'release'], InstantTestPaymentModule::$calls);
        self::assertTrue($this->reread($order)->isCancelled());
    }

    /**
     * A cancelled order the back office turned unpaid again: its instant parts are still
     * given back, so a new attempt must not charge the provider the balance of a payment
     * that no longer holds.
     */
    public function testAPaymentOfAnOrderTurnedUnpaidAfterItsCardWasGivenBackIsRefused(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        $this->cancel($order);
        OrderQuery::create()
            ->filterById($order->getId())
            ->update(['StatusId' => OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_NOT_PAID)?->getId()], $this->getPropelConnection());
        BalanceChargingTestPaymentModule::reset();
        self::assertFalse($this->reread($order)->isCancelled(), 'The order is back to unpaid.');

        try {
            $this->dispatcher()->dispatch(new OrderPaymentEvent($this->reread($order)), TheliaEvents::MODULE_PAY);
            self::fail('The payment of an order whose card was given back must be refused.');
        } catch (CancelledOrderPaymentRefusedException $refused) {
            self::assertStringNotContainsString('cancelled', $refused->getMessage(), 'The order is not cancelled: the message says what is true.');
            self::assertStringContainsString((string) $order->getRef(), $refused->getMessage());
            self::assertStringNotContainsString('30', str_replace((string) $order->getRef(), '', $refused->getMessage()), 'No amount in the message.');
        }

        self::assertSame([], BalanceChargingTestPaymentModule::$paidOrderIds, 'The provider is not asked to pay.');
        self::assertSame(['settle', 'release'], InstantTestPaymentModule::$calls);
        self::assertSame(
            [OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value],
            array_column($this->partsOf($order), 2),
        );
    }

    /**
     * The date of the last update follows the state, whichever way the state is written:
     * by a release, and by the confirmation of the provider.
     */
    public function testTheUpdateDateOfAPartFollowsItsStateWhenItIsReleased(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        $this->backdateTheParts($order);

        $this->cancel($order);

        foreach ($this->updateDatesOf($order) as $updatedAt) {
            self::assertGreaterThan('2020-01-01 00:00:00', $updatedAt, 'The release wrote the date of the update.');
        }
    }

    public function testTheUpdateDateOfTheProviderPartFollowsItsConfirmation(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        $this->backdateTheParts($order);

        $this->confirmThePayment($order, source: BalanceChargingTestPaymentModule::getModuleCode());

        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::SETTLED->value],
            array_column($this->partsOf($order), 2),
        );
        self::assertSame('2020-01-01 00:00:00', $this->updateDatesOf($order)[0], 'The card part was not touched.');
        self::assertGreaterThan('2020-01-01 00:00:00', $this->updateDatesOf($order)[1], 'The provider part was.');
    }

    /**
     * Inside a transaction someone else opened and marked for rollback, nothing written here
     * can be committed: no module is asked to give a part back.
     */
    public function testAReleaseInsideATransactionMarkedForRollbackAsksNoModule(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        $connection = Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        $connection->rollBack();
        self::assertFalse($connection->isCommitable());

        try {
            $this->getService(OrderPaymentPartSettlement::class)->releasePartsOf($this->reread($order));
            self::fail('A release that cannot be committed must not start.');
        } catch (\LogicException $failure) {
            self::assertStringContainsString('already marked for rollback', $failure->getMessage());
        }

        self::assertSame(['settle'], InstantTestPaymentModule::$calls, 'The card was not given back.');
    }

    public function testAPaidOrderOrAnOrderWithNoPartReleasesNothing(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        $this->confirmThePayment($order);
        $announced = $this->recordTheReleases();

        self::assertSame(['settle'], InstantTestPaymentModule::$calls, 'Paying releases nothing.');

        InstantTestPaymentModule::$appliedAmount = '0';
        $whole = $this->pay($this->cartPaidWith($this->providerModule));
        $this->cancel($whole);

        self::assertSame([], $this->partsOf($whole));
        self::assertCount(0, $announced);
    }

    public function testTheSettledPartOfTheProviderIsLeftToARefund(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        $this->confirmThePayment($order);

        $this->cancel($order);

        self::assertSame(
            [OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::SETTLED->value],
            array_column($this->partsOf($order), 2),
            'The card is given back; what the provider charged is refunded through the provider, not here.',
        );
        self::assertSame(['settle', 'release'], InstantTestPaymentModule::$calls);
    }

    public function testAPartReleasedByAnotherRequestIsNotReleasedAgainWithThePoolOfPropelOn(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        Propel::enableInstancePooling();
        self::forgetPooledModels();
        $pooled = OrderPaymentPartQuery::create()->filterByOrderId($order->getId())->orderByPosition()->find($this->getPropelConnection());
        self::assertSame(OrderPaymentPartState::SETTLED->value, $pooled->getFirst()->getState());

        // Another request released the card part and committed: this process knows nothing of it.
        $this->getPropelConnection()
            ->exec('UPDATE `order_payment_part` SET `state` = \'released\' WHERE `id` = '.(int) $pooled->getFirst()->getId());

        $this->cancel($order);

        self::assertSame(['settle'], InstantTestPaymentModule::$calls, 'The part is read as it is stored, released, and left alone.');
        self::assertSame(
            [OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value],
            $this->statesThroughThePool($order),
        );
    }

    public function testAPartIsReleasedOnceAndReadAsReleasedWithThePoolOfPropelOn(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);
        Propel::enableInstancePooling();
        self::forgetPooledModels();
        OrderPaymentPartQuery::create()->filterByOrderId($order->getId())->find($this->getPropelConnection());

        $this->cancel($order);
        $this->cancel($order);

        self::assertSame(['settle', 'release'], InstantTestPaymentModule::$calls);
        self::assertSame(
            [OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value],
            $this->statesThroughThePool($order),
        );
        self::forgetPooledModels();
        self::assertSame(
            [OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value],
            $this->statesThroughThePool($order),
            'And the row says the same.',
        );
    }

    public function testACancelledOrderThatInstantPartsPaidIsNotTurnedPaidAgainByANewPayment(): void
    {
        $cart = $this->cartPaidWith($this->instantModule);
        InstantTestPaymentModule::$appliedAmount = '120';
        $order = $this->pay($cart);
        $this->cancel($order);

        // A module, or a replay, raises MODULE_PAY again on the cancelled order: its only
        // part is released, nothing pays it any more.
        try {
            $this->dispatcher()->dispatch(new OrderPaymentEvent($this->reread($order)), TheliaEvents::MODULE_PAY);
            self::fail('The payment of a cancelled order must be refused.');
        } catch (CancelledOrderPaymentRefusedException) {
        }

        $stored = $this->reread($order);
        self::assertFalse($stored->isPaid(false), 'A released part pays nothing.');
        self::assertTrue($stored->isCancelled());
        self::assertSame(['settle', 'release'], InstantTestPaymentModule::$calls, 'Neither settled again nor asked to pay().');
        self::assertSame([OrderPaymentPartState::RELEASED->value], array_column($this->partsOf($order), 2));
    }

    private function backdateTheParts(Order $order): void
    {
        $this->getPropelConnection()->exec(\sprintf("UPDATE `order_payment_part` SET `updated_at` = '2020-01-01 00:00:00' WHERE `order_id` = %d", (int) $order->getId()));
    }

    /**
     * @return list<string> in position order, read from the table
     */
    private function updateDatesOf(Order $order): array
    {
        $statement = $this->getPropelConnection()->prepare('SELECT `updated_at` FROM `order_payment_part` WHERE `order_id` = ? ORDER BY `position`, `id`');
        $statement->execute([(int) $order->getId()]);

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function cancel(Order $order): void
    {
        $this->reread($order)->setCancelled($this->dispatcher());
    }

    /**
     * The parts announced as released: id, state carried by the event, and whether the
     * transaction of the release was still open when it was announced.
     *
     * @return \ArrayObject<int, array{0: int, 1: string|null, 2: bool}>
     */
    private function recordTheReleases(): \ArrayObject
    {
        $announced = new \ArrayObject();
        $levels = Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME)->getNestedTransactionCount();
        $this->listen(TheliaEvents::ORDER_PAYMENT_PART_RELEASE, static function (OrderPaymentPartEvent $event) use ($announced, $levels): void {
            $announced[] = [
                (int) $event->getPart()->getId(),
                $event->getPart()->getState(),
                Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME)->getNestedTransactionCount() > $levels,
            ];
        });

        return $announced;
    }

    /**
     * Placed through the theme's service, which reuses the unpaid order of the cart when it
     * still describes it. The delivery address is the cart's own.
     */
    private function pay(Cart $cart): Order
    {
        $cart = $this->rereadCart($cart);

        $outcome = $this->getService(CheckoutPaymentService::class)->payAndReturnOutcome(
            new OrderPaymentRequest(
                $cart,
                (int) $cart->getAddressDeliveryId(),
                (int) $cart->getAddressInvoiceId(),
                (int) $cart->getDeliveryModuleId(),
                (int) $cart->getPaymentModuleId(),
                $this->factory->currency(),
                $this->factory->lang(),
            ),
        );

        return $outcome->placedOrder;
    }

    /**
     * @return list<array{0: int|null, 1: string|null, 2: string|null, 3: bool|null, 4: int|null, 5: int}>
     */
    private function partsOf(Order $order): array
    {
        $parts = OrderPaymentPartQuery::create()
            ->filterByOrderId($order->getId())
            ->orderByPosition()
            ->find($this->getPropelConnection());

        return array_values(array_map(
            static fn (OrderPaymentPart $part): array => [
                $part->getPaymentModuleId(),
                $part->getAmount(),
                $part->getState(),
                $part->getIsPrimary(),
                $part->getPosition(),
                (int) $part->getId(),
            ],
            iterator_to_array($parts),
        ));
    }

    private function rereadCart(Cart $cart): Cart
    {
        return CartQuery::create()->findPk($cart->getId(), $this->getPropelConnection())
            ?? throw new \RuntimeException('The cart went away.');
    }

    private function reread(Order $order): Order
    {
        return OrderQuery::create()->findPk($order->getId(), $this->getPropelConnection())
            ?? throw new \RuntimeException('The order went away.');
    }

    private function cartPaidWith(Module $paymentModule): Cart
    {
        $country = $this->factory->country();
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');

        $area = (new Area())->setName('Split payment test area '.uniqid());
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($deliveryModule->getId())->save($this->getPropelConnection());

        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true);
            $event->setPostage(new OrderPostage(0.0, 0.0, 'VAT'));
            $event->stopPropagation();
        });
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        });

        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->factory->address($customer, $country);

        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );

        $cart = $this->factory->cart($customer);
        $this->factory->cartItem($cart, $product, overrides: ['price' => '100.000000', 'promoPrice' => '100.000000']);

        $cart
            ->setAddressDeliveryId($this->factory->cartAddress(null, $country)->getId())
            ->setAddressInvoiceId($this->factory->cartAddress(null, $country)->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($paymentModule->getId())
            ->setPostage('0')
            ->setPostageTax('0')
            ->save($this->getPropelConnection());

        return $cart;
    }

    private function turnMandatoryConsentsOff(): void
    {
        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }

    /**
     * The status turned paid: by the provider when its code is given as the source of the
     * event, the way a payment module confirming a payment does; by the back office otherwise.
     */
    private function confirmThePayment(Order $order, bool $forced = false, ?string $source = null): void
    {
        $event = (new OrderEvent($this->reread($order)))->setStatus((int) OrderStatusQuery::getPaidStatus()->getId());

        if (null !== $source) {
            $event->setSourceModuleCode($source);
        }

        if ($forced) {
            $event->forceStatusTransition();
        }

        $this->dispatcher()->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    /**
     * @return list<string>
     */
    private function statesThroughThePool(Order $order): array
    {
        return array_column($this->partsOf($order), 2);
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->listenAt($eventName, 512, $listener);
    }

    private function listenAt(string $eventName, int $priority, callable $listener): void
    {
        $this->dispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }
}
