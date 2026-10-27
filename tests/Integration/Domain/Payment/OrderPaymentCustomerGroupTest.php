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
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Customer\Service\CustomerGroupPaymentPolicy;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Domain\Payment\OrderPaymentComposer;
use Thelia\Domain\Payment\Service\OrderPaymentPartSettlement;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupPaymentModule;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderPaymentPartQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Tests\Support\Payment\BalanceChargingTestPaymentModule;
use Thelia\Tests\Support\Payment\InstantTestPaymentModule;
use Thelia\Tests\Support\Payment\UsesInstantPaymentModules;

/**
 * A payment part is a payment by the module that holds it: a module off the list of the
 * customer group of the cart takes no part in the composition of the payment, is not written
 * on the order, and is not settled, whatever the module says it holds for the cart. A group
 * with no list pays with every module. The cart is worth 100.00 and the instant module
 * applies 30.00 of it.
 */
final class OrderPaymentCustomerGroupTest extends IntegrationTestCase
{
    use ForgetsPooledModels;
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
        $this->forgetTheGroups();

        InstantTestPaymentModule::$appliedAmount = '30';
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];
        $this->uninstallThePaymentModules(static::getContainer());
        $this->getService(ConsentProvider::class)->forgetCache();
        $this->forgetTheGroups();
        self::forgetPooledModels();
        Propel::disableInstancePooling();

        parent::tearDown();
    }

    public function testAPartOfAModuleOffTheListOfTheGroupIsNotComposedAndTheModuleIsNotAsked(): void
    {
        $pro = $this->factory->customerGroup('pro');
        $this->limitTheGroupTo($pro, $this->providerModule);
        $cart = $this->cartOf($this->customerOf($pro));

        $composition = $this->getService(OrderPaymentComposer::class)->composeForCart($cart, (int) $this->providerModule->getId());

        self::assertSame([], $composition->parts);
        self::assertSame([], InstantTestPaymentModule::$askedAboutCartIds, 'The module off the list is not even asked what it applied.');
    }

    public function testAPartOfAModuleOnTheListOfTheGroupIsComposed(): void
    {
        $pro = $this->factory->customerGroup('pro');
        $this->limitTheGroupTo($pro, $this->instantModule, $this->providerModule);
        $cart = $this->cartOf($this->customerOf($pro));

        $composition = $this->getService(OrderPaymentComposer::class)->composeForCart($cart, (int) $this->providerModule->getId());

        self::assertSame(
            [[(int) $this->instantModule->getId(), '30.000000'], [(int) $this->providerModule->getId(), '70.000000']],
            array_map(static fn ($part): array => [$part->moduleId, $part->amount], $composition->parts),
        );
    }

    public function testAGroupWithNoListPaysWithEveryModule(): void
    {
        $pro = $this->factory->customerGroup('pro');
        $retail = $this->defaultGroup();
        $composer = $this->getService(OrderPaymentComposer::class);

        foreach ([$pro, $retail] as $group) {
            $composition = $composer->composeForCart($this->cartOf($this->customerOf($group)), (int) $this->providerModule->getId());

            self::assertCount(2, $composition->parts, 'An empty list means every module.');
        }
    }

    public function testTheListOfOneGroupClosesNothingToTheDefaultGroupNorToAVisitor(): void
    {
        $pro = $this->factory->customerGroup('pro');
        $this->limitTheGroupTo($pro, $this->providerModule);
        $composer = $this->getService(OrderPaymentComposer::class);

        $proComposition = $composer->composeForCart($this->cartOf($this->customerOf($pro)), (int) $this->providerModule->getId());
        $retailComposition = $composer->composeForCart($this->cartOf($this->customerOf($this->defaultGroup())), (int) $this->providerModule->getId());
        $visitorComposition = $composer->composeForCart($this->cartOf(null), (int) $this->providerModule->getId());

        self::assertSame([], $proComposition->parts);
        self::assertCount(2, $retailComposition->parts);
        self::assertCount(2, $visitorComposition->parts, 'A cart with no customer is a visitor\'s, who belongs to the default group.');
    }

    public function testTheCartOfAVisitorFollowsTheListOfTheDefaultGroup(): void
    {
        $this->limitTheGroupTo($this->defaultGroup(), $this->providerModule);

        $composition = $this->getService(OrderPaymentComposer::class)->composeForCart($this->cartOf(null), (int) $this->providerModule->getId());

        self::assertSame([], $composition->parts);
    }

    public function testAPartOfAModuleOffTheListOfTheGroupIsNotWrittenOnTheOrder(): void
    {
        $pro = $this->factory->customerGroup('pro');
        $this->limitTheGroupTo($pro, $this->providerModule);
        $cart = $this->placeableCartOf($this->customerOf($pro));

        $order = $this->pay($cart);

        self::assertSame([], $this->partsOf($order), 'The order is paid by the provider alone.');
        self::assertSame([], InstantTestPaymentModule::$settledPartIds);
        self::assertSame((int) $this->providerModule->getId(), $order->getPaymentModuleId());
    }

    public function testAPartOfAModuleOnTheListOfTheGroupIsWrittenOnTheOrder(): void
    {
        $pro = $this->factory->customerGroup('pro');
        $this->limitTheGroupTo($pro, $this->instantModule, $this->providerModule);
        $cart = $this->placeableCartOf($this->customerOf($pro));

        $order = $this->pay($cart);

        self::assertSame(
            [(int) $this->instantModule->getId(), (int) $this->providerModule->getId()],
            array_map(static fn (OrderPaymentPart $part): ?int => $part->getPaymentModuleId(), $this->partsOf($order)),
        );
        self::assertCount(1, InstantTestPaymentModule::$settledPartIds);
    }

    /**
     * A part written while the group had no list, a list without the module since: the
     * settlement refuses, whichever way MODULE_PAY is reached, and the module is never
     * asked to spend anything.
     */
    public function testAPartWrittenBeforeTheGroupWasLimitedIsNotSettled(): void
    {
        $pro = $this->factory->customerGroup('pro');
        $customer = $this->customerOf($pro);
        $order = $this->placeWithoutPaying($this->placeableCartOf($customer));

        $this->limitTheGroupTo($pro, $this->providerModule);

        try {
            $this->dispatcher()->dispatch(new OrderPaymentEvent($this->reread($order)), TheliaEvents::MODULE_PAY);
            self::fail('A part of a module off the list of the group must not be settled.');
        } catch (InvalidPaymentException) {
        }

        self::assertSame([], InstantTestPaymentModule::$calls, 'No module was asked to settle or to pay.');
        self::assertSame(
            [OrderPaymentPartState::PENDING->value, OrderPaymentPartState::PENDING->value],
            array_map(static fn (OrderPaymentPart $part): ?string => $part->getState(), $this->partsOf($order)),
        );
    }

    public function testAPartOfAModuleStillOnTheListOfTheGroupIsSettled(): void
    {
        $pro = $this->factory->customerGroup('pro');
        $customer = $this->customerOf($pro);
        $order = $this->placeWithoutPaying($this->placeableCartOf($customer));

        $this->limitTheGroupTo($pro, $this->instantModule, $this->providerModule);
        $this->getService(OrderPaymentPartSettlement::class)->settleInstantPartsOf($this->reread($order));

        self::assertSame(['settle'], InstantTestPaymentModule::$calls);
    }

    /**
     * The unpaid order is presented again once the group was limited to the provider: the
     * composition no longer holds the part, so it is not the same payment, and the order that
     * replaces the first one carries no part of that module.
     */
    public function testAnUnpaidOrderIsNotPresentedAgainWithAPartOfAModuleOffTheList(): void
    {
        $pro = $this->factory->customerGroup('pro');
        $customer = $this->customerOf($pro);
        $cart = $this->placeableCartOf($customer);
        $first = $this->placeWithoutPaying($cart);

        $this->limitTheGroupTo($pro, $this->providerModule);
        $second = $this->pay($cart);

        self::assertNotSame($first->getId(), $second->getId());
        self::assertSame([], $this->partsOf($second));
        self::assertSame([], InstantTestPaymentModule::$settledPartIds);
    }

    private function defaultGroup(): CustomerGroup
    {
        return CustomerGroupQuery::findDefault();
    }

    private function limitTheGroupTo(CustomerGroup $group, Module ...$modules): void
    {
        foreach ($modules as $module) {
            (new CustomerGroupPaymentModule())
                ->setCustomerGroupId($group->getId())
                ->setModuleId($module->getId())
                ->save($this->getPropelConnection());
        }

        $group->setRestrictsPaymentModules(1)->save($this->getPropelConnection());

        $this->forgetTheGroups();
    }

    private function forgetTheGroups(): void
    {
        $this->getService(CustomerGroupPaymentPolicy::class)->reset();
        $this->getService(CustomerGroupResolver::class)->reset();
    }

    private function customerOf(CustomerGroup $group): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
    }

    private function cartOf(?Customer $customer): Cart
    {
        return $this->placeableCartOf($customer);
    }

    private function placeableCartOf(?Customer $customer): Cart
    {
        $country = $this->factory->country();
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');

        $area = (new Area())->setName('Payment group test area '.uniqid());
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

        if ($customer instanceof Customer) {
            $this->factory->address($customer, $country);
        }

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
            ->setPaymentModuleId($this->providerModule->getId())
            ->setPostage('0')
            ->setPostageTax('0')
            ->save($this->getPropelConnection());

        return $cart;
    }

    private function pay(Cart $cart): Order
    {
        $cart = CartQuery::create()->findPk($cart->getId(), $this->getPropelConnection())
            ?? throw new \RuntimeException('The cart went away.');

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

    /**
     * The order as the placement leaves it, with its parts pending: MODULE_PAY is stopped
     * before anything listens to it.
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

        self::assertCount(2, $this->partsOf($order));

        return $order;
    }

    /**
     * @return list<OrderPaymentPart>
     */
    private function partsOf(Order $order): array
    {
        return array_values(iterator_to_array(
            OrderPaymentPartQuery::create()
                ->filterByOrderId($order->getId())
                ->orderByPosition()
                ->find($this->getPropelConnection()),
        ));
    }

    private function reread(Order $order): Order
    {
        return OrderQuery::create()->findPk($order->getId(), $this->getPropelConnection())
            ?? throw new \RuntimeException('The order went away.');
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
