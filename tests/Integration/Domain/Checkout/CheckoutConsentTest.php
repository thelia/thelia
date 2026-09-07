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
use Thelia\Action\Cart as CartAction;
use Thelia\Core\Event\Cart\CartCheckoutEvent;
use Thelia\Core\Event\Consent\ConsentToggleActiveEvent;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderManualEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Checkout\Exception\MissingConsentException;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\CheckoutValidationService;
use Thelia\Domain\Checkout\Service\ConsentAcceptanceStore;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartItem;
use Thelia\Model\Consent;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderConsent;
use Thelia\Model\OrderConsentQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The consents of the checkout, from the box the buyer ticks to the proof kept on the
 * order.
 *
 * Two things are being pinned here. An order is refused while a required consent is
 * unanswered, and it names the consent the buyer still has to give. And what ends up on
 * the order is a copy, frozen: rewording a consent, making it optional or turning it
 * off afterwards changes nothing about the orders already placed under it.
 */
final class CheckoutConsentTest extends ActionIntegrationTestCase
{
    private const QUOTED_POSTAGE = 12.0;
    private const QUOTED_POSTAGE_TAX = 2.0;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        // The shop ships with the terms and conditions as a mandatory consent. Every
        // test here builds the list it needs from scratch, so it starts from an empty
        // one — the rollback of IntegrationTestCase puts the seeded row back.
        foreach (ConsentQuery::create()->find($this->getPropelConnection()) as $seededConsent) {
            $seededConsent->delete($this->getPropelConnection());
        }

        $this->getService(ConsentAcceptanceStore::class)->clear();
    }

    protected function tearDown(): void
    {
        // The kernel dispatcher outlives the test, so listeners left behind would
        // answer the next test of the process.
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->kernelDispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        $this->getService(ConsentAcceptanceStore::class)->clear();

        parent::tearDown();
    }

    public function testAnOrderIsRefusedWhileAMandatoryConsentIsUnanswered(): void
    {
        $this->createConsent('cgv-test', 'I accept the terms of this very shop', mandatory: true);
        $fixtures = $this->createCheckoutReadyCart();

        $this->expectException(MissingConsentException::class);
        $this->expectExceptionMessage('I accept the terms of this very shop');

        $this->getService(CheckoutValidationService::class)->validateForOrder($fixtures['cart']);
    }

    public function testAnOrderIsRefusedWhileAMandatoryConsentIsDeclined(): void
    {
        $this->createConsent('cgv-test', 'I accept the terms of this very shop', mandatory: true);
        $fixtures = $this->createCheckoutReadyCart();

        $this->acceptances(['cgv-test' => false]);

        $this->expectException(MissingConsentException::class);

        $this->getService(CheckoutValidationService::class)->validateForOrder($fixtures['cart']);
    }

    public function testAnOrderGoesThroughOnceEveryMandatoryConsentIsAccepted(): void
    {
        $this->createConsent('cgv-test', 'The terms of sale', mandatory: true);
        $this->createConsent('newsletter-test', 'Send me the newsletter', mandatory: false);
        $fixtures = $this->createCheckoutReadyCart();

        // The optional one is declined, which must not stand in the way.
        $this->acceptances(['cgv-test' => true, 'newsletter-test' => false]);

        $this->getService(CheckoutValidationService::class)->validateForOrder($fixtures['cart']);

        $order = $this->checkout($fixtures);
        $rows = $this->orderConsentsOf($order);

        self::assertCount(2, $rows, 'Every consent the shop was asking for belongs on the order, declined ones included.');
        self::assertSame(['cgv-test', 'newsletter-test'], array_map(
            static fn (OrderConsent $row): ?string => $row->getConsentCode(),
            $rows,
        ));
        self::assertTrue($rows[0]->isAccepted());
        self::assertFalse($rows[1]->isAccepted(), 'A box left unticked is recorded as declined, not left out.');
        self::assertSame('The terms of sale', $rows[0]->getTitle());
        self::assertNotNull($rows[0]->getCreatedAt());
        self::assertSame('127.0.0.1', $rows[0]->getIpAddress());
    }

    public function testTheAcceptancesAreForgottenOnceTheOrderCarriesThem(): void
    {
        $this->createConsent('cgv-test', 'The terms of sale', mandatory: true);
        $fixtures = $this->createCheckoutReadyCart();

        $this->acceptances(['cgv-test' => true]);
        $this->checkout($fixtures);

        self::assertSame(
            [],
            $this->getService(ConsentAcceptanceStore::class)->all(),
            'The next order placed in the same session must ask again.',
        );
    }

    public function testRewordingAConsentDoesNotRewriteThePastOrders(): void
    {
        $consent = $this->createConsent('cgv-test', 'The terms of sale, first wording', mandatory: true);
        $fixtures = $this->createCheckoutReadyCart();

        $this->acceptances(['cgv-test' => true]);
        $order = $this->checkout($fixtures);

        $consent
            ->setLocale('en_US')
            ->setTitle('The terms of sale, reworded afterwards')
            ->save($this->getPropelConnection());

        $rows = $this->orderConsentsOf($order);

        self::assertCount(1, $rows);
        self::assertSame('The terms of sale, first wording', $rows[0]->getTitle());
    }

    public function testATurnedOffConsentIsNeitherRequiredNorCopiedWhilePastRowsStay(): void
    {
        $consent = $this->createConsent('cgv-test', 'The terms of sale', mandatory: true);

        $firstFixtures = $this->createCheckoutReadyCart();
        $this->acceptances(['cgv-test' => true]);
        $firstOrder = $this->checkout($firstFixtures);

        self::assertCount(1, $this->orderConsentsOf($firstOrder));

        // Through the real listener, not a raw model write: ConsentProvider memoizes its
        // answer for the request, and only the toggle event tells it to forget it.
        $this->dispatch(new ConsentToggleActiveEvent($consent->getId()), TheliaEvents::CONSENT_TOGGLE_ACTIVE);

        $secondFixtures = $this->createCheckoutReadyCart();

        // Nothing is asked for any more, so nothing has to be answered.
        $this->getService(CheckoutValidationService::class)->validateForOrder($secondFixtures['cart']);

        $secondOrder = $this->checkout($secondFixtures);

        self::assertSame([], $this->orderConsentsOf($secondOrder), 'A consent no longer asked for must not be recorded on new orders.');
        self::assertCount(1, $this->orderConsentsOf($firstOrder), 'The proof collected while the consent was live must survive it being turned off.');
    }

    public function testAManualOrderCreationAsksForNothing(): void
    {
        $this->createConsent('cgv-test', 'The terms of sale', mandatory: true);

        $fixtures = $this->createCheckoutReadyCart();
        $cart = $fixtures['cart'];

        $sessionOrder = (new Order())
            ->setDeliveryOrderAddressId($fixtures['deliveryAddressId'])
            ->setInvoiceOrderAddressId($fixtures['invoiceAddressId'])
            ->setPaymentModuleId($fixtures['paymentModule']->getId())
            ->setDeliveryModuleId($fixtures['deliveryModule']->getId())
            ->setPostage('0')
            ->setPostageTax('0');

        $event = new OrderManualEvent(
            $sessionOrder,
            $fixtures['currency'],
            $this->factory->lang(),
            $cart,
            $fixtures['customer'],
        );

        // The back office places orders with no session and no boxes to tick: this must
        // not raise MissingConsentException.
        $this->dispatch($event, TheliaEvents::ORDER_CREATE_MANUAL);

        $placedOrder = $event->getPlacedOrder();

        self::assertNotNull($placedOrder->getId());
        self::assertSame(
            [],
            $this->orderConsentsOf($placedOrder),
            'A manual order has no buyer ticking boxes, so it must not be recorded as having answered — accepted or refused — any consent.',
        );
    }

    /**
     * @return array<int, OrderConsent>
     */
    private function orderConsentsOf(Order $order): array
    {
        return iterator_to_array(
            OrderConsentQuery::create()
                ->filterByOrderId($order->getId())
                ->orderById()
                ->find($this->getPropelConnection()),
            false,
        );
    }

    /**
     * @param array<string, bool> $acceptances
     */
    private function acceptances(array $acceptances): void
    {
        $this->getService(ConsentAcceptanceStore::class)->replace($acceptances);
    }

    private function createConsent(string $code, string $title, bool $mandatory): Consent
    {
        $consent = (new Consent())
            ->setCode($code)
            ->setMandatory($mandatory ? 1 : 0)
            ->setActive(1)
            ->setLocale('en_US')
            ->setTitle($title);
        $consent->save($this->getPropelConnection());

        return $consent;
    }

    /**
     * @param array{cart: Cart, customer: Customer, currency: Currency, deliveryModule: Module, paymentModule: Module, deliveryAddressId: int, invoiceAddressId: int} $fixtures
     */
    private function checkout(array $fixtures): Order
    {
        $session = $this->session();
        $session->setCustomerUser($fixtures['customer']);
        $session->setSessionCart($fixtures['cart']);
        $session->setCurrency($fixtures['currency']);

        $placedOrder = null;
        $this->listen(
            TheliaEvents::ORDER_BEFORE_PAYMENT,
            static function (OrderEvent $event) use (&$placedOrder): void {
                $placedOrder = $event->getOrder();
            },
        );
        // The payment module would answer with its own payment page. Everything this
        // test is about is settled by then, so the chain stops here.
        $this->listen(
            TheliaEvents::MODULE_PAY,
            static function (OrderPaymentEvent $event): void {
                $event->stopPropagation();
            },
            256,
        );

        $this->getService(CheckoutPaymentService::class)->pay(
            $fixtures['cart'],
            $fixtures['deliveryAddressId'],
            $fixtures['invoiceAddressId'],
            $fixtures['deliveryModule']->getId(),
            $fixtures['paymentModule']->getId(),
        );

        self::assertInstanceOf(Order::class, $placedOrder, 'The checkout did not place an order.');

        return $placedOrder;
    }

    /**
     * @return array{cart: Cart, customer: Customer, currency: Currency, deliveryModule: Module, paymentModule: Module, deliveryAddressId: int, invoiceAddressId: int}
     */
    private function createCheckoutReadyCart(): array
    {
        $currency = $this->factory->currency();
        $customerTitle = $this->factory->customerTitle();
        $customer = $this->factory->customer($customerTitle);
        $country = $this->factory->country();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
            ['baseQuantity' => 100],
        );

        $deliveryAddress = $this->createCartAddress($customerTitle->getId(), $country->getId());
        $invoiceAddress = $this->createCartAddress($customerTitle->getId(), $country->getId());

        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No payment module installed — run bin/test-prepare.');

        $cart = (new Cart())
            ->setCustomerId($customer->getId())
            ->setCurrencyId($currency->getId())
            ->setToken(uniqid('checkout-consent-', true))
            ->setAddressDeliveryId($deliveryAddress->getId())
            ->setAddressInvoiceId($invoiceAddress->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($paymentModule->getId());
        $cart->save($this->getPropelConnection());

        $productSaleElements = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne();
        self::assertNotNull($productSaleElements);

        (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($product->getId())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setQuantity(1)
            ->setPrice('10.00')
            ->setPromoPrice('10.00')
            ->setPromo(0)
            ->save($this->getPropelConnection());

        $this->quotePostageOnCart($cart);

        // The guards the consent one sits behind: the delivery module has to serve the
        // country and quote the cart, and the payment module has to accept it.
        $this->serveCountryWith($deliveryModule, $country);
        $this->answerDeliveryQuoteWith(valid: true);
        $this->answerPaymentValidityWith(valid: true);

        return [
            'cart' => $cart,
            'customer' => $customer,
            'currency' => $currency,
            'deliveryModule' => $deliveryModule,
            'paymentModule' => $paymentModule,
            'deliveryAddressId' => $deliveryAddress->getId(),
            'invoiceAddressId' => $invoiceAddress->getId(),
        ];
    }

    /**
     * Runs the real CART_SET_POSTAGE listener on a fixed quote, so that the cart passes
     * the delivery checks the consent guard sits behind.
     */
    private function quotePostageOnCart(Cart $cart): void
    {
        $action = new class extends CartAction {
            public OrderPostage $quote;

            public function __construct()
            {
                // The overridden method below is the only one this test calls, and it
                // uses none of the parent's dependencies.
            }

            protected function getPostageByDeliveryModuleId(
                Cart $cart,
                EventDispatcherInterface $dispatcher,
                int $moduleId,
                int $deliveryAddressId,
            ): OrderPostage {
                return $this->quote;
            }
        };
        $action->quote = new OrderPostage(self::QUOTED_POSTAGE, self::QUOTED_POSTAGE_TAX, 'VAT 20');

        $action->calculatePostage(new CartCheckoutEvent($cart), TheliaEvents::CART_SET_POSTAGE, $this->dispatcher);

        $cart->reload();
    }

    private function serveCountryWith(Module $module, Country $country): void
    {
        $area = (new Area())->setName('Checkout consent test area');
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($module->getId())->save($this->getPropelConnection());
    }

    private function answerDeliveryQuoteWith(bool $valid): void
    {
        $this->listen(
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
            static function (DeliveryPostageEvent $event) use ($valid): void {
                $event->setValidModule($valid);
                $event->setPostage(new OrderPostage(self::QUOTED_POSTAGE, self::QUOTED_POSTAGE_TAX, 'VAT 20'));
                $event->stopPropagation();
            },
            512,
        );
    }

    private function answerPaymentValidityWith(bool $valid): void
    {
        $this->listen(
            TheliaEvents::MODULE_PAYMENT_IS_VALID,
            static function (IsValidPaymentEvent $event) use ($valid): void {
                $event->setValidModule($valid);
                $event->stopPropagation();
            },
            512,
        );
    }

    private function createCartAddress(int $customerTitleId, int $countryId): CartAddress
    {
        $address = (new CartAddress())
            ->setCustomerTitleId($customerTitleId)
            ->setFirstname('Jane')
            ->setLastname('Doe')
            ->setAddress1('1 rue du Port')
            ->setZipcode('44000')
            ->setCity('Nantes')
            ->setCountryId($countryId);
        $address->save($this->getPropelConnection());

        return $address;
    }

    private function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->kernelDispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function kernelDispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }

    private function session(): Session
    {
        return static::getContainer()->get('request_stack')->getCurrentRequest()->getSession();
    }
}
