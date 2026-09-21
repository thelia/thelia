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
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Order\RetryingPaymentModule;

/**
 * A second payment attempt on a cart that already carries an unpaid order.
 *
 * Nothing changed and the payment module can take the same order twice: the order is
 * reused, only the module is called again, and no second confirmation e-mail goes out.
 * Anything else: the previous order is cancelled through the status flow and one new
 * order is placed. Either way a cart never carries more than one live unpaid order.
 */
final class PaymentRetryTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    private int $modulePayDispatches = 0;

    private int $beforePaymentDispatches = 0;

    /** @var list<array{0: int, 1: int}> order id, new status id */
    private array $statusChanges = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->registerTheRetryingPaymentModule();
        $this->countTheEventsOfThePlacement();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        parent::tearDown();
    }

    /**
     * AC4 — same cart, module that supports retries: the unpaid order is reused.
     */
    public function testAC4ASecondAttemptOnAnUnchangedCartReusesTheUnpaidOrder(): void
    {
        [$cart, $product] = $this->cartReadyToPay(RetryingPaymentModule::getModuleCode());
        $stockBefore = $this->stockOf($product);

        $first = $this->pay($cart);
        $second = $this->pay($cart);

        self::assertSame($first->getId(), $second->getId(), 'The very same order is handed back.');
        self::assertSame($first->getRef(), $second->getRef(), 'With the same reference.');
        self::assertCount(1, $this->ordersOf($cart), 'One single order row for the cart.');
        self::assertSame(2, $this->modulePayDispatches, 'The payment module is asked again.');
        self::assertSame(1, $this->beforePaymentDispatches, 'ORDER_BEFORE_PAYMENT is not raised again, so no second confirmation e-mail.');
        self::assertSame($stockBefore - 1.0, $this->stockOf($product), 'The stock moved once, not twice.');
    }

    /**
     * AC5 (a) — same module, but the cart changed in between: the first order is
     * cancelled through the status flow and exactly one new unpaid order exists.
     */
    public function testAC5AChangedCartCancelsThePreviousOrderAndPlacesOneNewOne(): void
    {
        [$cart, $product] = $this->cartReadyToPay(RetryingPaymentModule::getModuleCode());
        $stockBefore = $this->stockOf($product);

        $first = $this->pay($cart);
        $this->changeTheQuantityInTheCart($cart, 2.0);
        $second = $this->pay($cart);

        $this->assertThePreviousOrderWasReplaced($cart, $first, $second);
        self::assertSame(
            $stockBefore - 2.0,
            $this->stockOf($product),
            'The stock of the cancelled order is given back, and the new order takes its own.',
        );
    }

    /**
     * AC5 (b) — a module that does not declare retry support: the unpaid order is
     * replaced even though nothing changed.
     */
    public function testAC5AModuleWithoutRetrySupportCancelsThePreviousOrderAndPlacesOneNewOne(): void
    {
        [$cart] = $this->cartReadyToPay('Cheque');

        $first = $this->pay($cart);
        $second = $this->pay($cart);

        $this->assertThePreviousOrderWasReplaced($cart, $first, $second);
        self::assertSame(2, $this->beforePaymentDispatches, 'A new order is a new placement, confirmation e-mail included.');
    }

    private function assertThePreviousOrderWasReplaced(Cart $cart, Order $first, Order $second): void
    {
        self::assertNotSame($first->getId(), $second->getId(), 'A new order is placed.');

        $orders = $this->ordersOf($cart);
        self::assertCount(2, $orders, 'Exactly two orders exist for the cart.');

        $unpaid = array_filter($orders, static fn (Order $order): bool => $order->isNotPaid());
        self::assertCount(1, $unpaid, 'And only one of them is still waiting for its payment.');

        $previous = OrderQuery::create()->findPk($first->getId(), $this->getPropelConnection());
        self::assertNotNull($previous);
        self::assertTrue($previous->isCancelled(), 'The previous order is cancelled.');

        $cancellations = array_filter(
            $this->statusChanges,
            static fn (array $change): bool => $change[0] === $first->getId() && $change[1] === $previous->getStatusId(),
        );
        self::assertCount(1, $cancellations, 'The cancellation went through ORDER_UPDATE_STATUS, where the status listeners are.');
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

    private function changeTheQuantityInTheCart(Cart $cart, float $quantity): void
    {
        /** @var CartItem $item */
        foreach ($cart->getCartItems() as $item) {
            $item->setQuantity($quantity)->save($this->getPropelConnection());
        }
    }

    private function stockOf(Product $product): float
    {
        return (float) ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne($this->getPropelConnection())
            ?->getQuantity();
    }

    /**
     * @return array{0: Cart, 1: Product}
     */
    private function cartReadyToPay(string $paymentModuleCode): array
    {
        $country = $this->factory->country();
        $customer = $this->factory->customer($this->factory->customerTitle());

        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');
        $paymentModule = ModuleQuery::create()->findOneByCode($paymentModuleCode)
            ?? throw new \RuntimeException("No $paymentModuleCode module installed.");

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

        return [$cart, $product];
    }

    /**
     * The module row the core instantiates the test module from. Written inside the
     * test transaction, so it is gone with it.
     */
    private function registerTheRetryingPaymentModule(): void
    {
        if (null !== ModuleQuery::create()->findOneByCode(RetryingPaymentModule::getModuleCode())) {
            return;
        }

        (new Module())
            ->setCode(RetryingPaymentModule::getModuleCode())
            ->setFullNamespace(RetryingPaymentModule::class)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->save($this->getPropelConnection());
    }

    /**
     * MODULE_PAY is counted and stopped: the module would answer with its own page, and
     * that page is not what these tests are about. ORDER_BEFORE_PAYMENT is counted, since
     * it is what sends the confirmation e-mail. ORDER_UPDATE_STATUS is recorded after
     * the core has written the status, to see what went through the flow.
     */
    private function countTheEventsOfThePlacement(): void
    {
        $this->listen(TheliaEvents::MODULE_PAY, function (OrderPaymentEvent $event): void {
            ++$this->modulePayDispatches;
            $event->stopPropagation();
        });
        $this->listen(TheliaEvents::ORDER_BEFORE_PAYMENT, function (): void {
            ++$this->beforePaymentDispatches;
        });
        $this->listen(TheliaEvents::ORDER_UPDATE_STATUS, function (OrderEvent $event): void {
            $this->statusChanges[] = [(int) $event->getOrder()->getId(), (int) $event->getStatus()];
        }, priority: -1024);
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
