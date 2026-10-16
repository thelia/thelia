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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Order\Service\OrderFingerprint;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Domain\Payment\Exception\PaymentCompositionRefusedException;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderPaymentPartQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Payment\BalanceChargingTestPaymentModule;
use Thelia\Tests\Support\Payment\InstantTestPaymentModule;
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;
use Thelia\Tests\Support\Payment\UsesInstantPaymentModules;
use Thelia\Tests\Support\Payment\UsesStoredMethodsPaymentModule;

/**
 * What an order is paid with is decided when it is placed: the amounts the instant payment
 * modules applied to the cart, and the module that charges the rest.
 *
 * The cart is worth 100.00 and its postage is free. MODULE_PAY is not stubbed: the payment
 * modules are the test modules, installed in the container under their service ids, and the
 * placement goes through the same CheckoutPaymentService the theme and the API use.
 */
final class OrderPaymentPlacementTest extends IntegrationTestCase
{
    use UsesInstantPaymentModules;
    use UsesStoredMethodsPaymentModule;

    private FixtureFactory $factory;

    private Module $instantModule;

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
        $this->providerModule = $this->installPaymentModule(BalanceChargingTestPaymentModule::class, $container, $connection);
        $this->installTheStoredMethodsPaymentModule($container, $connection);
        $this->turnMandatoryConsentsOff();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];
        $this->uninstallThePaymentModules(static::getContainer());
        $this->uninstallTheStoredMethodsPaymentModule(static::getContainer());
        $this->getService(ConsentProvider::class)->forgetCache();

        parent::tearDown();
    }

    public function testACartWithNoInstantMeansIsPlacedWithNoPart(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);

        $order = $this->pay($cart);

        self::assertSame([], $this->partsOf($order));
        self::assertSame($this->providerModule->getId(), $this->reread($order)->getPaymentModuleId());
        self::assertSame([$order->getId()], BalanceChargingTestPaymentModule::$paidOrderIds);
    }

    public function testAnInstantMeansThatAppliedNothingLeavesTheOrderWithNoPart(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '0.000000';

        $order = $this->pay($cart);

        self::assertSame([], $this->partsOf($order));
        self::assertSame($this->providerModule->getId(), $this->reread($order)->getPaymentModuleId());
        self::assertNotSame([], InstantTestPaymentModule::$askedAboutCartIds, 'The module was asked, it applied nothing.');
    }

    public function testAnInstantShareAndAProviderThatChargesTheBalanceMakeTwoParts(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';

        $order = $this->pay($cart);

        self::assertSame(
            [
                // The instant part is settled by the payment that follows the placement, the
                // part of the provider waits for the provider (OrderPaymentPartSettlementTest).
                [$this->instantModule->getId(), '30.000000', OrderPaymentPartState::SETTLED->value, false, 0],
                [$this->providerModule->getId(), '70.000000', OrderPaymentPartState::PENDING->value, true, 1],
            ],
            $this->partsOf($order),
        );
        self::assertSame($this->providerModule->getId(), $this->reread($order)->getPaymentModuleId());
    }

    public function testAnInstantShareThatCoversTheOrderIsTheOnlyPartAndTheModuleOfTheOrder(): void
    {
        $cart = $this->cartPaidWith($this->instantModule);
        InstantTestPaymentModule::$appliedAmount = '120';

        $order = $this->pay($cart);

        self::assertSame(
            [[$this->instantModule->getId(), '100.000000', OrderPaymentPartState::SETTLED->value, true, 0]],
            $this->partsOf($order),
        );
        self::assertSame($this->instantModule->getId(), $this->reread($order)->getPaymentModuleId());
    }

    public function testAnInstantShareCoveringTheOrderMakesTheInstantModuleTheOneOfTheOrderWhateverProviderWasChosen(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '100';

        $order = $this->pay($cart);

        self::assertSame(
            [[$this->instantModule->getId(), '100.000000', OrderPaymentPartState::SETTLED->value, true, 0]],
            $this->partsOf($order),
        );
        self::assertSame($this->instantModule->getId(), $this->reread($order)->getPaymentModuleId());
    }

    public function testAProviderThatDoesNotChargeTheBalanceRefusesTheCompositionBeforeAnyOrderIsWritten(): void
    {
        $cart = $this->cartPaidWith($this->storedMethodsModule());
        InstantTestPaymentModule::$appliedAmount = '30';

        try {
            $this->pay($cart);
            self::fail('The composition must be refused.');
        } catch (PaymentCompositionRefusedException $refusal) {
            self::assertSame(PaymentCompositionRefusedException::PROVIDER_CANNOT_CHARGE_BALANCE, $refusal->reason());
        }

        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
        self::assertSame(0, OrderPaymentPartQuery::create()->useOrderQuery()->filterByCartId($cart->getId())->endUse()->count($this->getPropelConnection()));
        self::assertSame([], StoredMethodsPaymentModule::$calls, 'The provider was never asked to pay.');
    }

    public function testARefusedCompositionLeavesThePreviousUnpaidOrderStanding(): void
    {
        $cart = $this->cartPaidWith($this->storedMethodsModule());
        $unpaid = $this->pay($cart);
        self::assertSame([], $this->partsOf($unpaid));

        InstantTestPaymentModule::$appliedAmount = '30';

        try {
            $this->pay($cart);
            self::fail('The composition must be refused.');
        } catch (PaymentCompositionRefusedException) {
        }

        self::assertSame(1, OrderQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
        self::assertFalse($this->reread($unpaid)->isCancelled(), 'The unpaid order is still there to be paid.');
    }

    public function testTheOrderIsLeftAloneWhenTheInstantMeansLeaveNothingForAnythingToCharge(): void
    {
        // The instant module is the one chosen and applies 30 of 100: nobody is left to
        // charge the 70, which is the same refusal.
        $cart = $this->cartPaidWith($this->instantModule);
        InstantTestPaymentModule::$appliedAmount = '30';

        try {
            $this->pay($cart);
            self::fail('The composition must be refused.');
        } catch (PaymentCompositionRefusedException $refusal) {
            self::assertSame(PaymentCompositionRefusedException::REMAINDER_WITHOUT_PROVIDER, $refusal->reason());
        }

        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    public function testAnAppliedAmountThatChangedBetweenTwoAttemptsPlacesAnotherOrder(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $first = $this->pay($cart);

        InstantTestPaymentModule::$appliedAmount = '40';
        $second = $this->pay($cart);

        self::assertNotSame($first->getId(), $second->getId());
        self::assertTrue($this->reread($first)->isCancelled(), 'The order that carried the old amount is taken back.');
        self::assertSame(
            [
                [$this->instantModule->getId(), '40.000000', OrderPaymentPartState::SETTLED->value, false, 0],
                [$this->providerModule->getId(), '60.000000', OrderPaymentPartState::PENDING->value, true, 1],
            ],
            $this->partsOf($second),
        );
    }

    public function testTheSameAppliedAmountPresentsTheSameOrderAgain(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $first = $this->pay($cart);

        $second = $this->pay($cart);

        self::assertSame($first->getId(), $second->getId());
        self::assertFalse($this->reread($first)->isCancelled());
        self::assertCount(2, $this->partsOf($first), 'The parts were not written a second time.');
    }

    /**
     * A refund does not give the instant parts back: the module returns the card on its own,
     * the part stays `settled`, and the back office may turn the order unpaid again. A new
     * attempt that presented the same order would find the card part settled, charge the
     * provider the balance only, and turn the order paid with the card part missing. Such an
     * order is not presented again: it is taken back and another one is placed, whose card
     * part is spent anew.
     */
    public function testAnOrderRefundedAndTurnedUnpaidAgainIsNotPresentedAgainWithItsCardPartSpent(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        InstantTestPaymentModule::$appliedAmount = '30';
        $first = $this->pay($cart);
        $firstInstantPartId = (int) OrderPaymentPartQuery::create()->filterByOrderId($first->getId())->filterByIsPrimary(false)->findOne($this->getPropelConnection())?->getId();

        // The module of the card gives it back by itself when the order is refunded, and leaves the part as it was.
        $cardGivenBack = 0;
        $this->listen(TheliaEvents::ORDER_UPDATE_STATUS, static function (OrderEvent $event) use (&$cardGivenBack): void {
            if ($event->getOrder()->isRefunded(false)) {
                ++$cardGivenBack;
            }
        });

        foreach ([OrderStatus::CODE_PAID, OrderStatus::CODE_REFUNDED, OrderStatus::CODE_NOT_PAID] as $statusCode) {
            $this->dispatcher()->dispatch(
                (new OrderEvent($this->reread($first)))->setStatus(OrderStatusQuery::create()->findOneByCode($statusCode, $this->getPropelConnection())?->getId()),
                TheliaEvents::ORDER_UPDATE_STATUS,
            );
        }

        self::assertSame(1, $cardGivenBack, 'The order was refunded.');
        self::assertTrue($this->reread($first)->isNotPaid(), 'The back office turned it unpaid again.');
        self::assertSame([$firstInstantPartId], InstantTestPaymentModule::$settledPartIds);

        $second = $this->pay($cart);

        self::assertNotSame($first->getId(), $second->getId(), 'The refunded order is not presented again.');
        self::assertTrue($this->reread($first)->isCancelled(), 'It is taken back.');
        self::assertSame(
            [
                [$this->instantModule->getId(), '30.000000', OrderPaymentPartState::SETTLED->value, false, 0],
                [$this->providerModule->getId(), '70.000000', OrderPaymentPartState::PENDING->value, true, 1],
            ],
            $this->partsOf($second),
        );
        self::assertCount(2, InstantTestPaymentModule::$settledPartIds, 'The card is spent for the new order too.');
        self::assertSame(
            [$firstInstantPartId],
            InstantTestPaymentModule::$releasedPartIds,
            'Taking the refunded order back asks the module to give its part back: the module tells a part it already gave back by its id.',
        );
    }

    public function testTheFingerprintOfAnOrderWithNoPartIsTheOneItWasBeforePaymentsCouldBeSplit(): void
    {
        $cart = $this->cartPaidWith($this->providerModule);
        $order = $this->pay($cart);
        $cart = $this->rereadCart($cart);
        $fingerprint = $this->getService(OrderFingerprint::class);

        $ofCart = new \ReflectionMethod($fingerprint, 'ofCart');
        $frozenBeforeThisChange = hash('sha256', json_encode(
            $ofCart->invoke($fingerprint, $cart, (int) $cart->getDeliveryModuleId(), (int) $cart->getPaymentModuleId(), (int) $cart->getCurrencyId(), null, null),
            \JSON_THROW_ON_ERROR,
        ));

        self::assertSame($frozenBeforeThisChange, $this->reread($order)->getCartFingerprint());
        self::assertArrayNotHasKey(
            'payment_parts',
            $ofCart->invoke($fingerprint, $cart, (int) $cart->getDeliveryModuleId(), (int) $cart->getPaymentModuleId(), (int) $cart->getCurrencyId(), null, null),
        );
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
     * @return list<array{0: int|null, 1: string|null, 2: string|null, 3: bool|null, 4: int|null}>
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

    private function storedMethodsModule(): Module
    {
        return ModuleQuery::create()->findOneByCode(StoredMethodsPaymentModule::getModuleCode(), $this->getPropelConnection())
            ?? throw new \RuntimeException('The provider test module is not installed.');
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

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->dispatcher()->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }
}
