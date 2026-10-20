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

namespace Thelia\Tests\Integration\Domain\Shipping;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Action\Cart as CartAction;
use Thelia\Action\Coupon as CouponAction;
use Thelia\Api\Bridge\Propel\Event\DeliveryModuleOptionEvent;
use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Domain\Shipping\Enum\PostageTaxStrategy;
use Thelia\Domain\Shipping\ProductRule\ShippingSurchargeMode;
use Thelia\Domain\Shipping\Service\DeliveryOptionsProvider;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\CartItem;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Coupon;
use Thelia\Model\Currency;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderPostageTaxQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The shipping rules of the products applied to what a carrier quotes: the carrier
 * prices the lines it carries, the surcharges come on top.
 *
 * The carrier answers through a listener on the postage event: what is under test
 * is what the shop does with the quote, on the path that writes it on the cart.
 */
final class ProductShippingRulesPostageTest extends ActionIntegrationTestCase
{
    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    /** @var list<float> weights of the carts the carrier was asked to price, read from the session */
    private array $weightsPriced = [];

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->kernelDispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        // ConfigQuery keeps what it reads in a static cache that outlives the rollback.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testACartWithoutRulesPaysWhatTheCarrierQuotes(): void
    {
        $fixtures = $this->cart([['price' => '30.00']]);

        $postage = $this->quote($fixtures);

        self::assertEqualsWithDelta(12.0, $postage->getAmount(), 0.0001);
        self::assertEqualsWithDelta(2.0, $postage->getAmountTax(), 0.0001);
        self::assertSame('VAT 20', $postage->getTaxRuleTitle());
    }

    public function testASurchargePerUnitIsChargedForEveryUnit(): void
    {
        $fixtures = $this->cart([['price' => '30.00', 'quantity' => 2, 'surcharge' => '10', 'mode' => ShippingSurchargeMode::PerUnit]]);

        $postage = $this->quote($fixtures);

        self::assertEqualsWithDelta(10.0 + 20.0, $postage->getUntaxedAmount(), 0.0001);
    }

    public function testASurchargePerLineIsChargedOnceForTheLine(): void
    {
        $fixtures = $this->cart([['price' => '30.00', 'quantity' => 2, 'surcharge' => '10', 'mode' => ShippingSurchargeMode::PerLine]]);

        $postage = $this->quote($fixtures);

        self::assertEqualsWithDelta(10.0 + 10.0, $postage->getUntaxedAmount(), 0.0001);
    }

    public function testACartWhoseEveryProductIsOfferedPaysNoPostage(): void
    {
        $fixtures = $this->cart([['price' => '30.00', 'free' => true]]);

        $postage = $this->quote($fixtures);

        self::assertEqualsWithDelta(0.0, $postage->getAmount(), 0.0001);
        self::assertEqualsWithDelta(0.0, $postage->getAmountTax(), 0.0001);
    }

    public function testTheCarrierPricesAMixedCartWithoutItsOfferedLines(): void
    {
        $fixtures = $this->cart([
            ['price' => '30.00', 'weight' => 2.0],
            ['price' => '50.00', 'weight' => 5.0, 'free' => true],
        ]);

        $postage = $this->quote($fixtures);

        // The carrier still charges for the line it carries...
        self::assertEqualsWithDelta(12.0, $postage->getAmount(), 0.0001);
        // ...and saw only that line, through the cart of the session it reads.
        self::assertSame([2.0], $this->weightsPriced);
    }

    public function testAnOfferedProductCarriesNoSurcharge(): void
    {
        $fixtures = $this->cart([
            ['price' => '30.00'],
            ['price' => '50.00', 'free' => true, 'surcharge' => '10', 'mode' => ShippingSurchargeMode::PerLine],
        ]);

        $postage = $this->quote($fixtures);

        self::assertEqualsWithDelta(10.0, $postage->getUntaxedAmount(), 0.0001);
    }

    public function testAVirtualProductIsNeitherSurchargedNorCountedAsShipped(): void
    {
        $fixtures = $this->cart([
            ['price' => '30.00'],
            ['price' => '9.00', 'weight' => 0.0, 'virtual' => true, 'surcharge' => '10', 'mode' => ShippingSurchargeMode::PerLine],
        ]);

        $postage = $this->quote($fixtures);

        self::assertEqualsWithDelta(10.0, $postage->getUntaxedAmount(), 0.0001);
    }

    public function testTheSurchargeIsConvertedToTheCurrencyOfTheCart(): void
    {
        $fixtures = $this->cart([['price' => '30.00', 'surcharge' => '10', 'mode' => ShippingSurchargeMode::PerLine]]);
        $secondaryCurrency = $this->factory->currency(['code' => 'XTS', 'rate' => 2 * (float) Currency::getDefaultCurrency()->getRate()]);
        $fixtures['cart']->setCurrencyId($secondaryCurrency->getId())->save($this->getPropelConnection());

        $postage = $this->quote($fixtures);

        self::assertEqualsWithDelta(10.0 + 20.0, $postage->getUntaxedAmount(), 0.0001);
    }

    public function testTheOptionsListedToTheBuyerCarryTheSurcharge(): void
    {
        $fixtures = $this->cart([['price' => '30.00', 'quantity' => 3, 'surcharge' => '5', 'mode' => ShippingSurchargeMode::PerUnit]]);
        $this->listen(
            TheliaEvents::MODULE_DELIVERY_GET_OPTIONS,
            static function (DeliveryModuleOptionEvent $event): void {
                $event->appendDeliveryModuleOptions(
                    (new DeliveryModuleOption())->setCode('home')->setValid(true)->setPostage(12.0)->setPostageTax(2.0)->setPostageUntaxed(10.0),
                );
                $event->stopPropagation();
            },
        );
        $country = CartAddressQuery::create()->findPk($fixtures['deliveryAddressId'])?->getCountry();
        self::assertNotNull($country);

        $options = $this->getService(DeliveryOptionsProvider::class)->getOptions($fixtures['deliveryModule'], null, $fixtures['cart'], $country, null);

        self::assertCount(1, $options);
        self::assertEqualsWithDelta(10.0 + 15.0, $options[0]->getPostageUntaxed(), 0.0001, 'The price listed is the price charged afterwards.');
    }

    public function testAFreeShippingCouponLeavesTheSurchargeDue(): void
    {
        $fixtures = $this->cart([['price' => '30.00', 'surcharge' => '10', 'mode' => ShippingSurchargeMode::PerLine]]);
        $couponManager = $this->getService(CouponManager::class);
        $couponManager->invalidateCurrentCoupons();
        $this->session()->setConsumedCoupons([]);
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(MatchForEveryone::class);
        $this->factory->coupon([
            'code' => null,
            'triggerMode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'conditions' => $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions),
            'isRemovingPostage' => true,
        ]);
        $couponManager->invalidateCurrentCoupons();
        $this->getService(SecurityContext::class)->setCustomerUser($fixtures['customer']);
        $this->session()->setSessionCart($fixtures['cart']);

        $this->getService(CouponAction::class)->forceFreePostage(new Event());

        $cart = CartQuery::create()->findPk($fixtures['cart']->getId());
        self::assertNotNull($cart);
        self::assertEqualsWithDelta(10.0, (float) $cart->getPostage() - (float) $cart->getPostageTax(), 0.0001, 'The coupon offers the carrier, not the surcharge.');
    }

    public function testTheSurchargeIsTaxedWithThePostageOnTheOrderAndItsInvoice(): void
    {
        $fixtures = $this->cart([['price' => '30.00', 'surcharge' => '10', 'mode' => ShippingSurchargeMode::PerLine]]);
        $this->writeOnTheCart($fixtures['cart'], $this->quote($fixtures));

        $order = $this->placeOrder($fixtures);

        // The carrier quotes 10.00 + 2.00 of tax, the surcharge adds 10.00 + 2.00.
        self::assertEqualsWithDelta(24.0, (float) $order->getPostage(), 0.0001);
        self::assertEqualsWithDelta(4.0, (float) $order->getPostageTax(), 0.0001);
        self::assertSame('VAT 20', $order->getPostageTaxRuleTitle());

        $invoice = $this->renderInvoice($order);
        self::assertMatchesRegularExpression('/Postage with tax.*?24[.,]00/s', $invoice, 'The invoice prints the postage with its surcharge.');
        self::assertMatchesRegularExpression('/Postage tax amount.*?4[.,]00/s', $invoice);
    }

    public function testTheSurchargeIsSpreadOverTheRatesOfTheCartWithThePostage(): void
    {
        $country = $this->factory->country();
        $fixtures = $this->cart([
            ['price' => '200.00', 'surcharge' => '10', 'mode' => ShippingSurchargeMode::PerLine, 'taxRule' => $this->taxRule($country, '20', 'VAT 20')],
            ['price' => '100.00', 'taxRule' => $this->taxRule($country, '5.5', 'VAT 5.5')],
        ]);
        ConfigQuery::write(PostageTaxStrategy::CONFIG_KEY, PostageTaxStrategy::PRO_RATA->value);
        $this->writeOnTheCart($fixtures['cart'], $this->quote($fixtures));

        $order = $this->placeOrder($fixtures);

        $untaxedTotal = 0.0;
        $taxTotal = 0.0;

        foreach (OrderPostageTaxQuery::create()->filterByOrderId($order->getId())->find() as $line) {
            $untaxedTotal += (float) $line->getUntaxedAmount();
            $taxTotal += (float) $line->getAmount();
        }

        // 10.00 quoted and 10.00 of surcharge, both spread over the two rates.
        self::assertEqualsWithDelta(20.0, $untaxedTotal, 0.0001, 'The surcharge is part of the postage that is spread.');
        self::assertEqualsWithDelta((float) $order->getPostageTax(), $taxTotal, 0.0001);
        self::assertEqualsWithDelta((float) $order->getPostage(), $untaxedTotal + $taxTotal, 0.0001);
    }

    /**
     * A carrier taxing its postage under a rule of its own (CustomDelivery has one)
     * quotes at that rate: the surcharge follows it, not the delivery rule of the shop.
     */
    public function testTheSurchargeIsTaxedAtTheRateOfTheQuoteRatherThanTheDeliveryRuleOfTheShop(): void
    {
        $country = $this->factory->country();
        ConfigQuery::write('taxrule_id_delivery_module', (string) $this->taxRule($country, '5.5', 'VAT 5.5')->getId());
        $fixtures = $this->cart([['price' => '30.00', 'surcharge' => '10', 'mode' => ShippingSurchargeMode::PerLine]]);

        $postage = $this->quote($fixtures);

        self::assertEqualsWithDelta(20.0, $postage->getUntaxedAmount(), 0.0001);
        self::assertEqualsWithDelta(2.0 + 2.0, $postage->getAmountTax(), 0.0001, 'The surcharge is taxed at the 20 % of the quote.');
        self::assertEqualsWithDelta(24.0, $postage->getAmount(), 0.0001);
        self::assertSame('VAT 20', $postage->getTaxRuleTitle());
    }

    /**
     * @param list<array{price: string, quantity?: int, weight?: float, surcharge?: string, mode?: ShippingSurchargeMode, free?: bool, virtual?: bool, taxRule?: TaxRule}> $lines
     *
     * @return array{cart: Cart, deliveryModule: Module, deliveryAddressId: int, invoiceAddressId: int, paymentModule: Module, customer: \Thelia\Model\Customer, currency: Currency}
     */
    private function cart(array $lines): array
    {
        $currency = $this->factory->currency();
        $customerTitle = $this->factory->customerTitle();
        $customer = $this->factory->customer($customerTitle);
        $country = $this->factory->country();
        $category = $this->factory->category();
        $taxRule = $this->factory->taxRule();

        $deliveryAddress = (new CartAddress())
            ->setCustomerTitleId($customerTitle->getId())
            ->setFirstname('Jane')
            ->setLastname('Doe')
            ->setAddress1('1 rue du Port')
            ->setZipcode('44000')
            ->setCity('Nantes')
            ->setCountryId($country->getId());
        $deliveryAddress->save($this->getPropelConnection());
        $invoiceAddress = $deliveryAddress->copy();
        $invoiceAddress->save($this->getPropelConnection());

        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed - run bin/test-prepare.');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No payment module installed - run bin/test-prepare.');

        $cart = (new Cart())
            ->setCustomerId($customer->getId())
            ->setCurrencyId($currency->getId())
            ->setToken(uniqid('shipping-rules-', true))
            ->setAddressDeliveryId($deliveryAddress->getId())
            ->setAddressInvoiceId($invoiceAddress->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($paymentModule->getId());
        $cart->save($this->getPropelConnection());

        foreach ($lines as $line) {
            $product = $this->factory->product($category, $line['taxRule'] ?? $taxRule, $currency, ['baseQuantity' => 100]);
            $product
                ->setVirtual((int) ($line['virtual'] ?? false))
                ->setShippingSurcharge($line['surcharge'] ?? '0')
                ->setShippingSurchargeMode(($line['mode'] ?? ShippingSurchargeMode::PerLine)->value)
                ->setFreeShipping((int) ($line['free'] ?? false))
                ->save($this->getPropelConnection());

            $this->addCartItem($cart, $product, $line['price'], $line['quantity'] ?? 1, $line['weight'] ?? 1.0);
        }

        return [
            'cart' => $cart,
            'customer' => $customer,
            'currency' => $currency,
            'deliveryModule' => $deliveryModule,
            'deliveryAddressId' => $deliveryAddress->getId(),
            'invoiceAddressId' => $invoiceAddress->getId(),
            'paymentModule' => $paymentModule,
        ];
    }

    private function addCartItem(Cart $cart, Product $product, string $price, int $quantity, float $weight): void
    {
        $productSaleElements = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->findOne();
        self::assertNotNull($productSaleElements);
        $productSaleElements->setWeight($weight)->save($this->getPropelConnection());

        (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($product->getId())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setQuantity($quantity)
            ->setPrice($price)
            ->setPromoPrice($price)
            ->setPromo(0)
            ->save($this->getPropelConnection());
    }

    /**
     * The carrier quotes 12.00 with 2.00 of tax, whatever the cart, and records the
     * weight of the cart of the session, which is the one delivery modules read.
     *
     * @param array{cart: Cart, deliveryModule: Module, deliveryAddressId: int, customer: \Thelia\Model\Customer} $fixtures
     */
    private function quote(array $fixtures): OrderPostage
    {
        $this->listen(
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
            function (DeliveryPostageEvent $event): void {
                $this->weightsPriced[] = $this->session()->getSessionCart($this->kernelDispatcher())->getWeight();
                $event->setPostage(new OrderPostage(12.0, 2.0, 'VAT 20'));
                $event->setValidModule(true);
                $event->stopPropagation();
            },
        );

        $this->getService(SecurityContext::class)->setCustomerUser($fixtures['customer']);
        $this->session()->setSessionCart($fixtures['cart']);

        $quote = \Closure::bind(
            fn (Cart $cart, EventDispatcherInterface $dispatcher, int $moduleId, int $addressId): OrderPostage => $this->getPostageByDeliveryModuleId($cart, $dispatcher, $moduleId, $addressId),
            $this->getService(CartAction::class),
            CartAction::class,
        );

        return $quote($fixtures['cart'], $this->dispatcher, $fixtures['deliveryModule']->getId(), $fixtures['deliveryAddressId']);
    }

    /**
     * What Action\Cart writes on the cart once the postage is quoted.
     */
    private function writeOnTheCart(Cart $cart, OrderPostage $postage): void
    {
        $cart
            ->setPostage((string) $postage->getAmount())
            ->setPostageTax((string) $postage->getAmountTax())
            ->setPostageTaxRuleTitle($postage->getTaxRuleTitle())
            ->save($this->getPropelConnection());
    }

    /**
     * @param array{cart: Cart, customer: \Thelia\Model\Customer, currency: Currency, deliveryModule: Module, paymentModule: Module, deliveryAddressId: int, invoiceAddressId: int} $fixtures
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
        // The payment module would answer with its own payment page.
        $this->listen(
            TheliaEvents::MODULE_PAY,
            static function (OrderPaymentEvent $event): void {
                $event->stopPropagation();
            },
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

    private function renderInvoice(Order $order): string
    {
        $pdfTemplate = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate();
        $parser = $this->getService(ParserResolver::class)->getParser($pdfTemplate->getAbsolutePath(), 'invoice');
        $parser->setTemplateDefinition($pdfTemplate, true);

        return $parser->render('invoice', ['order_id' => $order->getId()]);
    }

    private function taxRule(Country $country, string $percent, string $title): TaxRule
    {
        // A non-empty override array forces a rule of its own instead of reusing the seeded one.
        $taxRule = $this->factory->taxRule(['isDefault' => false]);
        $taxRule->setLocale('en_US')->setTitle($title)->save($this->getPropelConnection());

        $tax = $this->factory->tax(['requirements' => ['percent' => $percent], 'title' => $title]);

        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($country->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $taxRule;
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->kernelDispatcher()->addListener($eventName, $listener, 1024);
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
