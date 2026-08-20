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

namespace Thelia\Tests\Integration\Model;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A shop selling by weight or by volume stores a unit price per gram or per
 * millilitre: 0.005678 € for one gram of bulk rice. Rounded to the cent that
 * price becomes 0.01 €, and the 300 g the customer scooped is charged 3.00 €
 * instead of 1.70 € before tax.
 *
 * The `order_rounding_mode` variable says where the cent appears: on the unit
 * price (ROUNDING_MODE_SUM_OF_ROUNDINGS, the historical behaviour and still the
 * default) or on the line total (ROUNDING_MODE_ROUNDING_OF_SUMS).
 *
 * Whichever mode is picked, the cart and the placed order have to agree: a cart
 * shown at 1.80 € that turns into a 3.00 € order is worse than either figure.
 */
final class OrderRoundingModeTest extends ActionIntegrationTestCase
{
    /** One gram of a product priced at 5.678 € the kilogram. */
    private const PRICE_PER_GRAM = '0.005678';

    private const GRAMS = 300;

    private const VAT_PERCENT = '5.5';

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function tearDown(): void
    {
        // ConfigQuery caches in a static array that the transaction rollback
        // cannot reach, so a mode written here would answer the next test.
        ConfigQuery::resetCache();

        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->kernelDispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        parent::tearDown();
    }

    public function testSumOfRoundingsIsTheDefault(): void
    {
        self::assertSame(
            ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS,
            ConfigQuery::getOrderRoundingMode(),
            'A shop that never set the variable must keep the historical rounding.',
        );
        self::assertFalse(ConfigQuery::isRoundingModeRoundingOfSums());
    }

    public function testTheDefaultModeRoundsTheUnitPriceBeforeApplyingTheQuantity(): void
    {
        $fixtures = $this->createCheckoutReadyCart(self::PRICE_PER_GRAM, self::GRAMS);
        $cartItem = $fixtures['cart']->getCartItems()->getFirst();

        // 0.005678 rounds to 0.01, and 0.01 x 300 is 3.00 both before and
        // after tax: the tax is lost in the same rounding.
        self::assertEqualsWithDelta(3.0, $cartItem->getTotalPrice(), 0.0001);
        self::assertEqualsWithDelta(3.0, $cartItem->getTotalTaxedPrice($fixtures['country']), 0.0001);
        self::assertEqualsWithDelta(3.0, $fixtures['cart']->getTaxedAmount($fixtures['country']), 0.0001);
    }

    public function testRoundingOfSumsAppliesTheQuantityBeforeRounding(): void
    {
        ConfigQuery::write('order_rounding_mode', ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS);

        $fixtures = $this->createCheckoutReadyCart(self::PRICE_PER_GRAM, self::GRAMS);
        $cartItem = $fixtures['cart']->getCartItems()->getFirst();

        // 0.005678 x 300 = 1.7034 before tax, and x 1.055 = 1.797087 with it.
        self::assertEqualsWithDelta(1.70, $cartItem->getTotalPrice(), 0.0001);
        self::assertEqualsWithDelta(1.80, $cartItem->getTotalTaxedPrice($fixtures['country']), 0.0001);
        self::assertEqualsWithDelta(1.80, $fixtures['cart']->getTaxedAmount($fixtures['country']), 0.0001);
    }

    public function testAPriceAlreadyExpressedInCentsIsUnaffectedByTheMode(): void
    {
        $withoutTheMode = $this->cartTotals($this->createCheckoutReadyCart('12.34', 3));

        ConfigQuery::write('order_rounding_mode', ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS);

        $withTheMode = $this->cartTotals($this->createCheckoutReadyCart('12.34', 3));

        self::assertEqualsWithDelta(37.02, $withoutTheMode['untaxed'], 0.0001);
        self::assertEqualsWithDelta(39.06, $withoutTheMode['taxed'], 0.0001);
        self::assertSame(
            $withoutTheMode,
            $withTheMode,
            'A shop whose prices are cents must not see a single amount move when it opts in.',
        );
    }

    public function testAPromoPriceFollowsTheMode(): void
    {
        $fixtures = $this->createCheckoutReadyCart(self::PRICE_PER_GRAM, self::GRAMS, promoPrice: '0.004000');
        $cartItem = $fixtures['cart']->getCartItems()->getFirst();

        // Rounded to the cent, 0.004 is nothing at all: the line is free.
        self::assertEqualsWithDelta(0.0, $cartItem->getTotalRealPrice(), 0.0001);

        ConfigQuery::write('order_rounding_mode', ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS);

        self::assertEqualsWithDelta(1.20, $cartItem->getTotalRealPrice(), 0.0001);
        self::assertEqualsWithDelta(1.27, $cartItem->getTotalRealTaxedPrice($fixtures['country']), 0.0001);
    }

    public function testTheOrderChargesWhatTheDefaultModeShowedInTheCart(): void
    {
        $fixtures = $this->createCheckoutReadyCart(self::PRICE_PER_GRAM, self::GRAMS);
        $cartAmount = $fixtures['cart']->getTaxedAmount($fixtures['country']);

        $order = $this->placeOrder($fixtures);

        self::assertEqualsWithDelta(3.0, $order->getTotalAmount(), 0.0001);
        self::assertEqualsWithDelta($cartAmount, $order->getTotalAmount(), 0.0001);
    }

    public function testTheOrderChargesWhatRoundingOfSumsShowedInTheCart(): void
    {
        ConfigQuery::write('order_rounding_mode', ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS);

        $fixtures = $this->createCheckoutReadyCart(self::PRICE_PER_GRAM, self::GRAMS);
        $cartAmount = $fixtures['cart']->getTaxedAmount($fixtures['country']);

        $order = $this->placeOrder($fixtures);

        $tax = 0.0;
        self::assertEqualsWithDelta(1.80, $order->getTotalAmount($tax), 0.0001);
        self::assertEqualsWithDelta(
            $cartAmount,
            $order->getTotalAmount(),
            0.0001,
            'The amount charged must be the amount the cart showed.',
        );
        self::assertEqualsWithDelta(0.10, $tax, 0.0001, 'The tax is the gap between the taxed and untaxed line totals.');
    }

    public function testAnOrderFrozenByThe24UpgradeIgnoresTheMode(): void
    {
        ConfigQuery::write('order_rounding_mode', ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS);

        $order = $this->placeOrder($this->createCheckoutReadyCart(self::PRICE_PER_GRAM, self::GRAMS));

        ConfigQuery::write('last_legacy_rounding_order_id', $order->getId());

        // Pre-2.4 orders were totalled without any rounding at all, and the
        // amount their invoice states cannot be restated afterwards.
        self::assertEqualsWithDelta(1.797087, $order->getTotalAmount(), 0.0001);
    }

    /**
     * @param array{cart: Cart, country: Country} $fixtures
     *
     * @return array{untaxed: float, taxed: float}
     */
    private function cartTotals(array $fixtures): array
    {
        return [
            'untaxed' => $fixtures['cart']->getTotalAmount(country: $fixtures['country']),
            'taxed' => $fixtures['cart']->getTaxedAmount($fixtures['country']),
        ];
    }

    /**
     * @param array{cart: Cart, customer: \Thelia\Model\Customer, currency: \Thelia\Model\Currency, deliveryModule: Module, paymentModule: Module, deliveryAddressId: int, invoiceAddressId: int} $fixtures
     */
    private function placeOrder(array $fixtures): Order
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
        // The payment module would answer with its own payment page; the order
        // and its lines are written by then, so the chain stops here.
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
     * @return array{cart: Cart, country: Country, customer: \Thelia\Model\Customer, currency: \Thelia\Model\Currency, deliveryModule: Module, paymentModule: Module, deliveryAddressId: int, invoiceAddressId: int}
     */
    private function createCheckoutReadyCart(string $price, float $quantity, ?string $promoPrice = null): array
    {
        $currency = $this->factory->currency();
        $customerTitle = $this->factory->customerTitle();
        $customer = $this->factory->customer($customerTitle);
        $country = $this->factory->country();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->createTaxRule($country),
            $currency,
            ['basePrice' => (float) $price, 'baseQuantity' => 100000],
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
            ->setToken(uniqid('rounding-mode-', true))
            ->setAddressDeliveryId($deliveryAddress->getId())
            ->setAddressInvoiceId($invoiceAddress->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($paymentModule->getId())
            // Free delivery, so that the amounts under test are the goods only.
            ->setPostage('0')
            ->setPostageTax('0');
        $cart->save($this->getPropelConnection());

        (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($product->getId())
            ->setProductSaleElementsId($product->getDefaultSaleElements()->getId())
            ->setQuantity($quantity)
            ->setPrice($price)
            ->setPromoPrice($promoPrice ?? $price)
            ->setPromo(null === $promoPrice ? 0 : 1)
            ->save($this->getPropelConnection());

        $cart->reload(true, $this->getPropelConnection());

        return [
            'cart' => $cart,
            'country' => $country,
            'customer' => $customer,
            'currency' => $currency,
            'deliveryModule' => $deliveryModule,
            'paymentModule' => $paymentModule,
            'deliveryAddressId' => $deliveryAddress->getId(),
            'invoiceAddressId' => $invoiceAddress->getId(),
        ];
    }

    private function createTaxRule(Country $country): TaxRule
    {
        // A non-empty override array forces a rule of its own instead of reusing the seeded one.
        $taxRule = $this->factory->taxRule(['isDefault' => false]);
        $tax = $this->factory->tax([
            'requirements' => ['percent' => self::VAT_PERCENT],
            'title' => 'VAT '.self::VAT_PERCENT,
        ]);

        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($country->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $taxRule;
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
