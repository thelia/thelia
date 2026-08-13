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
use Thelia\Action\Cart as CartAction;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Shipping\DTO\PostageTaxLine;
use Thelia\Domain\Shipping\Enum\PostageTaxStrategy;
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
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderPostageTax;
use Thelia\Model\OrderPostageTaxQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A cart holding a case at 20 % and a book at 5.5 % pays one postage, and the
 * European rule is that the postage follows the goods. Thelia taxes it under a
 * single rule, which cannot say how that tax splits.
 *
 * The split has to stay opt-in: an upgrade that silently moved the VAT a shop
 * declares would be worse than the missing feature.
 */
final class PostageTaxBreakdownTest extends ActionIntegrationTestCase
{
    /** The postage the delivery module quotes, tax excluded. */
    private const UNTAXED_POSTAGE = 10.0;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->kernelDispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        // ConfigQuery memoizes what it reads in a static cache that outlives
        // the transaction rollback: left alone, the strategy written here would
        // answer the next test of the process while its row is gone.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheDefaultStrategyLeavesTheDeliveryQuoteUntouched(): void
    {
        $fixtures = $this->createMixedRateCart();

        $postage = $this->quotePostage($fixtures);

        self::assertSame([], $postage->getTaxBreakdown());
        self::assertEqualsWithDelta(12.0, $postage->getAmount(), 0.0001);
        self::assertEqualsWithDelta(2.0, $postage->getAmountTax(), 0.0001);
        self::assertSame('VAT 20', $postage->getTaxRuleTitle());
    }

    public function testProRataSpreadsThePostageOverTheRatesOfTheCart(): void
    {
        $fixtures = $this->createMixedRateCart();
        ConfigQuery::write(PostageTaxStrategy::CONFIG_KEY, PostageTaxStrategy::PRO_RATA->value);

        $postage = $this->quotePostage($fixtures);

        // 200.00 at 20 % and 100.00 at 5.5 %, so two thirds of the postage
        // follow the dearer rate. The share of the largest base takes the
        // rounding remainder: 10.00 - 3.33 = 6.67, not 6.66.
        $breakdown = $this->indexByTitle($postage->getTaxBreakdown());

        self::assertSame(['VAT 20', 'VAT 5.5'], array_keys($breakdown));
        self::assertEqualsWithDelta(6.67, $breakdown['VAT 20']->untaxedAmount, 0.0001);
        self::assertEqualsWithDelta(1.33, $breakdown['VAT 20']->amount, 0.0001);
        self::assertEqualsWithDelta(3.33, $breakdown['VAT 5.5']->untaxedAmount, 0.0001);
        self::assertEqualsWithDelta(0.18, $breakdown['VAT 5.5']->amount, 0.0001);

        // The untaxed postage is what the merchant charges, so it does not move;
        // the tax is the derived figure and it does.
        self::assertEqualsWithDelta(self::UNTAXED_POSTAGE, $postage->getUntaxedAmount(), 0.0001);
        self::assertEqualsWithDelta(1.51, $postage->getAmountTax(), 0.0001);
        self::assertEqualsWithDelta(11.51, $postage->getAmount(), 0.0001);
    }

    public function testHighestRateTaxesTheWholePostageAtTheDearestRateOfTheCart(): void
    {
        $fixtures = $this->createMixedRateCart();
        ConfigQuery::write(PostageTaxStrategy::CONFIG_KEY, PostageTaxStrategy::HIGHEST_RATE->value);

        $postage = $this->quotePostage($fixtures);

        $breakdown = $postage->getTaxBreakdown();

        self::assertCount(1, $breakdown);
        self::assertSame('VAT 20', $breakdown[0]->title);
        self::assertEqualsWithDelta(self::UNTAXED_POSTAGE, $breakdown[0]->untaxedAmount, 0.0001);
        self::assertEqualsWithDelta(2.0, $breakdown[0]->amount, 0.0001);
        self::assertEqualsWithDelta(12.0, $postage->getAmount(), 0.0001);
    }

    public function testAFreePostageIsNotSplit(): void
    {
        $fixtures = $this->createMixedRateCart();
        ConfigQuery::write(PostageTaxStrategy::CONFIG_KEY, PostageTaxStrategy::PRO_RATA->value);

        $postage = $this->quotePostage($fixtures, new OrderPostage(0.0, 0.0, null));

        self::assertSame([], $postage->getTaxBreakdown());
        self::assertEqualsWithDelta(0.0, $postage->getAmount(), 0.0001);
        self::assertEqualsWithDelta(0.0, $postage->getAmountTax(), 0.0001);
    }

    public function testAnOrderPlacedUnderTheDefaultStrategyCarriesNoBreakdown(): void
    {
        $fixtures = $this->createMixedRateCart();
        $this->writeQuoteOnCart($fixtures['cart'], 12.0, 2.0);

        $order = $this->checkout($fixtures);

        self::assertSame(0, OrderPostageTaxQuery::create()->filterByOrderId($order->getId())->count());
    }

    public function testTheOrderFreezesTheBreakdownAndItsSumsMatchItsColumns(): void
    {
        $fixtures = $this->createMixedRateCart();
        ConfigQuery::write(PostageTaxStrategy::CONFIG_KEY, PostageTaxStrategy::PRO_RATA->value);
        // What Action\Cart wrote after the split: 10.00 untaxed, 1.51 of tax.
        $this->writeQuoteOnCart($fixtures['cart'], 11.51, 1.51);

        $order = $this->checkout($fixtures);

        $lines = OrderPostageTaxQuery::create()
            ->filterByOrderId($order->getId())
            ->find()
            ->getData();

        self::assertCount(2, $lines);

        $untaxedTotal = 0.0;
        $taxTotal = 0.0;

        /** @var OrderPostageTax $line */
        foreach ($lines as $line) {
            $untaxedTotal += (float) $line->getUntaxedAmount();
            $taxTotal += (float) $line->getAmount();
        }

        // The order columns are what the customer was charged, so the breakdown
        // has to add up to them exactly - to the cent, not to a rounding delta.
        self::assertEqualsWithDelta((float) $order->getPostageTax(), $taxTotal, 0.0001);
        self::assertEqualsWithDelta(
            (float) $order->getPostage(),
            $untaxedTotal + $taxTotal,
            0.0001,
            'sum(untaxed_amount) + sum(amount) must equal order.postage.',
        );

        $breakdown = [];
        foreach ($lines as $line) {
            $breakdown[$line->getTitle()] = $line;
        }

        self::assertEqualsWithDelta(6.67, (float) $breakdown['VAT 20']->getUntaxedAmount(), 0.0001);
        self::assertEqualsWithDelta(1.33, (float) $breakdown['VAT 20']->getAmount(), 0.0001);
        self::assertEqualsWithDelta(3.33, (float) $breakdown['VAT 5.5']->getUntaxedAmount(), 0.0001);
        self::assertEqualsWithDelta(0.18, (float) $breakdown['VAT 5.5']->getAmount(), 0.0001);
    }

    /**
     * Runs the real Action\Cart post-processing on a fixed delivery quote.
     *
     * Only the module's own answer is replaced, by a listener on the event the
     * action dispatches: what is under test is what the action does with it.
     *
     * @param array{cart: Cart, deliveryModule: Module, deliveryAddressId: int, customer: Customer} $fixtures
     */
    private function quotePostage(array $fixtures, ?OrderPostage $quote = null): OrderPostage
    {
        $quote ??= new OrderPostage(12.0, 2.0, 'VAT 20');

        $this->listen(
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
            static function (DeliveryPostageEvent $event) use ($quote): void {
                $event->setPostage($quote);
                $event->setValidModule(true);
                $event->stopPropagation();
            },
            1024,
        );

        $this->getService(SecurityContext::class)->setCustomerUser($fixtures['customer']);

        $quote = \Closure::bind(
            fn (Cart $cart, EventDispatcherInterface $dispatcher, int $moduleId, int $addressId): OrderPostage => $this->getPostageByDeliveryModuleId($cart, $dispatcher, $moduleId, $addressId),
            $this->getService(CartAction::class),
            CartAction::class,
        );

        return $quote(
            $fixtures['cart'],
            $this->dispatcher,
            $fixtures['deliveryModule']->getId(),
            $fixtures['deliveryAddressId'],
        );
    }

    private function writeQuoteOnCart(Cart $cart, float $taxedPostage, float $postageTax): void
    {
        $cart
            ->setPostage((string) $taxedPostage)
            ->setPostageTax((string) $postageTax)
            ->setPostageTaxRuleTitle('VAT 20')
            ->save($this->getPropelConnection());
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
        // The payment module would answer with its own payment page, and the
        // postage is settled by then.
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
     * A cart worth 200.00 at 20 % and 100.00 at 5.5 %, tax excluded.
     *
     * @return array{cart: Cart, customer: Customer, currency: Currency, deliveryModule: Module, paymentModule: Module, deliveryAddressId: int, invoiceAddressId: int}
     */
    private function createMixedRateCart(): array
    {
        $currency = $this->factory->currency();
        $customerTitle = $this->factory->customerTitle();
        $customer = $this->factory->customer($customerTitle);
        $country = $this->factory->country();
        $category = $this->factory->category();

        $standardRate = $this->createTaxRule($country, '20', 'VAT 20');
        $reducedRate = $this->createTaxRule($country, '5.5', 'VAT 5.5');

        $deliveryAddress = $this->createCartAddress($customerTitle->getId(), $country->getId());
        $invoiceAddress = $this->createCartAddress($customerTitle->getId(), $country->getId());

        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed - run bin/test-prepare.');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No payment module installed - run bin/test-prepare.');

        $cart = (new Cart())
            ->setCustomerId($customer->getId())
            ->setCurrencyId($currency->getId())
            ->setToken(uniqid('postage-breakdown-', true))
            ->setAddressDeliveryId($deliveryAddress->getId())
            ->setAddressInvoiceId($invoiceAddress->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($paymentModule->getId());
        $cart->save($this->getPropelConnection());

        $this->addCartItem($cart, $this->factory->product($category, $standardRate, $currency, ['baseQuantity' => 100]), '200.00');
        $this->addCartItem($cart, $this->factory->product($category, $reducedRate, $currency, ['baseQuantity' => 100]), '100.00');

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

    private function addCartItem(Cart $cart, Product $product, string $price): void
    {
        $productSaleElements = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne();
        self::assertNotNull($productSaleElements);

        (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($product->getId())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setQuantity(1)
            ->setPrice($price)
            ->setPromoPrice($price)
            ->setPromo(0)
            ->save($this->getPropelConnection());
    }

    private function createTaxRule(Country $country, string $percent, string $title): TaxRule
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

    /**
     * @param list<PostageTaxLine> $lines
     *
     * @return array<string, PostageTaxLine>
     */
    private function indexByTitle(array $lines): array
    {
        $indexed = [];

        foreach ($lines as $line) {
            $indexed[$line->title] = $line;
        }

        ksort($indexed);

        return $indexed;
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
