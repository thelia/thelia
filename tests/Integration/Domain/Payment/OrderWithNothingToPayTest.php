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
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderNothingToPayEvent;
use Thelia\Core\Event\Order\OrderPayTotalEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\Coupon;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Tests\Support\Payment\BalanceChargingTestPaymentModule;
use Thelia\Tests\Support\Payment\UsesInstantPaymentModules;

/**
 * An order the discount leaves nothing to pay is paid by the core, and its payment module
 * is never asked to charge it.
 *
 * The order is placed through the CheckoutPaymentService the theme and the API use, with the
 * payment module of the test suite: a provider that records every order it is asked to pay.
 * The cart holds one product worth 100.00, its postage is free, and the discount is the one
 * an automatic promotion of 100.00 gives, written on the cart the way the coupon machinery
 * writes it.
 */
final class OrderWithNothingToPayTest extends IntegrationTestCase
{
    use ForgetsPooledModels;
    use UsesInstantPaymentModules;

    private FixtureFactory $factory;

    private Module $providerModule;

    private Product $product;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    /** @var list<int> */
    private array $confirmationEmailsOf = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->providerModule = $this->installPaymentModule(BalanceChargingTestPaymentModule::class, static::getContainer(), $this->getPropelConnection());
        $this->turnMandatoryConsentsOff();

        $this->listen(TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL, function (OrderEvent $event): void {
            $this->confirmationEmailsOf[] = (int) $event->getOrder()->getId();
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];
        $this->confirmationEmailsOf = [];
        $this->uninstallThePaymentModules(static::getContainer());
        $this->getService(ConsentProvider::class)->forgetCache();
        $this->getService(CouponManager::class)->invalidateCurrentCoupons();
        self::forgetPooledModels();
        Propel::disableInstancePooling();

        parent::tearDown();
    }

    public function testAnOrderACouponCoversInFullIsPaidWithoutAskingItsPaymentModule(): void
    {
        $cart = $this->cartWithAPromotionOf(100.0);

        $order = $this->pay($cart);

        self::assertTrue($this->reread($order)->isPaid(true), 'Nothing was left to pay: the order is paid.');
        self::assertSame([], BalanceChargingTestPaymentModule::$paidOrderIds, 'The payment module was never asked to charge nothing.');
        self::assertSame((int) $this->providerModule->getId(), (int) $this->reread($order)->getPaymentModuleId(), 'The order keeps the payment module the buyer chose.');
        self::assertSame(99, $this->stockOfTheProduct(), 'The paid order took its line out of the stock.');
        self::assertSame([(int) $order->getId()], $this->confirmationEmailsOf, 'The buyer is sent the confirmation, once.');
    }

    public function testAnOrderWithSomethingLeftToPayIsStillChargedByItsPaymentModule(): void
    {
        $cart = $this->cartWithAPromotionOf(30.0);

        $order = $this->pay($cart);

        self::assertFalse($this->reread($order)->isPaid(false), 'The provider has not confirmed yet.');
        self::assertSame([(int) $order->getId()], BalanceChargingTestPaymentModule::$paidOrderIds);
    }

    public function testTheTotalThePaymentModulesReadIsTheOneThatDecides(): void
    {
        $cart = $this->cartWithAPromotionOf(100.0);
        // A module that adds to the amount to pay (a fee, a deposit) after the core computed it.
        $this->listenAt(TheliaEvents::ORDER_PAY_GET_TOTAL, 64, static function (OrderPayTotalEvent $event): void {
            $event->setTotal($event->getTotal() + 5);
        });

        $order = $this->pay($cart);

        self::assertFalse($this->reread($order)->isPaid(false), 'Something is left to pay: the order waits for its payment.');
        self::assertSame([(int) $order->getId()], BalanceChargingTestPaymentModule::$paidOrderIds);
    }

    public function testAModuleCanRefuseThatAnOrderWithNothingToPayIsPaidWithoutItsPaymentModule(): void
    {
        $cart = $this->cartWithAPromotionOf(100.0);
        $askedAbout = [];
        $this->listen(TheliaEvents::ORDER_NOTHING_TO_PAY, static function (OrderNothingToPayEvent $event) use (&$askedAbout): void {
            $askedAbout[] = (int) $event->getOrder()->getId();
            // A subscription module that has the card authorised even for nothing.
            $event->refuse();
        });

        $order = $this->pay($cart);

        self::assertNotSame([], $askedAbout);
        self::assertSame([(int) $order->getId()], array_values(array_unique($askedAbout)), 'Asked about the order being paid, and that one only.');
        self::assertFalse($this->reread($order)->isPaid(false), 'The order waits for the payment module.');
        self::assertSame([(int) $order->getId()], BalanceChargingTestPaymentModule::$paidOrderIds, 'The payment module was asked, as before.');
    }

    private function cartWithAPromotionOf(float $amount): Cart
    {
        $country = $this->factory->country();
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');

        $area = (new Area())->setName('Nothing to pay test area '.uniqid());
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

        $this->product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );

        $cart = $this->factory->cart($customer);
        $this->factory->cartItem($cart, $this->product, overrides: ['price' => '100.000000', 'promoPrice' => '100.000000']);

        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(MatchForEveryone::class);
        $this->factory->coupon([
            'code' => null,
            'triggerMode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'effects' => ['amount' => $amount],
            'conditions' => $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions),
        ]);

        $cart
            ->setAddressDeliveryId($this->factory->cartAddress(null, $country)->getId())
            ->setAddressInvoiceId($this->factory->cartAddress(null, $country)->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($this->providerModule->getId())
            ->setPostage('0')
            ->setPostageTax('0')
            ->setDiscount((string) $this->getService(CouponManager::class)->discountOfCartWithoutSession($cart, $customer))
            ->save($this->getPropelConnection());

        self::assertEqualsWithDelta($amount, (float) $cart->getDiscount(), 0.001, 'The promotion gives the discount the test is about.');

        return $cart;
    }

    private function pay(Cart $cart): Order
    {
        $cart = CartQuery::create()->findPk($cart->getId(), $this->getPropelConnection()) ?? throw new \LogicException('The cart is gone.');

        return $this->getService(CheckoutPaymentService::class)->payAndReturnOutcome(
            new OrderPaymentRequest(
                $cart,
                (int) $cart->getAddressDeliveryId(),
                (int) $cart->getAddressInvoiceId(),
                (int) $cart->getDeliveryModuleId(),
                (int) $cart->getPaymentModuleId(),
                $this->factory->currency(),
                $this->factory->lang(),
            ),
        )->placedOrder;
    }

    private function reread(Order $order): Order
    {
        return OrderQuery::create()->findPk($order->getId(), $this->getPropelConnection())
            ?? throw new \LogicException('The order is gone.');
    }

    private function stockOfTheProduct(): int
    {
        return (int) ProductSaleElementsQuery::create()
            ->filterByProductId($this->product->getId())
            ->findOne($this->getPropelConnection())
            ?->getQuantity();
    }

    private function turnMandatoryConsentsOff(): void
    {
        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
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
