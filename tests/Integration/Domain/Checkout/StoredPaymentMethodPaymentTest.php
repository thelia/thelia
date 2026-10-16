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

use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\CheckoutPlacementService;
use Thelia\Domain\Checkout\Service\CheckoutProgressionService;
use Thelia\Domain\Checkout\Service\CheckoutValidationService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Domain\Payment\Exception\StoredMethodPaymentRateLimitedException;
use Thelia\Domain\Payment\StoredPaymentMethodPayment;
use Thelia\Domain\Payment\StoredPaymentMethodSelection;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\CheckoutStep;
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
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;
use Thelia\Tests\Support\Payment\UsesStoredMethodsPaymentModule;

/**
 * Paying an order with the stored method chosen on its cart.
 *
 * MODULE_PAY is not stubbed here, unlike in the other placement tests: Action\Module::pay()
 * is what decides between the stored method and a new one, so it runs, and the payment
 * module it calls is the test module, installed in the container under its service id.
 */
final class StoredPaymentMethodPaymentTest extends IntegrationTestCase
{
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
        $this->turnMandatoryConsentsOff();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];
        $this->uninstallTheStoredMethodsPaymentModule(static::getContainer());
        $this->getService(ConsentProvider::class)->forgetCache();

        parent::tearDown();
    }

    public function testTheOrderIsPaidWithTheMethodChosenOnTheCart(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->choose($cart, $method);

        $this->place($cart, $customer);

        self::assertSame(['payWithStoredMethod'], StoredMethodsPaymentModule::$calls);
        self::assertSame([$method->getId()], StoredMethodsPaymentModule::$paidWithMethodIds);
    }

    public function testWithoutAChosenMethodTheModuleIsAskedToPayWithANewOne(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');

        $this->place($cart, $customer);

        self::assertSame(['pay'], StoredMethodsPaymentModule::$calls);
    }

    public function testAMethodForgottenBetweenTheChoiceAndThePaymentLeavesANewOne(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->choose($cart, $method);

        $this->getService(CustomerPaymentMethodService::class)->forget($customer, $method);
        $this->place($cart, $customer);

        self::assertSame(['forget', 'pay'], StoredMethodsPaymentModule::$calls);
    }

    public function testAMethodExpiredSinceTheChoiceLeavesANewOne(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->choose($cart, $method);

        $method->setExpiresAt('2020-01-31')->save($this->getPropelConnection());
        $this->place($cart, $customer);

        self::assertSame(['pay'], StoredMethodsPaymentModule::$calls);
    }

    /**
     * The column is only a wish: a method written on the cart without going through the
     * selection is checked against the customer of the order, and not handed over.
     */
    public function testAMethodOfAnotherCustomerWrittenOnTheCartIsNotUsed(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $methodOfSomebodyElse = $this->storedMethodRow($this->factory->customer($this->factory->customerTitle()), $this->module, 'Visa ending 4242', '2030-12-31');
        $cart->setCustomerPaymentMethodId($methodOfSomebodyElse->getId())->save($this->getPropelConnection());

        $this->place($cart, $customer);

        self::assertSame(['pay'], StoredMethodsPaymentModule::$calls);
    }

    public function testAnotherAttemptOnTheSameOrderPaysWithTheSameMethod(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->choose($cart, $method);

        $first = $this->payThroughTheCheckout($cart);
        $second = $this->payThroughTheCheckout($cart);

        self::assertSame($first->getId(), $second->getId(), 'The unpaid order is presented again.');
        self::assertSame(['payWithStoredMethod', 'payWithStoredMethod'], StoredMethodsPaymentModule::$calls);
        self::assertSame([$method->getId(), $method->getId()], StoredMethodsPaymentModule::$paidWithMethodIds);
    }

    /**
     * The placement freezes the chosen method in the fingerprint of the order the same
     * way a later attempt reads it, so that attempt finds the order still describing the
     * cart.
     */
    public function testAnAttemptAfterThePlacementPresentsTheSameOrderAgain(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->choose($cart, $method);

        $this->place($cart, $customer);
        $placed = OrderQuery::create()->filterByCartId($cart->getId())->findOne($this->getPropelConnection());
        $retried = $this->payThroughTheCheckout($cart);

        self::assertSame($placed?->getId(), $retried->getId());
        self::assertSame([$method->getId(), $method->getId()], StoredMethodsPaymentModule::$paidWithMethodIds);
    }

    /**
     * The method chosen is part of what the order was placed with: choosing another one
     * places another order rather than presenting the first with a method it was not
     * placed with.
     */
    public function testChoosingAnotherStoredMethodPlacesAnotherOrder(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $first = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $second = $this->storedMethodRow($customer, $this->module, 'Mastercard ending 1111', '2031-06-30');
        $this->choose($cart, $first);

        $firstOrder = $this->payThroughTheCheckout($cart);
        $this->choose($this->reread($cart), $second);
        $secondOrder = $this->payThroughTheCheckout($this->reread($cart));

        self::assertNotSame($firstOrder->getId(), $secondOrder->getId());
        self::assertTrue(OrderQuery::create()->findPk($firstOrder->getId(), $this->getPropelConnection())?->isCancelled());
        self::assertSame([$first->getId(), $second->getId()], StoredMethodsPaymentModule::$paidWithMethodIds);
    }

    public function testTooManyPaymentsWithAStoredMethodAreRefusedBeforeTheProviderIsAsked(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->choose($cart, $method);
        $this->spendTheWholeHourlyAllowanceOf($customer, $method);

        try {
            $this->place($cart, $customer);
            self::fail('The payment over the allowance must be refused.');
        } catch (StoredMethodPaymentRateLimitedException) {
        }

        self::assertSame([], StoredMethodsPaymentModule::$calls, 'Neither the stored method nor a new one was asked to pay.');
        self::assertSame(
            0,
            OrderQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()),
            'The limiter is checked before the order is written, not after: a refused placement leaves no unpaid order behind.',
        );
    }

    /**
     * The allowance is a limit on paying, not something the cart is missing: every page
     * that shows the progression and the validation of the API read the steps, and a
     * customer over the limit is not to be answered with a failure there. The refusal
     * waits for the payment itself.
     */
    public function testTheAllowanceIsNeitherAStepOfTheTunnelNorAViolationOfTheCheckout(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->choose($cart, $method);
        $this->spendTheWholeHourlyAllowanceOf($customer, $method);
        $cart = $this->reread($cart);

        self::assertSame([], $this->getService(CheckoutValidationService::class)->collectViolations($cart));
        $this->getService(CheckoutValidationService::class)->validateForOrder($cart);

        $progression = $this->getService(CheckoutProgressionService::class);
        $progression->forget();
        self::assertNotSame(CheckoutStep::CODE_PAYMENT, $progression->firstIncompleteStep($cart)?->code);
    }

    /**
     * The tunnel of a theme validates the cart, then pays it: past the allowance, the
     * payment is what refuses, and still before an order is written.
     */
    public function testAPaymentThroughTheThemeOverTheAllowanceIsRefusedBeforeTheOrderIsWritten(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->choose($cart, $method);
        $this->spendTheWholeHourlyAllowanceOf($customer, $method);

        try {
            $cart = $this->reread($cart);
            $this->getService(CheckoutFacade::class)->pay(new CheckoutDTO(
                $cart,
                deliveryAddressId: (int) $cart->getAddressDeliveryId(),
                invoiceAddressId: (int) $cart->getAddressInvoiceId(),
            ));
            self::fail('The payment over the allowance must be refused.');
        } catch (StoredMethodPaymentRateLimitedException) {
        }

        self::assertSame([], StoredMethodsPaymentModule::$calls, 'Neither the stored method nor a new one was asked to pay.');
        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    /**
     * A retry through the theme's checkout presents the unpaid order again instead of
     * writing a new one — the one path MODULE_PAY reaches through payAgain() rather than
     * through a fresh ORDER_PAY. The allowance is not asked before that path, which writes
     * nothing: the refusal comes from StoredPaymentMethodPayment::pay(), which spends the
     * slot before it asks the module and refuses once the allowance is gone, so the module
     * is not called again.
     */
    public function testARetryThroughPayAgainIsRefusedWithoutCallingTheModuleWhenTheLimitIsHit(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->choose($cart, $method);
        $this->spendTheWholeHourlyAllowanceOf($customer, $method, self::PAYMENTS_ALLOWED_PER_HOUR - 1);

        // The last slot of the hour: this attempt still goes through and writes the
        // unpaid order the retry below will find.
        $this->payThroughTheCheckout($cart);
        self::assertSame(['payWithStoredMethod'], StoredMethodsPaymentModule::$calls, 'The last payment of the allowance still goes through.');

        try {
            $this->payThroughTheCheckout($cart);
            self::fail('A retry over the allowance must be refused.');
        } catch (StoredMethodPaymentRateLimitedException) {
        }

        self::assertSame(['payWithStoredMethod'], StoredMethodsPaymentModule::$calls, 'The module was not asked again by the retry.');
    }

    /**
     * MODULE_PAY pays with the method read off the cart, whatever the request said: a
     * caller that leaves the method out of the request is still paying with the one the
     * cart names, and is refused before the order is written all the same.
     */
    public function testTheAllowanceIsAskedOfTheMethodOnTheCartWhenTheRequestLeavesItOut(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->choose($cart, $method);
        $this->spendTheWholeHourlyAllowanceOf($customer, $method);
        $cart = $this->reread($cart);

        try {
            $this->getService(CheckoutPaymentService::class)->payAndReturnOutcome(new OrderPaymentRequest(
                $cart,
                (int) $cart->getAddressDeliveryId(),
                (int) $cart->getAddressInvoiceId(),
                (int) $cart->getDeliveryModuleId(),
                (int) $cart->getPaymentModuleId(),
            ));
            self::fail('The payment over the allowance must be refused.');
        } catch (StoredMethodPaymentRateLimitedException) {
        }

        self::assertSame([], StoredMethodsPaymentModule::$calls, 'Neither the stored method nor a new one was asked to pay.');
        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    /**
     * A cart changed since its unpaid order was written gets a new order, and the unpaid
     * one is cancelled to make way for it. Past the allowance there is no new order to
     * make way for: the unpaid one is left standing, still waiting for its payment.
     */
    public function testAnUnpaidOrderIsNotCancelledForAPaymentOverTheAllowance(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->choose($cart, $method);
        $this->spendTheWholeHourlyAllowanceOf($customer, $method, self::PAYMENTS_ALLOWED_PER_HOUR - 1);

        $unpaid = $this->payThroughTheCheckout($cart);
        StoredMethodsPaymentModule::reset();

        // One more of the same item: the unpaid order no longer describes the cart.
        $item = $this->reread($cart)->getCartItems(null, $this->getPropelConnection())->getFirst()
            ?? throw new \RuntimeException('The cart has no item.');
        $item->setQuantity($item->getQuantity() + 1)->save($this->getPropelConnection());

        try {
            $this->payThroughTheCheckout($cart);
            self::fail('The payment over the allowance must be refused.');
        } catch (StoredMethodPaymentRateLimitedException) {
        }

        self::assertSame([], StoredMethodsPaymentModule::$calls, 'Neither the stored method nor a new one was asked to pay.');
        self::assertSame(1, OrderQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()), 'No new order was written.');
        self::assertFalse(
            OrderQuery::create()->findPk($unpaid->getId(), $this->getPropelConnection())?->isCancelled(),
            'The unpaid order is still there to be paid once the hour has passed.',
        );
    }

    /**
     * What is logged when the choice can no longer pay names the rows by their ids, and
     * never carries the provider reference.
     */
    public function testAChoiceThatCanNoLongerPayIsLoggedWithoutTheProviderReference(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2020-01-31');
        $cart->setCustomerPaymentMethodId($method->getId())->save($this->getPropelConnection());
        $order = (new Order())
            ->setId(424242)
            ->setCartId($cart->getId())
            ->setCustomerId($customer->getId())
            ->setPaymentModuleId($this->module->getId());

        $logger = $this->collectingLogger();
        $payment = new StoredPaymentMethodPayment(
            $this->getService(StoredPaymentMethodSelection::class),
            $this->storedMethodPaymentLimiter(),
            $logger,
        );

        self::assertNull($payment->methodChosenFor($order, new StoredMethodsPaymentModule()));
        self::assertCount(1, $logger->messages);
        self::assertStringContainsString('#'.$method->getId(), $logger->messages[0]);
        self::assertStringNotContainsString((string) $method->getToken(), $logger->messages[0]);
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

    /**
     * The path of a theme and of a second attempt: the checkout service, which reuses the
     * unpaid order when it still describes the cart.
     */
    private function payThroughTheCheckout(Cart $cart): Order
    {
        $cart = $this->reread($cart);
        $this->getService(CheckoutPaymentService::class)->pay(
            $cart,
            (int) $cart->getAddressDeliveryId(),
            (int) $cart->getAddressInvoiceId(),
            (int) $cart->getDeliveryModuleId(),
            (int) $cart->getPaymentModuleId(),
        );

        return OrderQuery::create()
            ->filterByCartId($cart->getId())
            ->orderById(\Propel\Runtime\ActiveQuery\Criteria::DESC)
            ->findOne($this->getPropelConnection())
            ?? throw new \RuntimeException('No order was placed.');
    }

    private function choose(Cart $cart, CustomerPaymentMethod $method): void
    {
        $this->getService(CheckoutFacade::class)->selectPaymentModule(new CheckoutDTO(
            $this->reread($cart),
            paymentModuleId: $this->module->getId(),
            customerPaymentMethodId: $method->getId(),
        ));
    }

    /**
     * Ten payments already went through for this customer within the hour.
     */
    private function spendTheWholeHourlyAllowanceOf(Customer $customer, CustomerPaymentMethod $method, int $count = self::PAYMENTS_ALLOWED_PER_HOUR): void
    {
        $payment = $this->getService(StoredPaymentMethodPayment::class);
        $order = (new Order())->setCustomerId($customer->getId());

        for ($payments = 0; $payments < $count; ++$payments) {
            $payment->pay(new StoredMethodsPaymentModule(), $order, $method);
        }

        StoredMethodsPaymentModule::reset();
    }

    private function storedMethodPaymentLimiter(): RateLimiterFactoryInterface
    {
        return new class implements RateLimiterFactoryInterface {
            public function create(?string $key = null): \Symfony\Component\RateLimiter\LimiterInterface
            {
                return new \Symfony\Component\RateLimiter\Policy\NoLimiter();
            }
        };
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
        // The limiter keeps its count outside the transaction of the test, and a test
        // database built again hands out the same customer ids.
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
            // Read back from the table, whose column has no default, the postage of a cart
            // no selection quoted would be empty.
            ->setPostage('0')
            ->setPostageTax('0')
            ->save($this->getPropelConnection());

        return [$cart, $customer];
    }

    private function serveCountryWith(Module $module, Country $country): void
    {
        $area = (new Area())->setName('Stored method payment test area '.uniqid());
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

    private function collectingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /**
             * @var list<string>
             */
            public array $messages = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };
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
