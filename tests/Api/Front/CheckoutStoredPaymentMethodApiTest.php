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

namespace Thelia\Tests\Api\Front;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Payment\StoredPaymentMethodPayment;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\Map\ConsentTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;
use Thelia\Tests\Support\Payment\UsesStoredMethodsPaymentModule;

/**
 * Choosing one of the buyer's stored payment methods with the payment module, over
 * `POST /front/account/checkout/{cartId}/payment_module`.
 */
final class CheckoutStoredPaymentMethodApiTest extends ApiTestCase
{
    use UsesStoredMethodsPaymentModule;

    private FixtureFactory $factory;

    private Module $module;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    /** @var list<int> customers whose hourly allowance a test spent */
    private array $exhaustedCustomerIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->module = $this->installTheStoredMethodsPaymentModule(static::getContainer(), $this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->getService(EventDispatcherInterface::class)->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];
        // The limiter counts outside the transaction of the test: the slots a test spent
        // would still be spent for the next one that reuses the customer id.
        foreach ($this->exhaustedCustomerIds as $customerId) {
            static::getContainer()->get('limiter.stored_method_payment')->create('customer:'.$customerId)->reset();
        }
        $this->exhaustedCustomerIds = [];
        $this->getService(ConsentProvider::class)->forgetCache();
        $this->uninstallTheStoredMethodsPaymentModule(static::getContainer());

        parent::tearDown();
    }

    public function testTheBuyerChoosesOneOfTheirStoredMethods(): void
    {
        [$cart, $customer] = $this->cartOfAnAccount();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');

        $response = $this->choose($cart, $this->authenticateAsCustomer($customer), $this->module, $method->getId());

        self::assertJsonResponseSuccessful($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($method->getId(), $this->reread($cart)->getCustomerPaymentMethodId());
        self::assertStringNotContainsString((string) $method->getToken(), (string) $response->getContent());
    }

    /**
     * Another customer's method is refused with the very answer a method that does not
     * exist gets: the endpoint is no way of finding out which ids are taken.
     */
    public function testAMethodOfAnotherCustomerIsRefusedLikeOneThatDoesNotExist(): void
    {
        [$cart, $customer] = $this->cartOfAnAccount();
        [, $somebodyElse] = $this->cartOfAnAccount();
        $methodOfSomebodyElse = $this->storedMethodRow($somebodyElse, $this->module, 'Visa ending 4242', '2030-12-31');

        $token = $this->authenticateAsCustomer($customer);

        $foreign = $this->choose($cart, $token, $this->module, $methodOfSomebodyElse->getId());
        $missing = $this->choose($cart, $token, $this->module, $methodOfSomebodyElse->getId() + 100000);

        self::assertSame(422, $foreign->getStatusCode());
        self::assertSame(422, $missing->getStatusCode());
        self::assertSame($this->messageOf($missing), $this->messageOf($foreign));
        self::assertStringNotContainsString((string) $methodOfSomebodyElse->getToken(), (string) $foreign->getContent());
        self::assertNull($this->reread($cart)->getCustomerPaymentMethodId());
    }

    public function testAMethodOfAnotherModuleIsRefused(): void
    {
        [$cart, $customer] = $this->cartOfAnAccount();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');

        $response = $this->choose($cart, $this->authenticateAsCustomer($customer), $this->chequeModule(), $method->getId());

        self::assertSame(422, $response->getStatusCode());
        self::assertStringNotContainsString((string) $method->getToken(), (string) $response->getContent());
        self::assertNull($this->reread($cart)->getCustomerPaymentMethodId());
    }

    /**
     * The same module posted again, without the method, is the choice of a new one: it
     * is not skipped as a choice the cart already holds.
     */
    public function testPostingTheModuleAgainWithoutTheMethodChoosesANewOne(): void
    {
        [$cart, $customer] = $this->cartOfAnAccount();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $token = $this->authenticateAsCustomer($customer);

        $this->jsonRequest('POST', $this->url($cart), ['paymentModuleId' => $this->module->getId(), 'customerPaymentMethodId' => $method->getId()], token: $token);
        $response = $this->jsonRequest('POST', $this->url($cart), ['paymentModuleId' => $this->module->getId()], token: $token);

        self::assertJsonResponseSuccessful($response);
        self::assertSame($this->module->getId(), $this->reread($cart)->getPaymentModuleId());
        self::assertNull($this->reread($cart)->getCustomerPaymentMethodId());
    }

    /**
     * The hourly allowance of payments with a stored method is a limit on paying, not
     * something the cart is missing: the verdict on the checkout does not see it, and
     * the placement is what answers 429, before an order is written.
     */
    public function testACustomerOverTheAllowanceIsToldTheCheckoutIsReadyAndRefusedThePlacement(): void
    {
        [$cart, $customer, $token, $method] = $this->checkoutReadyToPlaceWithAStoredMethod();
        $this->spendTheWholeHourlyAllowanceOf($customer, $method);

        $validation = $this->jsonRequest('GET', $this->checkoutUrl($cart, 'validation'), token: $token);

        self::assertSame(200, $validation->getStatusCode(), (string) $validation->getContent());
        self::assertSame(
            ['ready' => true, 'violations' => []],
            json_decode((string) $validation->getContent(), true, flags: \JSON_THROW_ON_ERROR),
        );

        $placement = $this->jsonRequest('POST', $this->checkoutUrl($cart, 'place'), token: $token);

        self::assertSame(429, $placement->getStatusCode(), (string) $placement->getContent());
        self::assertSame([], StoredMethodsPaymentModule::$calls, 'Neither the stored method nor a new one was asked to pay.');
        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()), 'No order was written.');
    }

    private function choose(Cart $cart, string $token, Module $module, int $customerPaymentMethodId): Response
    {
        return $this->jsonRequest(
            'POST',
            $this->url($cart),
            ['paymentModuleId' => $module->getId(), 'customerPaymentMethodId' => $customerPaymentMethodId],
            token: $token,
        );
    }

    private function messageOf(Response $response): ?string
    {
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $body['hydra:description'] ?? $body['detail'] ?? $body['description'] ?? null;
    }

    private function url(Cart $cart): string
    {
        return '/api/front/account/checkout/'.$cart->getId().'/payment_module';
    }

    /**
     * @return array{0: Cart, 1: Customer}
     */
    private function cartOfAnAccount(): array
    {
        $customer = $this->factory->customer($this->factory->customerTitle(), ['password' => 'password']);
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );
        $cart = $this->factory->cart($customer);
        $this->factory->cartItem($cart, $product);

        return [$cart, $customer];
    }

    /**
     * A cart of an account with every choice posted over the API, the stored method
     * chosen with the payment module.
     *
     * @return array{0: Cart, 1: Customer, 2: string, 3: CustomerPaymentMethod}
     */
    private function checkoutReadyToPlaceWithAStoredMethod(): array
    {
        $this->turnMandatoryConsentsOff();

        $country = $this->factory->country();
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');

        $area = (new Area())->setName('Stored method placement test area '.uniqid());
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

        [$cart, $customer] = $this->cartOfAnAccount();
        $address = $this->factory->address($customer, $country);
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        // The limiter keeps its count outside the transaction of the test, and a test
        // database built again hands out the same customer ids.
        static::getContainer()->get('limiter.stored_method_payment')->create('customer:'.$customer->getId())->reset();

        $token = $this->authenticateAsCustomer($customer);
        foreach ([
            'delivery_address' => ['addressId' => $address->getId()],
            'invoice_address' => ['addressId' => $address->getId()],
            'delivery_module' => ['deliveryModuleId' => $deliveryModule->getId()],
            'payment_module' => ['paymentModuleId' => $this->module->getId(), 'customerPaymentMethodId' => $method->getId()],
        ] as $step => $choice) {
            self::assertJsonResponseSuccessful($this->jsonRequest('POST', $this->checkoutUrl($cart, $step), $choice, token: $token));
        }

        return [$cart, $customer, $token, $method];
    }

    /**
     * Ten payments already went through for this customer within the hour.
     */
    private function spendTheWholeHourlyAllowanceOf(Customer $customer, CustomerPaymentMethod $method): void
    {
        $payment = $this->getService(StoredPaymentMethodPayment::class);
        $order = (new Order())->setCustomerId($customer->getId());
        // Recorded before the first slot is spent, so that a payment failing halfway
        // still leaves the tearDown the customer to reset.
        $this->exhaustedCustomerIds[] = (int) $customer->getId();

        for ($payments = 0; $payments < 10; ++$payments) {
            $payment->pay(new StoredMethodsPaymentModule(), $order, $method);
        }

        StoredMethodsPaymentModule::reset();
    }

    private function turnMandatoryConsentsOff(): void
    {
        // Propel pools its objects for the whole process, and the transaction of the
        // previous test took its writes back without telling the pool.
        ConsentTableMap::clearInstancePool();

        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->getService(EventDispatcherInterface::class)->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function checkoutUrl(Cart $cart, string $step): string
    {
        return '/api/front/account/checkout/'.$cart->getId().'/'.$step;
    }

    private function reread(Cart $cart): Cart
    {
        return CartQuery::create()->findPk($cart->getId(), $this->getPropelConnection())
            ?? throw new \RuntimeException('The cart went away.');
    }

    private function chequeModule(): Module
    {
        return ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No Cheque module installed — run bin/test-prepare.');
    }
}
