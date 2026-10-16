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

use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\Order\OrderPaymentPartEvent;
use Thelia\Core\Event\Order\OrderPayTotalEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Domain\Payment\Exception\InstantPaymentRefusedException;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\Map\OrderPaymentPartTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderPaymentPartQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Tests\Support\Payment\BalanceChargingTestPaymentModule;
use Thelia\Tests\Support\Payment\InstantTestPaymentModule;
use Thelia\Tests\Support\Payment\SecondInstantTestPaymentModule;
use Thelia\Tests\Support\Payment\UsesInstantPaymentModules;

/**
 * Settling the parts of an order paid with several means, and the order turning paid.
 *
 * MODULE_PAY is not stubbed: the payment modules are the test modules, installed in the
 * container under their service ids, and the order is placed and paid again through the
 * same CheckoutPaymentService the theme and the API use. The cart is worth 100.00 and its
 * postage is free.
 */
final class OrderPaymentPartSettlementTest extends IntegrationTestCase
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

    public function testTheInstantPartIsSettledBeforeTheProviderIsAskedAndTheOrderIsPaidWhenTheProviderConfirms(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';

        $order = $this->pay($cart);

        self::assertSame(
            [[OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::PENDING->value]],
            BalanceChargingTestPaymentModule::$partStatesWhenAskedToPay,
            'The instant part was settled when the provider was asked, the provider part is waiting.',
        );
        self::assertSame(['settle'], InstantTestPaymentModule::$calls);
        self::assertFalse($this->reread($order)->isPaid(false), 'The provider has not confirmed yet.');

        $this->confirmThePayment($order);

        self::assertTrue($this->reread($order)->isPaid(false));
        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::SETTLED->value],
            array_column($this->partsOf($order), 2),
        );
        self::assertSame(['settle'], InstantTestPaymentModule::$calls, 'Confirming the provider settles nothing more.');
    }

    public function testTheSettlementOfAnInstantPartIsAnnouncedOnceItIsCommitted(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $announced = [];
        $this->listen(TheliaEvents::ORDER_PAYMENT_PART_SETTLE, static function (OrderPaymentPartEvent $event) use (&$announced): void {
            $announced[] = [(int) $event->getPart()->getId(), $event->getPart()->getState()];
        });

        $order = $this->pay($cart);
        $this->pay($cart);

        $instantPart = $this->partsOf($order)[0];
        self::assertSame([[$instantPart[5], OrderPaymentPartState::SETTLED->value]], $announced, 'Once, with the part as it is stored, and not again by the second attempt.');
    }

    public function testAnInstantShareThatCoversTheOrderPaysItWithoutAskingTheModuleToPay(): void
    {
        $cart = $this->cartPaidWith($this->instantModule);
        InstantTestPaymentModule::$appliedAmount = '120';

        $order = $this->pay($cart);

        $stored = $this->reread($order);
        self::assertTrue($stored->isPaid(false));
        self::assertSame($this->instantModule->getId(), $stored->getPaymentModuleId());
        self::assertSame(['settle'], InstantTestPaymentModule::$calls, 'The module settled its part and was never asked to pay().');
        self::assertSame(
            [[$this->instantModule->getId(), '100.000000', OrderPaymentPartState::SETTLED->value, true, 0]],
            array_map(static fn (array $part): array => \array_slice($part, 0, 5), $this->partsOf($order)),
        );
        self::assertSame([], BalanceChargingTestPaymentModule::$paidOrderIds);
    }

    public function testTheOrderThatTheInstantPartsCoverIsPaidOnceWhateverProviderWasChosen(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '60';
        SecondInstantTestPaymentModule::$secondAppliedAmount = '60';

        $order = $this->pay($cart);

        self::assertTrue($this->reread($order)->isPaid(false));
        self::assertSame([], BalanceChargingTestPaymentModule::$paidOrderIds, 'No provider part, nobody to ask.');
        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::SETTLED->value],
            array_column($this->partsOf($order), 2),
        );
    }

    public function testAPartThatRefusesTakesBackTheOnesSettledBeforeIt(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        SecondInstantTestPaymentModule::$secondAppliedAmount = '20';
        SecondInstantTestPaymentModule::$secondRefusesEveryPart = true;
        $announced = [];
        $this->listen(TheliaEvents::ORDER_PAYMENT_PART_SETTLE, static function () use (&$announced): void {
            $announced[] = true;
        });

        try {
            $this->pay($cart);
            self::fail('The payment must be stopped.');
        } catch (InstantPaymentRefusedException $refusal) {
            self::assertStringNotContainsString('20', $refusal->getMessage());
            self::assertStringNotContainsString('refuses', $refusal->getMessage(), 'Nothing the module said is passed on.');
        }

        $order = OrderQuery::create()->filterByCartId($cart->getId())->findOne($this->getPropelConnection());
        self::assertNotNull($order);
        self::assertSame(['settle', 'settle', 'release'], InstantTestPaymentModule::$calls);
        self::assertCount(1, InstantTestPaymentModule::$releasedPartIds);
        self::assertSame(InstantTestPaymentModule::$settledPartIds, InstantTestPaymentModule::$releasedPartIds, 'What was settled is what was released.');
        self::assertSame(
            [OrderPaymentPartState::PENDING->value, OrderPaymentPartState::PENDING->value, OrderPaymentPartState::PENDING->value],
            array_column($this->partsOf($order), 2),
            'No part is settled in the end.',
        );
        self::assertFalse($order->isPaid(false));
        self::assertSame([], BalanceChargingTestPaymentModule::$paidOrderIds, 'The provider was never asked.');
        self::assertSame([], $announced, 'Nothing is announced for a settlement that did not hold.');
    }

    public function testAPartThatRefusesFirstReleasesNothing(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        InstantTestPaymentModule::$refusesEveryPart = true;
        SecondInstantTestPaymentModule::$secondAppliedAmount = '20';

        try {
            $this->pay($cart);
            self::fail('The payment must be stopped.');
        } catch (InstantPaymentRefusedException) {
        }

        self::assertSame(['settle'], InstantTestPaymentModule::$calls);
    }

    /**
     * The module settled the part, then writing it failed: the part is given back to its
     * module all the same, since the module has taken the money. The failure is the
     * database's, and is not passed off as a refusal of the payment means.
     */
    public function testAPartWhoseWriteFailsOnceItsModuleSettledItIsGivenBack(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        // An order that does not exist: the write of the part breaks its foreign key.
        InstantTestPaymentModule::$whenAskedToSettle = static function (OrderPaymentPart $part): void {
            $part->setOrderId(2147483000);
        };

        try {
            $this->pay($cart);
            self::fail('The payment must be stopped.');
        } catch (\Throwable $failure) {
            self::assertNotInstanceOf(InstantPaymentRefusedException::class, $failure, 'A database error is not a refusal of the payment means.');
        }

        $order = OrderQuery::create()->filterByCartId($cart->getId())->findOne($this->getPropelConnection());
        self::assertNotNull($order);
        self::assertSame(['settle', 'release'], InstantTestPaymentModule::$calls, 'What the module settled is given back.');
        self::assertSame(InstantTestPaymentModule::$settledPartIds, InstantTestPaymentModule::$releasedPartIds);
        self::assertSame(
            [OrderPaymentPartState::PENDING->value, OrderPaymentPartState::PENDING->value],
            array_column($this->partsOf($order), 2),
        );
        self::assertSame([], BalanceChargingTestPaymentModule::$paidOrderIds, 'The provider was never asked.');
    }

    /**
     * Inside a transaction someone else opened and marked for rollback, nothing written here
     * can be committed: no module is asked to settle a part.
     */
    public function testASettlementInsideATransactionMarkedForRollbackAsksNoModule(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $connection = Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        $connection->rollBack();
        self::assertFalse($connection->isCommitable());

        try {
            $this->pay($cart);
            self::fail('A settlement that cannot be committed must not start.');
        } catch (\LogicException $failure) {
            self::assertStringContainsString('already marked for rollback', $failure->getMessage());
        }

        self::assertSame([], InstantTestPaymentModule::$calls, 'No module is asked.');
        self::assertSame([], BalanceChargingTestPaymentModule::$paidOrderIds);
    }

    public function testTheSettlementCanBeTriedAgainOnceTheModuleAccepts(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        SecondInstantTestPaymentModule::$secondAppliedAmount = '20';
        SecondInstantTestPaymentModule::$secondRefusesEveryPart = true;

        try {
            $this->pay($cart);
            self::fail('The payment must be stopped.');
        } catch (InstantPaymentRefusedException) {
        }

        SecondInstantTestPaymentModule::$secondRefusesEveryPart = false;
        $this->reopenTheTransactionTheRefusalMarked();
        $order = $this->pay($cart);

        self::assertSame(1, OrderQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()), 'The same order is presented again.');
        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::PENDING->value],
            array_column($this->partsOf($order), 2),
        );
        self::assertSame([$order->getId()], BalanceChargingTestPaymentModule::$paidOrderIds);
    }

    public function testAPartAlreadySettledIsNotSettledAgainWhenTheProviderIsAskedAgain(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $first = $this->pay($cart);

        // The provider answered with a failure and the buyer tries again: same order, same
        // parts, the provider is asked once more.
        $second = $this->pay($cart);

        self::assertSame($first->getId(), $second->getId());
        self::assertSame(['settle'], InstantTestPaymentModule::$calls, 'The card is spent once.');
        self::assertCount(1, InstantTestPaymentModule::$settledPartIds);
        self::assertSame([$first->getId(), $first->getId()], BalanceChargingTestPaymentModule::$paidOrderIds);
        self::assertSame(
            [[OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::PENDING->value], [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::PENDING->value]],
            BalanceChargingTestPaymentModule::$partStatesWhenAskedToPay,
        );
    }

    public function testAnOrderWithNoPartIsPaidTheWayItWas(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);

        $order = $this->pay($cart);

        self::assertSame([], $this->partsOf($order));
        self::assertSame([], InstantTestPaymentModule::$calls, 'Nothing was settled.');
        self::assertSame([$order->getId()], BalanceChargingTestPaymentModule::$paidOrderIds);
        self::assertSame([[]], BalanceChargingTestPaymentModule::$partStatesWhenAskedToPay);

        $this->confirmThePayment($order);
        self::assertTrue($this->reread($order)->isPaid(false));
        self::assertSame([], $this->partsOf($order), 'Paying an order with no part writes none.');
    }

    public function testTheChequeAndTheFreeOrderModulesAreAskedToPayAsBefore(): void
    {
        $cheque = ModuleQuery::create()->findOneByCode('Cheque', $this->getPropelConnection()) ?? throw new \RuntimeException('Cheque is not installed.');
        $chequeOrder = $this->pay($this->cartPaidWith($cheque));

        self::assertSame([], $this->partsOf($chequeOrder));
        self::assertFalse($this->reread($chequeOrder)->isPaid(false), 'A cheque is paid by hand, from the back office.');

        $freeOrderModule = ModuleQuery::create()->findOneByCode('FreeOrder', $this->getPropelConnection()) ?? throw new \RuntimeException('FreeOrder is not installed.');
        $freeOrder = $this->pay($this->cartPaidWith($freeOrderModule));

        self::assertSame([], $this->partsOf($freeOrder));
        self::assertTrue($this->reread($freeOrder)->isPaid(false), 'FreeOrder still turns its order paid itself.');
        self::assertSame([], InstantTestPaymentModule::$calls);
    }

    public function testThePayTotalOfAnOrderWithPartsIsTheBalanceWhateverAListenerSubtractedBeforeIt(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->pay($cart);

        // What a gift card module that was not adapted does: it takes its cards off the total.
        $this->listenAt(TheliaEvents::ORDER_PAY_GET_TOTAL, 60, static function (OrderPayTotalEvent $event): void {
            $event->setTotal($event->getTotal() - 30);
        });

        self::assertEqualsWithDelta(70.0, $this->payTotalOf($order), 0.000001);
    }

    public function testThePayTotalOfAnOrderPaidByInstantPartsOnlyIsNothing(): void
    {
        $cart = $this->cartPaidWith($this->instantModule);
        InstantTestPaymentModule::$appliedAmount = '100';
        $order = $this->pay($cart);

        self::assertEqualsWithDelta(0.0, $this->payTotalOf($order), 0.000001);
    }

    public function testThePayTotalOfAnOrderWithNoPartIsItsTotal(): void
    {
        $order = $this->pay($this->cartPaidWith($this->providerModule));

        self::assertEqualsWithDelta(100.0, $this->payTotalOf($order), 0.000001);
    }

    public function testThePayTotalWithoutThePostageTakesThePostageOffTheBalance(): void
    {
        $order = $this->orderWithTwoPartsPostageAndDiscount();

        self::assertEqualsWithDelta(60.0, $this->payTotalWith($order, includePostage: false, includeDiscount: true), 0.000001);
    }

    public function testThePayTotalWithoutTheDiscountAddsTheDiscountToTheBalance(): void
    {
        $order = $this->orderWithTwoPartsPostageAndDiscount();

        self::assertEqualsWithDelta(90.0, $this->payTotalWith($order, includePostage: true, includeDiscount: false), 0.000001);
    }

    public function testThePayTotalWithoutThePostageIsNeverBelowNothing(): void
    {
        InstantTestPaymentModule::$appliedAmount = '100';
        $order = $this->withPostageAndDiscount($this->pay($this->cartPaidWith($this->instantModule)), '10', '20');

        self::assertEqualsWithDelta(0.0, $this->payTotalWith($order, includePostage: false, includeDiscount: true), 0.000001);
    }

    public function testThePayTotalWithThePostageAndTheDiscountIsTheBalance(): void
    {
        $order = $this->orderWithTwoPartsPostageAndDiscount();

        self::assertEqualsWithDelta(70.0, $this->payTotalWith($order, includePostage: true, includeDiscount: true), 0.000001);
    }

    public function testThePayTotalWithoutThePostageOfAnOrderWithNoPartIsTheTotalWithoutIt(): void
    {
        $order = $this->withPostageAndDiscount($this->pay($this->cartPaidWith($this->providerModule)), '10', '20');

        self::assertEqualsWithDelta(80.0, $this->payTotalWith($order, includePostage: false, includeDiscount: true), 0.000001);
    }

    public function testTheAmountToChargeIsTheBalanceOfTheProviderOrTheWholeTotal(): void
    {
        InstantTestPaymentModule::$appliedAmount = '30';
        $split = $this->pay($this->cartPaidWith($this->providerModule));
        self::assertSame('70.000000', $this->amountToChargeOf($split));
        InstantTestPaymentModule::$appliedAmount = '0';

        $whole = $this->pay($this->cartPaidWith($this->providerModule));
        self::assertSame('100.000000', $this->amountToChargeOf($whole));
    }

    public function testAPartSettledByAnotherRequestIsNotSettledAgainWithThePoolOfPropelOn(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        // Placed with the pool off, as every test is, then the pool is turned on the way it
        // is in production, and fed with the parts as they were stored: pending.
        $order = $this->placeWithoutPaying($cart);
        Propel::enableInstancePooling();
        self::forgetPooledModels();
        $pooled = OrderPaymentPartQuery::create()->filterByOrderId($order->getId())->orderByPosition()->find($this->getPropelConnection());
        self::assertSame(OrderPaymentPartState::PENDING->value, $pooled->getFirst()->getState());

        // Another request settles the part and commits: this process knows nothing of it.
        $this->getPropelConnection()
            ->exec('UPDATE `order_payment_part` SET `state` = \'settled\' WHERE `id` = '.(int) $pooled->getFirst()->getId());

        $this->dispatcher()->dispatch(new OrderPaymentEvent($this->reread($order)), TheliaEvents::MODULE_PAY);

        self::assertSame([], InstantTestPaymentModule::$settledPartIds, 'The part is read as it is stored, settled, and left alone.');
        self::assertSame([], InstantTestPaymentModule::$calls);
        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::PENDING->value],
            $this->statesThroughThePool($order),
        );
    }

    public function testAPartIsSettledOnceAndReadAsSettledWithThePoolOfPropelOn(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $order = $this->placeWithoutPaying($cart);
        Propel::enableInstancePooling();
        self::forgetPooledModels();
        OrderPaymentPartQuery::create()->filterByOrderId($order->getId())->find($this->getPropelConnection());

        $this->dispatcher()->dispatch(new OrderPaymentEvent($this->reread($order)), TheliaEvents::MODULE_PAY);
        $this->dispatcher()->dispatch(new OrderPaymentEvent($this->reread($order)), TheliaEvents::MODULE_PAY);

        self::assertCount(1, InstantTestPaymentModule::$settledPartIds, 'Settled by the first call, left alone by the second.');
        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::PENDING->value],
            $this->statesThroughThePool($order),
        );
        self::forgetPooledModels();
        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::PENDING->value],
            $this->statesThroughThePool($order),
            'And the row says the same.',
        );
    }

    public function testARefusalWithThePoolOfPropelOnLeavesNoPartReadAsSettled(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        SecondInstantTestPaymentModule::$secondAppliedAmount = '20';
        SecondInstantTestPaymentModule::$secondRefusesEveryPart = true;
        $order = $this->placeWithoutPaying($cart);
        Propel::enableInstancePooling();
        self::forgetPooledModels();

        try {
            $this->dispatcher()->dispatch(new OrderPaymentEvent($this->reread($order)), TheliaEvents::MODULE_PAY);
            self::fail('The payment must be stopped.');
        } catch (InstantPaymentRefusedException) {
        }

        self::assertSame(
            [OrderPaymentPartState::PENDING->value, OrderPaymentPartState::PENDING->value, OrderPaymentPartState::PENDING->value],
            $this->statesThroughThePool($order),
        );
    }

    /**
     * The test runs inside nested levels of one transaction, so the rollback of a refused
     * settlement marks the whole of it as impossible to commit; in production the refusal
     * rolls back the transaction of the request, and the next request starts a new one.
     * The mark is lifted to let a second attempt run in the same test.
     */
    private function reopenTheTransactionTheRefusalMarked(): void
    {
        $connection = Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME);
        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        (new \ReflectionProperty(ConnectionWrapper::class, 'isUncommitable'))->setValue($connection, false);
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
     * The order as the placement leaves it, with its parts pending: what is written before
     * MODULE_PAY is raised, which is all this does.
     */
    private function placeWithoutPaying(Cart $cart): Order
    {
        $listener = static function (OrderPaymentEvent $event): void {
            $event->stopPropagation();
        };
        $this->listenAt(TheliaEvents::MODULE_PAY, 1000, $listener);

        try {
            $order = $this->pay($cart);
        } finally {
            $this->dispatcher()->removeListener(TheliaEvents::MODULE_PAY, $listener);
        }

        self::assertSame(
            [OrderPaymentPartState::PENDING->value, OrderPaymentPartState::PENDING->value],
            \array_slice(array_column($this->partsOf($order), 2), 0, 2),
        );

        return $order;
    }

    private function confirmThePayment(Order $order): void
    {
        $this->dispatcher()->dispatch(
            (new OrderEvent($this->reread($order)))->setStatus((int) OrderStatusQuery::getPaidStatus()->getId()),
            TheliaEvents::ORDER_UPDATE_STATUS,
        );
    }

    private function payTotalOf(Order $order): float
    {
        return (float) $this->providerInstance()->getOrderPayTotalAmount($this->reread($order));
    }

    /**
     * 100.00 of products, 10.00 of postage and 20.00 of discount stored on the order after
     * its placement. The parts, 30 instant and 70 provider, are those of the placement, whose
     * total was the products alone: the postage and the discount are set afterwards.
     */
    private function orderWithTwoPartsPostageAndDiscount(): Order
    {
        InstantTestPaymentModule::$appliedAmount = '30';

        return $this->withPostageAndDiscount($this->pay($this->cartPaidWith($this->providerModule)), '10', '20');
    }

    private function withPostageAndDiscount(Order $order, string $postage, string $discount): Order
    {
        $order = $this->reread($order);
        $order->setPostage($postage)->setPostageTax('0')->setDiscount($discount)->save($this->getPropelConnection());

        return $order;
    }

    private function payTotalWith(Order $order, bool $includePostage, bool $includeDiscount): float
    {
        return (float) $this->providerInstance()->getOrderPayTotalAmount($order, includeDiscount: $includeDiscount, includePostage: $includePostage);
    }

    private function amountToChargeOf(Order $order): string
    {
        return $this->providerInstance()->getAmountToCharge($this->reread($order));
    }

    private function providerInstance(): BalanceChargingTestPaymentModule
    {
        return static::getContainer()->get('module.'.BalanceChargingTestPaymentModule::getModuleCode());
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
