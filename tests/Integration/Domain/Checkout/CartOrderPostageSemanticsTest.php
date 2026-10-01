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
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartItem;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPostage;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * cart.postage and order.postage carry the same name, so they must carry the
 * same meaning: both hold the amount the customer pays, tax included.
 *
 * The amount a delivery module quotes therefore has to reach the placed order
 * untouched. Anything that converts it on the way — even a conversion undone
 * further down — leaves two columns of opposite meaning behind, and every
 * reader gets one chance in two.
 */
final class CartOrderPostageSemanticsTest extends ActionIntegrationTestCase
{
    private const QUOTED_POSTAGE = 12.0;
    private const QUOTED_POSTAGE_TAX = 2.0;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function tearDown(): void
    {
        // The kernel dispatcher outlives the test, so listeners left behind
        // would answer the next test of the process.
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->kernelDispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        parent::tearDown();
    }

    public function testTheCartStoresTheAmountTheDeliveryModuleQuoted(): void
    {
        $cart = $this->createCheckoutReadyCart()['cart'];

        $this->quotePostageOnCart($cart);

        self::assertEqualsWithDelta(self::QUOTED_POSTAGE, (float) $cart->getPostage(), 0.0001);
        self::assertEqualsWithDelta(self::QUOTED_POSTAGE_TAX, (float) $cart->getPostageTax(), 0.0001);
        self::assertEqualsWithDelta(self::QUOTED_POSTAGE, $cart->getTaxedPostage(), 0.0001);
        self::assertEqualsWithDelta(10.0, $cart->getUntaxedPostage(), 0.0001);
    }

    public function testTheCheckoutHandsTheCartPostageToTheOrderUnchanged(): void
    {
        $fixtures = $this->createCheckoutReadyCart();
        $cart = $fixtures['cart'];

        $this->quotePostageOnCart($cart);
        $order = $this->checkout($fixtures);

        self::assertEqualsWithDelta(
            (float) $cart->getPostage(),
            (float) $order->getPostage(),
            0.0001,
            'cart.postage and order.postage must hold the same amount.',
        );
        self::assertEqualsWithDelta(
            $cart->getTaxedPostage(),
            (float) $order->getPostage(),
            0.0001,
        );
        self::assertEqualsWithDelta(
            $cart->getUntaxedPostage(),
            (float) $order->getUntaxedPostage(),
            0.0001,
        );
        self::assertEqualsWithDelta(self::QUOTED_POSTAGE, (float) $order->getPostage(), 0.0001);
        self::assertEqualsWithDelta(self::QUOTED_POSTAGE_TAX, (float) $order->getPostageTax(), 0.0001);
    }

    /**
     * Runs the real CART_SET_POSTAGE listener on a fixed quote.
     *
     * Only the delivery module lookup is replaced: it would need an installed
     * module configured for the test country, which says nothing about the
     * amount the listener then writes.
     */
    private function quotePostageOnCart(Cart $cart): void
    {
        $action = new class extends CartAction {
            public OrderPostage $quote;

            public function __construct()
            {
                // The overridden method below is the only one this test calls,
                // and it uses none of the parent's dependencies.
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
        // The payment module would answer with its own payment page. The
        // postage contract is settled by then, so the chain stops here.
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
            ->setToken(uniqid('postage-semantics-', true))
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
