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
use Thelia\Core\Event\Order\OrderManualEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Payment\OrderPaymentComposer;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\ConsentQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentPartQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Payment\BalanceChargingTestPaymentModule;
use Thelia\Tests\Support\Payment\InstantTestPaymentModule;
use Thelia\Tests\Support\Payment\SecondInstantTestPaymentModule;
use Thelia\Tests\Support\Payment\UsesInstantPaymentModules;

/**
 * What the composer reads from the instant payment modules, and the cart it is asked about.
 */
final class OrderPaymentComposerTest extends IntegrationTestCase
{
    use UsesInstantPaymentModules;

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

        foreach (ConsentQuery::create()->filterByMandatory(1)->find($connection) as $consent) {
            $consent->setActive(0)->save($connection);
        }
        $this->getService(ConsentProvider::class)->forgetCache();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->getService(EventDispatcherInterface::class)->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];
        $this->uninstallThePaymentModules(static::getContainer());
        $this->getService(ConsentProvider::class)->forgetCache();

        parent::tearDown();
    }

    /**
     * A module may answer in the scientific notation of a float: the amount is a decimal
     * string for the splitter, so it is either read as one or ignored, and never a 500.
     */
    public function testAScientificNotationAmountIsIgnoredLikeAnAmountOfNothing(): void
    {
        InstantTestPaymentModule::$appliedAmount = '1.0E-5';
        $cart = $this->factory->cart($this->factory->customer($this->factory->customerTitle()));

        $composition = $this->getService(OrderPaymentComposer::class)->composeForCart($cart, (int) $this->providerModule->getId());

        self::assertSame([], $composition->parts);
    }

    public function testAnAmountThatIsNotANumberIsIgnored(): void
    {
        InstantTestPaymentModule::$appliedAmount = 'plenty';
        $cart = $this->factory->cart($this->factory->customer($this->factory->customerTitle()));

        $composition = $this->getService(OrderPaymentComposer::class)->composeForCart($cart, (int) $this->providerModule->getId());

        self::assertSame([], $composition->parts);
    }

    public function testAnActiveModuleWhoseCodeIsGoneDoesNotStopTheOthers(): void
    {
        (new Module())
            ->setCode('OrderPaymentComposerGoneModule')
            ->setFullNamespace('Thelia\\Tests\\Missing\\OrderPaymentComposerGoneModule')
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->save($this->getPropelConnection());
        InstantTestPaymentModule::$appliedAmount = '0';
        $cart = $this->factory->cart($this->factory->customer($this->factory->customerTitle()));

        $composition = $this->getService(OrderPaymentComposer::class)->composeForCart($cart, (int) $this->providerModule->getId());

        self::assertSame([], $composition->parts);
    }

    /**
     * A module that cannot tell the amount it applied counts as having applied nothing: it
     * does not stop the other means, nor every cart of the shop.
     */
    public function testAModuleThatFailsToTellItsAmountCountsAsNothing(): void
    {
        $this->installPaymentModule(SecondInstantTestPaymentModule::class, static::getContainer(), $this->getPropelConnection());
        InstantTestPaymentModule::$failsWhenAskedForTheAmount = true;
        SecondInstantTestPaymentModule::$secondAppliedAmount = '0';
        $cart = $this->factory->cart($this->factory->customer($this->factory->customerTitle()));

        $composition = $this->getService(OrderPaymentComposer::class)->composeForCart($cart, (int) $this->providerModule->getId());

        self::assertSame([], $composition->parts);
        self::assertSame([(int) $cart->getId()], InstantTestPaymentModule::$askedAboutCartIds, 'The module was asked.');
    }

    /**
     * The place a cart is taxed at is BaseModule's, whichever of the two asks.
     */
    public function testTheTaxationPlaceIsTheDeliveryAddressCountryAndState(): void
    {
        $country = $this->factory->country();
        $state = $this->stateOf($country);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $cart = $this->factory->cart($customer);
        $deliveryAddress = $this->factory->cartAddress(null, $country);
        $deliveryAddress->setStateId($state->getId())->save($this->getPropelConnection());
        $cart->setAddressDeliveryId($deliveryAddress->getId())->save($this->getPropelConnection());

        [$taxedCountry, $taxedState] = BaseModule::resolveTaxationPlace($cart, static::getContainer());

        self::assertSame($country->getId(), $taxedCountry->getId());
        self::assertSame($state->getId(), $taxedState?->getId());
    }

    public function testTheTaxationPlaceFallsBackOnTheDefaultAddressWithItsState(): void
    {
        $country = $this->factory->country();
        $state = $this->stateOf($country);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $address = $this->factory->address($customer, $country);
        $address->setStateId($state->getId())->setIsDefault(1)->save($this->getPropelConnection());
        $cart = $this->factory->cart($customer);

        [$taxedCountry, $taxedState] = BaseModule::resolveTaxationPlace($this->reloadedCart($cart), static::getContainer());

        self::assertSame($country->getId(), $taxedCountry->getId());
        self::assertSame($state->getId(), $taxedState?->getId(), 'The state of the default address is kept.');
    }

    /**
     * A manual order of the back office is composed like any other: when no instant module
     * applied anything, it writes no part and the order is the one it has always been.
     */
    public function testAManualOrderWithNoInstantShareWritesNoPartAndRaisesNothing(): void
    {
        InstantTestPaymentModule::$appliedAmount = '0';
        $cart = $this->cartPaidWith($this->providerModule);

        $sessionOrder = (new Order())
            ->setDeliveryOrderAddressId($cart->getAddressDeliveryId())
            ->setInvoiceOrderAddressId($cart->getAddressInvoiceId())
            ->setPaymentModuleId($this->providerModule->getId())
            ->setDeliveryModuleId($cart->getDeliveryModuleId())
            ->setPostage('0')
            ->setPostageTax('0');

        $event = new OrderManualEvent($sessionOrder, $this->factory->currency(), $this->factory->lang(), $cart, $cart->getCustomer());

        $this->getService(EventDispatcherInterface::class)->dispatch($event, TheliaEvents::ORDER_CREATE_MANUAL);

        $placed = $event->getPlacedOrder();
        self::assertNotNull($placed->getId());
        self::assertSame(0, OrderPaymentPartQuery::create()->filterByOrderId($placed->getId())->count($this->getPropelConnection()));
        self::assertSame($this->providerModule->getId(), $placed->getPaymentModuleId());
        self::assertNotSame([], InstantTestPaymentModule::$askedAboutCartIds, 'The composer did ask the instant module.');
    }

    private function stateOf(\Thelia\Model\Country $country): State
    {
        $state = (new State())->setCountryId($country->getId())->setVisible(1)->setIsocode('ZZ')->setTitle('Composer test state');
        $state->save($this->getPropelConnection());

        return $state;
    }

    private function reloadedCart(Cart $cart): Cart
    {
        \Thelia\Model\Map\CartTableMap::clearInstancePool();

        return \Thelia\Model\CartQuery::create()->findPk($cart->getId());
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->getService(EventDispatcherInterface::class)->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function cartPaidWith(Module $paymentModule): Cart
    {
        $country = $this->factory->country();
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');

        $area = (new Area())->setName('Composer test area '.uniqid());
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
}
