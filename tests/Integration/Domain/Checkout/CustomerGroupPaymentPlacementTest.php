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

namespace Thelia\Tests\Integration\Domain\Checkout;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Customer\Service\CustomerGroupPaymentPolicy;
use Thelia\Model\Cart;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupPaymentModule;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Order\RetryingPaymentModule;

/**
 * Whatever path pays an order, a payment module off the list of the group of the customer
 * of the cart is refused: a direct payment, an event raised by a module, and an unpaid order
 * presented again once the group was given a list without the module. A group with no list,
 * the default one of the test, pays with any module.
 */
final class CustomerGroupPaymentPlacementTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private Module $module;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    private int $modulePayDispatches = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->module = $this->registerTheRetryingPaymentModule();
        $this->getService(CustomerGroupPaymentPolicy::class)->reset();

        // The module would answer with a page of its own: only the call is counted.
        $this->listen(TheliaEvents::MODULE_PAY, function (OrderPaymentEvent $event): void {
            ++$this->modulePayDispatches;
            $event->stopPropagation();
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];
        $this->getService(CustomerGroupPaymentPolicy::class)->reset();

        parent::tearDown();
    }

    public function testADirectPaymentWithAModuleOffTheListOfTheGroupIsRefusedAndWritesNoOrder(): void
    {
        $cart = $this->cartReadyToPay($this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $this->groupListingAnotherModule()]));

        try {
            $this->pay($cart);
            self::fail('The payment with a module off the list of the group must be refused.');
        } catch (InvalidPaymentException) {
            self::assertSame([], $this->ordersOf($cart), 'Nothing is written.');
            self::assertSame(0, $this->modulePayDispatches, 'The module is never asked to pay.');
        }
    }

    public function testAnEventRaisedByAModuleWithAModuleOffTheListIsRefusedToo(): void
    {
        $cart = $this->cartReadyToPay($this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $this->groupListingAnotherModule()]));

        $order = (new Order())
            ->setCartId($cart->getId())
            ->setCustomerId($cart->getCustomerId())
            ->setPaymentModuleId($this->module->getId());

        $this->expectException(InvalidPaymentException::class);
        $this->dispatcher()->dispatch(new OrderEvent($order), TheliaEvents::ORDER_PAY);
    }

    public function testAModuleOnTheListOfTheGroupIsPaidAsBefore(): void
    {
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $this->module->getId()]);
        $cart = $this->cartReadyToPay($this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]));

        $order = $this->pay($cart);

        self::assertCount(1, $this->ordersOf($cart));
        self::assertSame(1, $this->modulePayDispatches);
        self::assertNotNull($order->getId());
    }

    public function testTheDefaultGroupWithNoListPaysWhateverTheOthersList(): void
    {
        $this->groupListingAnotherModule();
        $cart = $this->cartReadyToPay($this->factory->customer($this->factory->customerTitle()));

        $this->pay($cart);

        self::assertCount(1, $this->ordersOf($cart));
    }

    public function testAnUnpaidOrderIsNotPresentedAgainToAModuleTheGroupNoLongerLists(): void
    {
        $pro = $this->factory->customerGroup('pro');
        $cart = $this->cartReadyToPay($this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]));

        $this->pay($cart);
        self::assertSame(1, $this->modulePayDispatches);

        (new CustomerGroupPaymentModule())
            ->setCustomerGroupId((int) $pro->getId())
            ->setModuleId((int) $this->anotherPaymentModule()->getId())
            ->save($this->getPropelConnection());
        $pro->setRestrictsPaymentModules(1)->save($this->getPropelConnection());
        $this->getService(CustomerGroupPaymentPolicy::class)->reset();

        try {
            $this->pay($cart);
            self::fail('The unpaid order must not be presented again to a module off the list of the group.');
        } catch (InvalidPaymentException) {
            self::assertSame(1, $this->modulePayDispatches, 'The module is not asked a second time.');
        }
    }

    public function testAGroupWhoseOnlyListedModuleWasDeletedCanPayWithNoModule(): void
    {
        $listed = $this->factory->paymentModule();
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $listed->getId()]);
        $cart = $this->cartReadyToPay($this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $pro]));

        ModuleQuery::create()->filterById($listed->getId())->delete($this->getPropelConnection());
        $this->getService(CustomerGroupPaymentPolicy::class)->reset();

        try {
            $this->pay($cart);
            self::fail('A group restricted to a module that is gone must be refused, not opened to every module.');
        } catch (InvalidPaymentException) {
            self::assertSame([], $this->ordersOf($cart), 'Nothing is written.');
            self::assertSame(0, $this->modulePayDispatches, 'The module is never asked to pay.');
        }
    }

    /**
     * A group whose list holds a payment module other than the one the carts of the test are
     * paid with.
     */
    private function groupListingAnotherModule(): CustomerGroup
    {
        $pro = $this->factory->customerGroup('pro', paymentModuleIds: [(int) $this->anotherPaymentModule()->getId()]);
        $this->getService(CustomerGroupPaymentPolicy::class)->reset();

        return $pro;
    }

    private function anotherPaymentModule(): Module
    {
        return ModuleQuery::create()
            ->filterByType(BaseModule::PAYMENT_MODULE_TYPE)
            ->filterById($this->module->getId(), Criteria::NOT_EQUAL)
            ->findOne($this->getPropelConnection())
            ?? throw new \RuntimeException('A second payment module is needed — run bin/test-prepare.');
    }

    private function pay(Cart $cart): Order
    {
        return $this->getService(CheckoutPaymentService::class)->payAndReturnOutcome(new OrderPaymentRequest(
            $cart,
            (int) $cart->getAddressDeliveryId(),
            (int) $cart->getAddressInvoiceId(),
            (int) $cart->getDeliveryModuleId(),
            (int) $cart->getPaymentModuleId(),
        ))->placedOrder;
    }

    /**
     * @return list<Order>
     */
    private function ordersOf(Cart $cart): array
    {
        return OrderQuery::create()
            ->filterByCartId($cart->getId())
            ->orderById()
            ->find($this->getPropelConnection())
            ->getData();
    }

    private function cartReadyToPay(Customer $customer): Cart
    {
        $country = $this->factory->country();
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');

        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );

        $cart = $this->factory->cart($customer);
        $this->factory->cartItem($cart, $product);

        $cart
            ->setAddressDeliveryId($this->factory->cartAddress(null, $country)->getId())
            ->setAddressInvoiceId($this->factory->cartAddress(null, $country)->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($this->module->getId())
            ->save($this->getPropelConnection());

        return $cart;
    }

    private function registerTheRetryingPaymentModule(): Module
    {
        $existing = ModuleQuery::create()->findOneByCode(RetryingPaymentModule::getModuleCode());

        if (null !== $existing) {
            return $existing;
        }

        $module = (new Module())
            ->setCode(RetryingPaymentModule::getModuleCode())
            ->setFullNamespace(RetryingPaymentModule::class)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED);
        $module->save($this->getPropelConnection());

        return $module;
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->dispatcher()->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
