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
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Payment\InstantTestPaymentModule;
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;
use Thelia\Tests\Support\Payment\UsesInstantPaymentModules;
use Thelia\Tests\Support\Payment\UsesStoredMethodsPaymentModule;

/**
 * The guard asked at placement must refuse exactly the placements MODULE_PAY will pay with
 * the stored method. When the payment module stopped offering stored methods since the
 * buyer chose one, StoredPaymentMethodPayment::methodChosenFor() answers null and the
 * module is asked to pay with a new method: no slot of the allowance is spent, so the
 * placement must not be refused by it.
 */
final class StoredMethodAllowanceWhenTheModuleStoppedOfferingStoredMethodsTest extends IntegrationTestCase
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

    public function testAModuleThatStoppedOfferingStoredMethodsDoesNotGetThePlacementRefusedByTheAllowance(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->choose($cart, $method);
        $this->spendTheWholeHourlyAllowanceOf($customer, $method);

        // The merchant turned stored methods off on the module after the buyer chose one.
        StoredMethodsPaymentModule::$offersStoredMethods = false;

        $refused = false;

        try {
            $this->place($cart, $customer);
        } catch (StoredMethodPaymentRateLimitedException) {
            $refused = true;
        }

        self::assertFalse($refused, 'MODULE_PAY pays with a new method when the module no longer offers stored methods (StoredPaymentMethodPayment::methodChosenFor), it spends no slot: the placement must not be refused by the allowance.');
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
