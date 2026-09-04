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
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Domain\Checkout\Exception\GuestCheckoutNotAllowedException;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\GuestCheckoutPolicy;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * Ordering without an account has to end where ordering with one ends: a placed order,
 * attached to the customer row the checkout was carried by.
 *
 * The two things that make it different from a normal checkout are covered here: the
 * shop decides whether it is offered at all, and the guest must not be left signed in
 * on the browser once the order is placed.
 */
final class GuestCheckoutTest extends ActionIntegrationTestCase
{
    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    private ?string $previousGuestCheckoutMode = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousGuestCheckoutMode = ConfigQuery::getGuestCheckoutMode();
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('guest_checkout_mode', (string) $this->previousGuestCheckoutMode);

        // The kernel dispatcher outlives the test, so listeners left behind
        // would answer the next test of the process.
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->kernelDispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        parent::tearDown();
    }

    public function testAGuestPlacesAnOrderAttachedToTheGuestAccount(): void
    {
        ConfigQuery::write('guest_checkout_mode', 'enabled');
        $fixtures = $this->createCheckoutReadyCart();

        $order = $this->checkout($fixtures);

        self::assertSame(
            $fixtures['customer']->getId(),
            $order->getCustomerId(),
            'The placed order must hang off the guest row the checkout was carried by.',
        );
        self::assertTrue(
            $order->getCustomer()->isGuest(),
            'The customer behind the order is the guest, not an account someone signed into.',
        );
    }

    /**
     * The guest was put in the session to carry one order through. Leaving them there
     * would hand the next person on this browser an identity nobody signed into.
     */
    public function testTheGuestIsNotLeftInTheSessionOnceTheOrderIsPlaced(): void
    {
        ConfigQuery::write('guest_checkout_mode', 'enabled');
        $fixtures = $this->createCheckoutReadyCart();

        $this->checkout($fixtures);

        $session = $this->session();
        self::assertNull($session->getCustomerUser(), 'The guest must be out of the session.');
        self::assertFalse($session->isCustomerGuest(), 'And nothing must still read the session as a guest one.');
    }

    /**
     * The awkward session: it carried a guest through the identification step, then
     * whoever holds it signed in for real. What follows is a normal customer checkout,
     * and the customer must still be there when it is over.
     *
     * Signing in goes through TheliaEvents::CUSTOMER_LOGIN, exactly as the login page
     * does, because that is the whole point — what tells the two apart has to be
     * something the login actually changes, not a flag nobody clears.
     */
    public function testACustomerWhoSignedInOverAGuestSessionStaysInTheSession(): void
    {
        ConfigQuery::write('guest_checkout_mode', 'enabled');
        $account = $this->factory->customer($this->factory->customerTitle());
        $fixtures = $this->createCheckoutReadyCart($account);
        $guest = $this->factory->guestCustomer($this->factory->customerTitle());

        $this->checkout($fixtures, sessionStartsAs: $guest, signInAs: $account);

        $inSession = $this->session()->getCustomerUser();

        self::assertInstanceOf(Customer::class, $inSession, 'Placing an order must not sign a customer out.');
        self::assertSame($account->getId(), $inSession->getId());
    }

    /**
     * The policy was answered once, when the buyer said they had no account. The cart
     * stayed open to changes afterwards, so it is answered again here — the last moment
     * before the order exists.
     */
    public function testACartThatGainsAForbiddenProductIsRefusedAtPayment(): void
    {
        ConfigQuery::write('guest_checkout_mode', 'enabled_unless_product_forbids');
        $fixtures = $this->createCheckoutReadyCart();

        self::assertTrue(
            $this->getService(GuestCheckoutPolicy::class)->isGuestCheckoutAllowedForCart($fixtures['cart']),
            'Nothing in this cart forbade it when the guest identified themselves.',
        );

        $fixtures['product']->setGuestCheckoutForbidden(1)->save($this->getPropelConnection());

        $this->expectException(GuestCheckoutNotAllowedException::class);

        $this->checkout($fixtures);
    }

    public function testAGuestIsRefusedAtPaymentWhenTheShopTurnsTheOptionOff(): void
    {
        ConfigQuery::write('guest_checkout_mode', 'enabled');
        $fixtures = $this->createCheckoutReadyCart();

        ConfigQuery::write('guest_checkout_mode', 'disabled');

        $this->expectException(GuestCheckoutNotAllowedException::class);

        $this->checkout($fixtures);
    }

    /**
     * The re-check reads the customer behind the cart, so it must have nothing to say
     * about a cart belonging to someone with an account.
     */
    public function testACustomerWithAnAccountIsNotAffectedByTheGuestCheckoutSetting(): void
    {
        ConfigQuery::write('guest_checkout_mode', 'disabled');
        $account = $this->factory->customer($this->factory->customerTitle());
        $fixtures = $this->createCheckoutReadyCart($account);
        $guest = $this->factory->guestCustomer($this->factory->customerTitle());

        $order = $this->checkout($fixtures, sessionStartsAs: $guest, signInAs: $account);

        self::assertSame($account->getId(), $order->getCustomerId());
    }

    public function testTheShopRefusesTheGuestCheckoutUntilItIsTurnedOn(): void
    {
        ConfigQuery::write('guest_checkout_mode', 'disabled');

        $policy = $this->getService(GuestCheckoutPolicy::class);

        self::assertSame(GuestCheckoutMode::Disabled, $policy->mode());
        self::assertFalse($policy->isGuestCheckoutEnabled());
        self::assertFalse($policy->isGuestCheckoutAllowedForCart($this->createCheckoutReadyCart()['cart']));
    }

    /**
     * A value the shop never chose — a typo, a leftover from an older version — must
     * not open the checkout by accident.
     */
    public function testAnUnknownModeIsReadAsDisabled(): void
    {
        ConfigQuery::write('guest_checkout_mode', 'someone-typed-this');

        self::assertSame(GuestCheckoutMode::Disabled, $this->getService(GuestCheckoutPolicy::class)->mode());
    }

    public function testEveryCartMayBeOrderedAsAGuestWhenTheShopSaysSo(): void
    {
        ConfigQuery::write('guest_checkout_mode', 'enabled');

        $fixtures = $this->createCheckoutReadyCart();
        $fixtures['product']->setGuestCheckoutForbidden(1)->save($this->getPropelConnection());

        $policy = $this->getService(GuestCheckoutPolicy::class);

        self::assertTrue($policy->isGuestCheckoutEnabled());
        self::assertTrue(
            $policy->isGuestCheckoutAllowedForCart($fixtures['cart']),
            'In this mode the product flag has nothing to say.',
        );
    }

    public function testACartHoldingAForbiddenProductIsRefusedInTheProductAwareMode(): void
    {
        ConfigQuery::write('guest_checkout_mode', 'enabled_unless_product_forbids');

        $fixtures = $this->createCheckoutReadyCart();
        $policy = $this->getService(GuestCheckoutPolicy::class);

        self::assertTrue(
            $policy->isGuestCheckoutAllowedForCart($fixtures['cart']),
            'Nothing in this cart forbids it yet.',
        );

        $fixtures['product']->setGuestCheckoutForbidden(1)->save($this->getPropelConnection());

        self::assertFalse(
            $policy->isGuestCheckoutAllowedForCart($fixtures['cart']),
            'One product that needs an account is enough to refuse the whole cart.',
        );
    }

    /**
     * Walks a cart through the payment, from a session holding the guest that filled it.
     *
     * $signInAs replays what the login page does over that same session: the CUSTOMER_LOGIN
     * event, which puts the account in the session and hands it the cart. Nothing else is
     * touched by hand — the point of these tests is that the guest state follows the
     * customer row, so writing it directly would prove nothing.
     *
     * @param array{cart: Cart, customer: Customer, currency: Currency, product: Product, deliveryModule: Module, paymentModule: Module, deliveryAddressId: int, invoiceAddressId: int} $fixtures
     */
    private function checkout(array $fixtures, ?Customer $sessionStartsAs = null, ?Customer $signInAs = null): Order
    {
        $session = $this->session();
        $session->setCustomerUser($sessionStartsAs ?? $fixtures['customer']);
        $session->setSessionCart($fixtures['cart']);
        $session->setCurrency($fixtures['currency']);

        if ($signInAs instanceof Customer) {
            $this->kernelDispatcher()->dispatch(new CustomerLoginEvent($signInAs), TheliaEvents::CUSTOMER_LOGIN);
        }

        $cart = $fixtures['cart'];

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
            $cart,
            $fixtures['deliveryAddressId'],
            $fixtures['invoiceAddressId'],
            $fixtures['deliveryModule']->getId(),
            $fixtures['paymentModule']->getId(),
        );

        self::assertInstanceOf(Order::class, $placedOrder, 'The checkout did not place an order.');

        return $placedOrder;
    }

    /**
     * @return array{cart: Cart, customer: Customer, currency: Currency, product: Product, deliveryModule: Module, paymentModule: Module, deliveryAddressId: int, invoiceAddressId: int}
     */
    private function createCheckoutReadyCart(?Customer $owner = null): array
    {
        $currency = $this->factory->currency();
        $customerTitle = $this->factory->customerTitle();
        $customer = $owner ?? $this->factory->guestCustomer($customerTitle);
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
            ->setToken(uniqid('guest-checkout-', true))
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

        return [
            'cart' => $cart,
            'customer' => $customer,
            'currency' => $currency,
            'product' => $product,
            'deliveryModule' => $deliveryModule,
            'paymentModule' => $paymentModule,
            'deliveryAddressId' => $deliveryAddress->getId(),
            'invoiceAddressId' => $invoiceAddress->getId(),
        ];
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
