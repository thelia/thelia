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
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The cart outlives the order placed from it, and is consumed once that order is paid.
 *
 * A declined card, a cancelled payment or a closed tab used to leave the buyer on an
 * empty cart: the placement emptied it before the payment module had answered. The cart
 * now stays the session cart until an order that names it is paid, and it is at that
 * moment — on the next read — that the session gets a new one.
 */
final class CartSurvivesPlacementTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    private ?string $previousGuestCheckoutMode = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->stopThePaymentModuleFromAnswering();

        // The guest scenario below needs the shop to allow ordering without an account,
        // whatever the test before this one left in the configuration.
        $this->previousGuestCheckoutMode = ConfigQuery::getGuestCheckoutMode();
        ConfigQuery::write('guest_checkout_mode', 'enabled');
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        ConfigQuery::write('guest_checkout_mode', (string) $this->previousGuestCheckoutMode);

        $session = $this->session();
        $session->clearCustomerUser();
        $session->setSessionCart(null);
        $session->remove('cart_use_cookie');

        parent::tearDown();
    }

    /**
     * AC1 — placing the order leaves the session cart, its lines and its cookie alone.
     */
    public function testAC1PlacingAnOrderLeavesTheSessionCartInPlace(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $this->sitAtTheBrowserWith($customer, $cart);
        $itemIdsBefore = $this->itemIdsOf($cart);

        $placed = $this->pay($cart);

        self::assertTrue($placed->isNotPaid(), 'The reference case is an order still waiting for its payment.');

        $sessionCart = $this->session()->getSessionCart($this->dispatcher());

        self::assertSame($cart->getId(), $sessionCart->getId(), 'The session must still hand back the very cart the order was placed from.');
        self::assertSame($itemIdsBefore, $this->itemIdsOf($sessionCart), 'With the same lines in it.');
        self::assertSame(
            $cart->getToken(),
            $this->session()->get('cart_use_cookie'),
            'And the persistent cart cookie must still name it — an empty value is the instruction to drop the cookie.',
        );
    }

    /**
     * AC2 — a cart whose order is paid is consumed on read.
     */
    public function testAC2APaidCartIsConsumedOnRead(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $this->sitAtTheBrowserWith($customer, $cart);
        $placed = $this->pay($cart);

        $this->moveTo($placed, OrderStatusQuery::getPaidStatus());

        $afterPayment = $this->session()->getSessionCart($this->dispatcher());

        self::assertNotSame($cart->getId(), $afterPayment->getId(), 'A paid cart is not the session cart any more.');
        self::assertCount(0, $afterPayment->getCartItems(), 'The cart that replaces it is empty.');
    }

    /**
     * AC2 — "paid" includes a status of the shop's own that stands for paid.
     */
    public function testAC2ACartPaidThroughAProjectStatusIsConsumedToo(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $this->sitAtTheBrowserWith($customer, $cart);
        $placed = $this->pay($cart);

        $this->moveTo($placed, $this->factory->orderStatus(['code' => 'paid-by-wire-'.uniqid(), 'equivalentCode' => OrderStatus::CODE_PAID]));

        $afterPayment = $this->session()->getSessionCart($this->dispatcher());

        self::assertNotSame($cart->getId(), $afterPayment->getId(), 'A status equivalent to paid consumes the cart as paid does.');
        self::assertCount(0, $afterPayment->getCartItems());
    }

    /**
     * AC2 — the consumed cart never comes back, to this buyer or to the next person on
     * the browser, and a cart of somebody else is still refused.
     */
    public function testAC2AConsumedCartIsNeverHandedBackAgain(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $this->sitAtTheBrowserWith($customer, $cart);
        $placed = $this->pay($cart);
        $this->moveTo($placed, OrderStatusQuery::getPaidStatus());

        $first = $this->session()->getSessionCart($this->dispatcher());
        // The browser still holds the id of the paid cart: a stale tab, a replayed cookie.
        $this->session()->set(Session::SESSION_CART_ID_NAME, $cart->getId());
        $second = $this->session()->getSessionCart($this->dispatcher());

        self::assertNotSame($cart->getId(), $first->getId());
        self::assertNotSame($cart->getId(), $second->getId(), 'Pointing the session at the paid cart again must not bring it back.');

        // And the rule that was already there: a cart of another customer is not this one's.
        [$othersCart, $other] = $this->cartReadyToPay();
        $this->session()->setSessionCart($othersCart);
        $this->session()->setCustomerUser($customer);

        self::assertNotSame($othersCart->getId(), $this->session()->getSessionCart($this->dispatcher())->getId(), 'A cart belonging to another customer is still refused.');
        self::assertNotSame($customer->getId(), $other->getId());
    }

    /**
     * AC3 — the core no longer raises ORDER_CART_CLEAR when the order is placed.
     */
    public function testAC3OrderCartClearIsNotDispatchedByThePlacement(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();
        $this->sitAtTheBrowserWith($customer, $cart);

        $cleared = 0;
        $this->listen(TheliaEvents::ORDER_CART_CLEAR, static function () use (&$cleared): void {
            ++$cleared;
        });

        $this->pay($cart);

        self::assertSame(0, $cleared, 'Placing an order must not empty the cart any more.');
    }

    /**
     * AC3 — but the event is still there for a module that raises it: it empties the
     * cart and retires the guest exactly as before.
     */
    public function testAC3OrderCartClearRaisedByAModuleStillEmptiesTheCartAndRetiresTheGuest(): void
    {
        [$cart, $guest] = $this->cartReadyToPay(guest: true);
        $this->sitAtTheBrowserWith($guest, $cart);
        $placed = $this->pay($cart);

        self::assertInstanceOf(Customer::class, $this->session()->getCustomerUser(), 'The guest is still there once the order is placed.');

        $this->dispatcher()->dispatch(new OrderEvent($placed), TheliaEvents::ORDER_CART_CLEAR);

        $afterClear = $this->session()->getSessionCart($this->dispatcher());

        self::assertNotSame($cart->getId(), $afterClear->getId(), 'The cart is emptied by the listener the core keeps on ORDER_CART_CLEAR.');
        self::assertCount(0, $afterClear->getCartItems());
        self::assertNull($this->session()->getCustomerUser(), 'And the guest is out of the session.');
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

    private function moveTo(Order $order, OrderStatus $status): void
    {
        $order->setStatusId($status->getId())->save($this->getPropelConnection());
    }

    /**
     * What a browser holds: the customer, the cart, and the cookie that names it.
     */
    private function sitAtTheBrowserWith(Customer $customer, Cart $cart): void
    {
        $session = $this->session();
        $session->setCustomerUser($customer);
        $session->setSessionCart($cart);
        $session->setCurrency($this->factory->currency());
        $session->set('cart_use_cookie', $cart->getToken());
    }

    /**
     * @return list<int>
     */
    private function itemIdsOf(Cart $cart): array
    {
        $ids = [];
        foreach ($cart->getCartItems() as $item) {
            $ids[] = (int) $item->getId();
        }
        sort($ids);

        return $ids;
    }

    /**
     * @return array{0: Cart, 1: Customer}
     */
    private function cartReadyToPay(bool $guest = false): array
    {
        $country = $this->factory->country();
        $title = $this->factory->customerTitle();
        $customer = $guest ? $this->factory->guestCustomer($title) : $this->factory->customer($title);

        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No payment module installed — run bin/test-prepare.');

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
            ->setPaymentModuleId($paymentModule->getId())
            ->save($this->getPropelConnection());

        return [$cart, $customer];
    }

    /**
     * The payment module would answer with its own page; nothing here is about it.
     */
    private function stopThePaymentModuleFromAnswering(): void
    {
        $this->listen(TheliaEvents::MODULE_PAY, static function (OrderPaymentEvent $event): void {
            $event->stopPropagation();
        });
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

    private function session(): Session
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}
