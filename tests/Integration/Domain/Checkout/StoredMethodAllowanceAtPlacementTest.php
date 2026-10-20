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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\Service\CheckoutPlacementService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Payment\Exception\StoredMethodPaymentRateLimitedException;
use Thelia\Domain\Payment\StoredPaymentMethodPayment;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Payment\InstantTestPaymentModule;
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;
use Thelia\Tests\Support\Payment\UsesInstantPaymentModules;
use Thelia\Tests\Support\Payment\UsesStoredMethodsPaymentModule;

/**
 * The hourly allowance of payments with a stored method is there to slow down a stolen
 * reference. An order the instant means (a gift card) pay in full is never charged on the
 * stored method: the module that keeps it is not asked to pay at all. Refusing that
 * placement because the customer is over the allowance refuses a payment that spends no
 * stored method.
 */
final class StoredMethodAllowanceAtPlacementTest extends IntegrationTestCase
{
    use UsesInstantPaymentModules;
    use UsesStoredMethodsPaymentModule;

    private const PAYMENTS_ALLOWED_PER_HOUR = 10;

    private FixtureFactory $factory;

    private Module $module;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->module = $this->installTheStoredMethodsPaymentModule(static::getContainer(), $this->getPropelConnection());
        $this->installPaymentModule(InstantTestPaymentModule::class, static::getContainer(), $this->getPropelConnection());
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

    public function testAnOrderTheInstantMeansPayInFullIsNotRefusedByTheStoredMethodAllowance(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->choose($cart, $method);
        $this->spendTheWholeHourlyAllowanceOf($customer, $method);

        // The gift card covers the whole cart: nothing is left for the stored method to pay.
        InstantTestPaymentModule::$appliedAmount = '100000';

        try {
            $this->place($cart, $customer);
        } catch (StoredMethodPaymentRateLimitedException) {
            self::fail('An order the instant means pay in full spends no stored method payment, and must not be refused by its allowance.');
        }

        $order = OrderQuery::create()->filterByCartId($cart->getId())->findOne($this->getPropelConnection());

        self::assertInstanceOf(Order::class, $order, 'The order paid by the gift card alone is placed.');
        self::assertSame([], StoredMethodsPaymentModule::$calls, 'The stored method module is never asked to pay.');
    }

    /**
     * Same allowance, same root: a method that expired since it was chosen is not the one
     * the order is paid with (MODULE_PAY falls back to a new one and spends no slot), so it
     * is no reason to refuse the placement either.
     */
    public function testAMethodExpiredSinceTheChoiceIsNotRefusedByTheStoredMethodAllowance(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->choose($cart, $method);
        $this->spendTheWholeHourlyAllowanceOf($customer, $method);
        $method->setExpiresAt((new \DateTimeImmutable('yesterday'))->format('Y-m-d'))->save($this->getPropelConnection());

        try {
            $this->place($cart, $customer);
        } catch (StoredMethodPaymentRateLimitedException) {
            self::fail('The expired method chosen on the cart is not used to pay, and must not get the placement refused by its allowance.');
        }

        self::assertSame(['pay'], StoredMethodsPaymentModule::$calls, 'The module is asked to pay with a new method.');
    }

    private function place(Cart $cart, Customer $customer): void
    {
        $this->getService(CheckoutPlacementService::class)->place(new CheckoutPlacementRequest(
            $this->reread($cart),
            $customer,
            $this->factory->currency(),
            $this->factory->lang(),
        ));
    }

    private function choose(Cart $cart, CustomerPaymentMethod $method): void
    {
        $this->getService(CheckoutFacade::class)->selectPaymentModule(new CheckoutDTO(
            $this->reread($cart),
            paymentModuleId: $this->module->getId(),
            customerPaymentMethodId: $method->getId(),
        ));
    }

    private function spendTheWholeHourlyAllowanceOf(Customer $customer, CustomerPaymentMethod $method): void
    {
        $payment = $this->getService(StoredPaymentMethodPayment::class);
        $order = (new Order())->setCustomerId($customer->getId());

        for ($payments = 0; $payments < self::PAYMENTS_ALLOWED_PER_HOUR; ++$payments) {
            $payment->pay(new StoredMethodsPaymentModule(), $order, $method);
        }

        StoredMethodsPaymentModule::reset();
    }

    private function reread(Cart $cart): Cart
    {
        return CartQuery::create()->findPk($cart->getId(), $this->getPropelConnection())
            ?? throw new \RuntimeException('The cart went away.');
    }

    /**
     * @return array{0: Cart, 1: Customer}
     */
    private function cartReadyToPay(): array
    {
        $country = $this->factory->country();
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');

        $this->serveCountryWith($deliveryModule, $country);
        $this->answerDeliveryQuoteWithAFreePostage();
        $this->answerPaymentValidityWithYes();

        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->factory->address($customer, $country);
        static::getContainer()->get('limiter.stored_method_payment')->create('customer:'.$customer->getId())->reset();

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
            ->setPostage('0')
            ->setPostageTax('0')
            ->save($this->getPropelConnection());

        return [$cart, $customer];
    }

    private function serveCountryWith(Module $module, Country $country): void
    {
        $area = (new Area())->setName('Stored method allowance test area '.uniqid());
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($module->getId())->save($this->getPropelConnection());
    }

    private function answerDeliveryQuoteWithAFreePostage(): void
    {
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true);
            $event->setPostage(new OrderPostage(0.0, 0.0, 'VAT'));
            $event->stopPropagation();
        });
    }

    private function answerPaymentValidityWithYes(): void
    {
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        });
    }

    private function turnMandatoryConsentsOff(): void
    {
        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }

    private function listen(string $eventName, callable $listener, int $priority = 512): void
    {
        $this->dispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
