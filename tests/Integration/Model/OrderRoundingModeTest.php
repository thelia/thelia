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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Service\DataAccess\LoopDataAccessService;
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
use Thelia\Model\Currency;
use Thelia\Model\Customer;
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
 * Two properties matter more than either figure. The cart and the placed order
 * have to agree, whichever mode is on — a cart shown at 1.80 € that turns into
 * a 3.00 € order is worse than both. And an order already invoiced must keep
 * its amount when a shop opts in, which is what `last_sum_of_roundings_order_id`
 * is for.
 */
final class OrderRoundingModeTest extends ActionIntegrationTestCase
{
    /** One gram of a product priced 5.678 € the kilogram, taxed at 5.5 %. */
    private const PRICE_PER_GRAM = '0.005678';

    private const GRAMS = 300;

    private const REDUCED_VAT = '5.5';

    private const STANDARD_VAT = '20';

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

    public function testAnUnknownModeValueReadsAsTheHistoricalOne(): void
    {
        ConfigQuery::write('order_rounding_mode', '3');

        self::assertSame(ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS, ConfigQuery::getOrderRoundingMode());

        $fixtures = $this->createCart([$this->bulkLine()]);

        self::assertEqualsWithDelta(3.0, $fixtures['cart']->getTaxedAmount($fixtures['country']), 0.0001);
    }

    public function testTheDefaultModeRoundsTheUnitPriceBeforeApplyingTheQuantity(): void
    {
        $fixtures = $this->createCart([$this->bulkLine()]);
        $cartItem = $fixtures['cart']->getCartItems()->getFirst();

        // 0.005678 rounds to 0.01, and 0.01 x 300 is 3.00 both before and after
        // tax: the tax is lost in the same rounding.
        self::assertEqualsWithDelta(3.0, $cartItem->getTotalPrice(), 0.0001);
        self::assertEqualsWithDelta(3.0, $cartItem->getTotalTaxedPrice($fixtures['country']), 0.0001);
        self::assertEqualsWithDelta(3.0, $fixtures['cart']->getTaxedAmount($fixtures['country']), 0.0001);
    }

    public function testRoundingOfSumsAppliesTheQuantityBeforeRounding(): void
    {
        $this->optIn();

        $fixtures = $this->createCart([$this->bulkLine()]);
        $cartItem = $fixtures['cart']->getCartItems()->getFirst();

        // 0.005678 x 300 = 1.7034 before tax, and x 1.055 = 1.797087 with it.
        self::assertEqualsWithDelta(1.70, $cartItem->getTotalPrice(), 0.0001);
        self::assertEqualsWithDelta(1.80, $cartItem->getTotalTaxedPrice($fixtures['country']), 0.0001);
        self::assertEqualsWithDelta(1.80, $fixtures['cart']->getTaxedAmount($fixtures['country']), 0.0001);
    }

    public function testAPriceWhoseTaxedAmountIsAWholeNumberOfCentsIsUnaffectedByTheMode(): void
    {
        $line = ['price' => '10.00', 'quantity' => 3.0, 'vatPercent' => self::STANDARD_VAT];

        $historical = $this->cartTotals($this->createCart([$line]));

        $this->optIn();

        self::assertSame([30.0, 36.0], $historical);
        self::assertSame(
            $historical,
            $this->cartTotals($this->createCart([$line])),
            'A shop whose taxed prices are whole cents must not see a single amount move.',
        );
    }

    /**
     * The other half of the truth: as soon as a unit amount has a third decimal,
     * the two modes can land one cent apart on a line. Rounding of sums is the
     * side that charges the price times the quantity.
     */
    public function testAPriceWithASubCentTaxShiftsByOneCentPerLine(): void
    {
        // 12.34 x 1.20 = 14.808 for one, 44.424 for three.
        $line = ['price' => '12.34', 'quantity' => 3.0, 'vatPercent' => self::STANDARD_VAT];

        self::assertSame([37.02, 44.43], $this->cartTotals($this->createCart([$line])));

        $this->optIn();

        self::assertSame([37.02, 44.42], $this->cartTotals($this->createCart([$line])));
    }

    public function testAPromoPriceFollowsTheMode(): void
    {
        $fixtures = $this->createCart([$this->bulkLine(promoPrice: '0.004000')]);
        $cartItem = $fixtures['cart']->getCartItems()->getFirst();

        // Rounded to the cent, 0.004 is nothing at all: the line is free.
        self::assertEqualsWithDelta(0.0, $cartItem->getTotalRealPrice(), 0.0001);
        self::assertEqualsWithDelta(0.0, $cartItem->getTotalRealTaxedPrice($fixtures['country']), 0.0001);

        $this->optIn();

        self::assertEqualsWithDelta(1.20, $cartItem->getTotalRealPrice(), 0.0001);
        self::assertEqualsWithDelta(1.27, $cartItem->getTotalRealTaxedPrice($fixtures['country']), 0.0001);
    }

    public function testTheOrderChargesWhatTheDefaultModeShowedInTheCart(): void
    {
        $fixtures = $this->createCart([$this->bulkLine()]);
        $cartAmount = $fixtures['cart']->getTaxedAmount($fixtures['country']);

        $order = $this->placeOrder($fixtures);

        self::assertEqualsWithDelta(3.0, $order->getTotalAmount(), 0.0001);
        self::assertEqualsWithDelta($cartAmount, $order->getTotalAmount(), 0.0001);
    }

    public function testTheOrderChargesWhatRoundingOfSumsShowedInTheCart(): void
    {
        $this->optIn();

        $fixtures = $this->createCart([$this->bulkLine()]);
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

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function roundingModeProvider(): iterable
    {
        yield 'sum of roundings' => [ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS];
        yield 'rounding of sums' => [ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS];
    }

    #[DataProvider('roundingModeProvider')]
    public function testAMixedCartChargesTheSumOfItsLinesWhateverTheMode(int $mode): void
    {
        ConfigQuery::write('order_rounding_mode', $mode);

        $fixtures = $this->createCart([
            $this->bulkLine(),
            ['price' => '10.00', 'quantity' => 3.0, 'vatPercent' => self::STANDARD_VAT],
        ]);
        $roundingOfSums = ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS === $mode;

        self::assertEqualsWithDelta(
            $roundingOfSums ? 37.80 : 39.00,
            $fixtures['cart']->getTaxedAmount($fixtures['country']),
            0.0001,
        );

        $order = $this->placeOrder($fixtures);

        self::assertEqualsWithDelta(
            $fixtures['cart']->getTaxedAmount($fixtures['country']),
            $order->getTotalAmount(),
            0.0001,
            'Each line keeps its own tax rate, and the order totals the same lines as the cart.',
        );
    }

    #[DataProvider('roundingModeProvider')]
    public function testADiscountIsSubtractedFromTheRoundedLinesWhateverTheMode(int $mode): void
    {
        ConfigQuery::write('order_rounding_mode', $mode);

        $fixtures = $this->createCart(
            [
                $this->bulkLine(),
                ['price' => '10.00', 'quantity' => 3.0, 'vatPercent' => self::STANDARD_VAT],
            ],
            ['discount' => '5.00'],
        );
        $goods = $mode === ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS ? 37.80 : 39.00;

        self::assertEqualsWithDelta($goods - 5.00, $fixtures['cart']->getTaxedAmount($fixtures['country']), 0.0001);

        $order = $this->placeOrder($fixtures);

        self::assertEqualsWithDelta($goods - 5.00, $order->getTotalAmount(), 0.0001);
    }

    #[DataProvider('roundingModeProvider')]
    public function testPostageIsAddedOnTopOfTheRoundedLinesWhateverTheMode(int $mode): void
    {
        ConfigQuery::write('order_rounding_mode', $mode);

        $fixtures = $this->createCart(
            [$this->bulkLine()],
            ['postage' => '4.80', 'postageTax' => '0.80'],
        );
        $goods = $mode === ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS ? 1.80 : 3.00;

        self::assertEqualsWithDelta(
            $goods + 4.80,
            $fixtures['cart']->getTaxedAmount($fixtures['country'], withPostage: true),
            0.0001,
        );

        $order = $this->placeOrder($fixtures);

        $tax = 0.0;
        self::assertEqualsWithDelta($goods + 4.80, $order->getTotalAmount(), 0.0001);
        self::assertEqualsWithDelta($goods, $order->getTotalAmount($tax, false), 0.0001);
    }

    /**
     * The invoice, the delivery note and the order emails read the order_product
     * loop, and they print the three totals of a line side by side. Rounding each
     * of them on its own leaves them disagreeing by a cent, so the tax of a line
     * is the gap between its taxed and its untaxed total, the way
     * Order::getTotalAmount() reads the tax of an order.
     */
    #[DataProvider('roundingModeProvider')]
    public function testALineOfTheOrderProductLoopAddsUpWhateverTheMode(int $mode): void
    {
        ConfigQuery::write('order_rounding_mode', $mode);

        $order = $this->placeOrder($this->createCart([
            $this->bulkLine(),
            ['price' => '12.34', 'quantity' => 3.0, 'vatPercent' => self::STANDARD_VAT],
        ]));

        $lines = $this->getService(LoopDataAccessService::class)
            ->theliaLoop('lines', 'order_product', ['order' => $order->getId()]);

        self::assertCount(2, $lines);

        $taxedTotal = 0.0;
        foreach ($lines as $line) {
            self::assertEqualsWithDelta(
                (float) $line['REAL_TOTAL_TAXED_PRICE'],
                (float) $line['REAL_TOTAL_PRICE'] + (float) $line['REAL_TOTAL_PRICE_TAX'],
                0.0001,
                'A line total, its tax and its taxed total have to be the same figure read twice.',
            );

            $taxedTotal += (float) $line['REAL_TOTAL_TAXED_PRICE'];
        }

        self::assertEqualsWithDelta(
            $order->getTotalAmount(),
            $taxedTotal,
            0.0001,
            'The lines of the invoice have to add up to the amount charged.',
        );
    }

    /**
     * The reason the pivot exists: a shop that opts in must not see the total of
     * an order it has already invoiced move by a single cent.
     */
    public function testAnOrderPlacedBeforeTheSwitchKeepsTheAmountItWasInvoicedWith(): void
    {
        $order = $this->placeOrder($this->createCart([$this->bulkLine()]));
        $amountInvoiced = $order->getTotalAmount();

        ConfigQuery::write('last_sum_of_roundings_order_id', $order->getId());
        $this->optIn();

        self::assertEqualsWithDelta(3.0, $amountInvoiced, 0.0001);
        self::assertEqualsWithDelta($amountInvoiced, $order->getTotalAmount(), 0.0001);
        self::assertSame(
            ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS,
            ConfigQuery::getOrderRoundingMode((int) $order->getId()),
        );

        // A cart is priced with the mode the shop runs today, pivot or not, and
        // the order placed above the pivot charges what the cart showed.
        $newCart = $this->createCart([$this->bulkLine()]);
        self::assertEqualsWithDelta(1.80, $newCart['cart']->getTaxedAmount($newCart['country']), 0.0001);
        self::assertEqualsWithDelta(1.80, $this->placeOrder($newCart)->getTotalAmount(), 0.0001);
    }

    public function testAnOrderFrozenByThe24UpgradeIgnoresTheMode(): void
    {
        $this->optIn();

        $order = $this->placeOrder($this->createCart([$this->bulkLine()]));

        ConfigQuery::write('last_legacy_rounding_order_id', $order->getId());

        // Pre-2.4 orders were totalled without any rounding at all, and the
        // amount their invoice states cannot be restated afterwards.
        self::assertEqualsWithDelta(1.797087, $order->getTotalAmount(), 0.0001);
    }

    private function optIn(): void
    {
        ConfigQuery::write('order_rounding_mode', ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS);
    }

    /**
     * @return array{price: string, quantity: float, vatPercent: string, promoPrice?: string}
     */
    private function bulkLine(?string $promoPrice = null): array
    {
        $line = [
            'price' => self::PRICE_PER_GRAM,
            'quantity' => (float) self::GRAMS,
            'vatPercent' => self::REDUCED_VAT,
        ];

        if (null !== $promoPrice) {
            $line['promoPrice'] = $promoPrice;
        }

        return $line;
    }

    /**
     * @param array{cart: Cart, country: Country} $fixtures
     *
     * @return array{0: float, 1: float} the untaxed then the taxed cart amount
     */
    private function cartTotals(array $fixtures): array
    {
        return [
            $fixtures['cart']->getTotalAmount(country: $fixtures['country']),
            $fixtures['cart']->getTaxedAmount($fixtures['country']),
        ];
    }

    /**
     * @param array{cart: Cart, customer: Customer, currency: Currency, deliveryModule: Module, paymentModule: Module, deliveryAddressId: int, invoiceAddressId: int} $fixtures
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
     * @param list<array{price: string, quantity: float, vatPercent: string, promoPrice?: string}> $lines
     * @param array{discount?: string, postage?: string, postageTax?: string}                      $cartOverrides
     *
     * @return array{cart: Cart, country: Country, customer: Customer, currency: Currency, deliveryModule: Module, paymentModule: Module, deliveryAddressId: int, invoiceAddressId: int}
     */
    private function createCart(array $lines, array $cartOverrides = []): array
    {
        $currency = $this->factory->currency();
        $customerTitle = $this->factory->customerTitle();
        $customer = $this->factory->customer($customerTitle);
        $country = $this->factory->country();

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
            // Free delivery unless the test quotes one, so that the amounts
            // under test are the goods only.
            ->setPostage($cartOverrides['postage'] ?? '0')
            ->setPostageTax($cartOverrides['postageTax'] ?? '0')
            ->setDiscount($cartOverrides['discount'] ?? '0');
        $cart->save($this->getPropelConnection());

        foreach ($lines as $line) {
            $product = $this->factory->product(
                $this->factory->category(),
                $this->createTaxRule($country, $line['vatPercent']),
                $currency,
                ['basePrice' => (float) $line['price'], 'baseQuantity' => 100000],
            );

            (new CartItem())
                ->setCartId($cart->getId())
                ->setProductId($product->getId())
                ->setProductSaleElementsId($product->getDefaultSaleElements()->getId())
                ->setQuantity($line['quantity'])
                ->setPrice($line['price'])
                ->setPromoPrice($line['promoPrice'] ?? $line['price'])
                ->setPromo(isset($line['promoPrice']) ? 1 : 0)
                ->save($this->getPropelConnection());
        }

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

    private function createTaxRule(Country $country, string $percent): TaxRule
    {
        // A non-empty override array forces a rule of its own instead of reusing the seeded one.
        $taxRule = $this->factory->taxRule(['isDefault' => false]);
        $tax = $this->factory->tax([
            'requirements' => ['percent' => $percent],
            'title' => 'VAT '.$percent,
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
