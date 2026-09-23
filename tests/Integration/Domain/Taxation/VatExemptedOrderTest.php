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

namespace Thelia\Tests\Integration\Domain\Taxation;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Action\Cart as CartAction;
use Thelia\Core\Event\Cart\CartCheckoutEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Domain\Taxation\Service\VatExemptionResolver;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A whole order placed under reverse charge, and the same one placed without it.
 *
 * What is being pinned is that the exemption reaches the amounts actually
 * written down, not only the resolver's opinion: no order_product_tax row at
 * all, which is what keeps the totals as invoiced once the buyer's number is
 * revoked, and the attestation frozen on the billing address.
 */
final class VatExemptedOrderTest extends ActionIntegrationTestCase
{
    private const INVOICE_DOCUMENT = 'invoice';

    private const REVERSE_CHARGE_MENTION = 'Reverse charge: VAT due by the customer';

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    /** @var array<string, Country> */
    private array $countries = [];

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->kernelDispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        // Both memoize in static caches that outlive the transaction rollback.
        ConfigQuery::resetCache();
        Country::resetDefaultCountryCache();

        parent::tearDown();
    }

    public function testAVerifiedBuyerAbroadIsInvoicedWithoutAnyTaxLine(): void
    {
        $this->configure(VatExemptionMode::VERIFIED_VAT_NUMBER);
        $order = $this->checkout($this->createCheckoutReadyCart('BE', new \DateTime('-10 days')));

        self::assertSame(
            0,
            $this->taxLinesOf($order),
            'An exempt order must carry no tax line: the totals are read back from those rows.',
        );
        self::assertSame(1, $order->getOrderAddressRelatedByInvoiceOrderAddressId()->getVatExempted());
        self::assertEqualsWithDelta(0.0, (float) $order->getPostageTax(), 0.0001);
        $tax = 0.0;
        self::assertEqualsWithDelta(10.0, (float) $order->getTotalAmount($tax), 0.0001);
        self::assertEqualsWithDelta(0.0, $tax, 0.0001, 'The VAT of an exempt order is zero.');
    }

    /**
     * The invoice has to state the VAT the buyer accounts for himself, and the
     * order carries no tax line to add up: the figure only survives if it was
     * frozen when the order was placed.
     */
    public function testTheVatTheExemptOrderDidNotChargeIsFrozenOnItsInvoiceAddress(): void
    {
        $this->configure(VatExemptionMode::VERIFIED_VAT_NUMBER);
        $order = $this->checkout($this->createCheckoutReadyCart('BE', new \DateTime('-10 days')));

        self::assertEqualsWithDelta(
            2.0,
            (float) $order->getOrderAddressRelatedByInvoiceOrderAddressId()->getVatExemptedAmount(),
            0.0001,
            'A single line of 10.00 taxed at 20 % owes 2.00 of VAT, exempted here.',
        );
    }

    /**
     * What the buyer is owed is a document saying who accounts for the VAT. The
     * mention lives in the PDF template, so this is the only test that walks the
     * whole way: an order placed under reverse charge, the order address that
     * froze it, and the invoice that states it next to the number it rests on.
     */
    public function testTheInvoiceOfAnExemptOrderStatesTheReverseCharge(): void
    {
        $this->skipUnlessThePdfTemplateStatesTheReverseCharge();

        $this->configure(VatExemptionMode::VERIFIED_VAT_NUMBER);
        $order = $this->checkout($this->createCheckoutReadyCart('BE', new \DateTime('-10 days')));

        $invoice = $this->renderInvoice($order);

        self::assertStringContainsString($this->reverseChargeMention(), $invoice);
        self::assertStringContainsString(
            (string) $order->getOrderAddressRelatedByInvoiceOrderAddressId()->getVatNumber(),
            $invoice,
            'The number the exemption rests on belongs on the invoice, facing the one of the shop.',
        );
    }

    public function testTheInvoiceOfATaxedOrderSaysNothingOfTheSort(): void
    {
        $this->skipUnlessThePdfTemplateStatesTheReverseCharge();

        $this->configure(VatExemptionMode::DISABLED);
        $order = $this->checkout($this->createCheckoutReadyCart('BE', new \DateTime('-10 days')));

        self::assertStringNotContainsString($this->reverseChargeMention(), $this->renderInvoice($order));
    }

    public function testATaxedOrderFreezesNoExemptedVat(): void
    {
        $this->configure(VatExemptionMode::DISABLED);
        $order = $this->checkout($this->createCheckoutReadyCart('BE', new \DateTime('-10 days')));

        self::assertNull($order->getOrderAddressRelatedByInvoiceOrderAddressId()->getVatExemptedAmount());
    }

    public function testAnExemptCartIsShownUntaxedBeforeTheOrderIsEvenPlaced(): void
    {
        $this->configure(VatExemptionMode::VERIFIED_VAT_NUMBER);
        $cart = $this->cartWithItems('BE', new \DateTime('-10 days'));
        $country = $this->countryOf('FR');

        self::assertEqualsWithDelta(
            $cart->getTotalAmount(false, $country),
            $cart->getTaxedAmount($country, false),
            0.0001,
            'The buyer must see the untaxed total in the cart, not only on the invoice.',
        );
        self::assertEqualsWithDelta(0.0, (float) $cart->getTotalVAT($country, null, false), 0.0001);
    }

    /**
     * getCalculatedDiscount() has its own route to a tax calculator, separate
     * from the one cart lines use: an exempt cart proves nothing about its
     * discount unless a discount is actually on it.
     */
    public function testAnExemptCartsDiscountIsAlsoShownUntaxed(): void
    {
        $this->configure(VatExemptionMode::VERIFIED_VAT_NUMBER);
        $cart = $this->cartWithItems('BE', new \DateTime('-10 days'));
        $cart->setDiscount('2.00')->save($this->getPropelConnection());
        $country = $this->countryOf('FR');

        self::assertEqualsWithDelta(
            $cart->getTotalAmount(true, $country),
            $cart->getTaxedAmount($country, true),
            0.0001,
            'An exempt cart must show the same total with or without tax even once a discount is deducted: '
            .'the discount itself must not be taxed away by a calculator that forgot the exemption.',
        );
        self::assertEqualsWithDelta(0.0, (float) $cart->getTotalVAT($country, null, true), 0.0001);
    }

    /**
     * calculatePostage() is the only listener that takes VAT off a delivery
     * quote, and it only runs once, on CART_SET_POSTAGE - so a buyer who picks
     * a delivery module before supplying the VAT number that exempts him would
     * keep the postage tax quoted for the wrong buyer, unless changing the
     * invoice address after the fact re-fires the same listener.
     */
    public function testChangingTheInvoiceAddressAfterPostageWasQuotedRecalculatesItsTax(): void
    {
        $this->configure(VatExemptionMode::VERIFIED_VAT_NUMBER);
        $cart = $this->createCheckoutReadyCart('FR', null)['cart'];

        $dispatcher = new EventDispatcher();
        $action = new class($this->getService(VatExemptionResolver::class)) extends CartAction {
            public OrderPostage $quote;

            public function __construct(VatExemptionResolver $vatExemptionResolver)
            {
                $this->vatExemptionResolver = $vatExemptionResolver;
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
        $action->quote = new OrderPostage(12.0, 2.0, 'VAT 20');
        $dispatcher->addListener(TheliaEvents::CART_SET_POSTAGE, $action->calculatePostage(...));

        $action->calculatePostage(new CartCheckoutEvent($cart), TheliaEvents::CART_SET_POSTAGE, $dispatcher);
        $cart->reload();
        self::assertEqualsWithDelta(
            2.0,
            (float) $cart->getPostageTax(),
            0.0001,
            'Control: the initial quote must actually carry a postage tax, or the test proves nothing.',
        );

        $exemptingAddress = $this->createCartAddress(
            $this->factory->customerTitle()->getId(),
            $this->countryOf('BE')->getId(),
            'BE0123456789',
            new \DateTime('-10 days'),
        );
        $event = (new CartCheckoutEvent($cart))->setCartAddress($exemptingAddress);
        $action->setInvoiceAddressManual($event, TheliaEvents::CART_SET_INVOICE_ADDRESS_MANUAL, $dispatcher);
        $cart->reload();

        self::assertEqualsWithDelta(
            0.0,
            (float) $cart->getPostageTax(),
            0.0001,
            'The postage tax must be recalculated once the new invoice address qualifies for exemption.',
        );
    }

    public function testTheSameCartIsShownTaxedWhenTheSettingIsOff(): void
    {
        $this->configure(VatExemptionMode::DISABLED);
        $cart = $this->cartWithItems('BE', new \DateTime('-10 days'));
        $country = $this->countryOf('FR');

        self::assertGreaterThan(
            0.0,
            (float) $cart->getTotalVAT($country, null, false),
            'The control cart must actually carry VAT, or it proves nothing.',
        );
    }

    public function testTheSameOrderKeepsItsTaxWhenTheSettingIsOff(): void
    {
        $this->configure(VatExemptionMode::DISABLED);
        $order = $this->checkout($this->createCheckoutReadyCart('BE', new \DateTime('-10 days')));

        self::assertGreaterThan(
            0,
            $this->taxLinesOf($order),
            'Without the setting the very same order must be taxed as before.',
        );
        self::assertSame(0, $order->getOrderAddressRelatedByInvoiceOrderAddressId()->getVatExempted());
        $tax = 0.0;
        $order->getTotalAmount($tax);
        self::assertGreaterThan(0.0, $tax, 'The control order must actually carry VAT, or it proves nothing.');
    }

    /**
     * The cart is built before its lines are, and it caches the empty collection
     * it was saved with: reading totals off it without reloading compares zero
     * to zero and proves nothing.
     */
    private function cartWithItems(string $billingCountryCode, ?\DateTime $verifiedAt): Cart
    {
        $cart = $this->createCheckoutReadyCart($billingCountryCode, $verifiedAt)['cart'];
        $cart->clearCartItems();

        self::assertCount(1, $cart->getCartItems());

        return $cart;
    }

    private function taxLinesOf(Order $order): int
    {
        $orderProductIds = OrderProductQuery::create()
            ->filterByOrderId($order->getId())
            ->select('Id')
            ->find($this->getPropelConnection())
            ->getData();

        return OrderProductTaxQuery::create()
            ->filterByOrderProductId($orderProductIds)
            ->count($this->getPropelConnection());
    }

    private function configure(VatExemptionMode $mode): void
    {
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, $mode->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('store_country', (string) $this->countryOf('FR')->getId());
    }

    /**
     * @return array{cart: Cart, customer: \Thelia\Model\Customer, currency: \Thelia\Model\Currency, deliveryModule: \Thelia\Model\Module, paymentModule: \Thelia\Model\Module, deliveryAddressId: int, invoiceAddressId: int}
     */
    private function createCheckoutReadyCart(string $billingCountryCode, ?\DateTime $verifiedAt): array
    {
        $currency = $this->factory->currency();
        $customerTitle = $this->factory->customerTitle();
        $customer = $this->factory->customer($customerTitle);
        $shopCountry = $this->countryOf('FR');
        $product = $this->factory->product(
            $this->factory->category(),
            $this->taxRuleTaxingAt($shopCountry, '20'),
            $currency,
            ['baseQuantity' => 100],
        );

        $deliveryAddress = $this->createCartAddress($customerTitle->getId(), $shopCountry->getId(), null, null);
        $invoiceAddress = $this->createCartAddress(
            $customerTitle->getId(),
            $this->countryOf($billingCountryCode)->getId(),
            $billingCountryCode.'0123456789',
            $verifiedAt,
        );

        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed - run bin/test-prepare.');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No payment module installed - run bin/test-prepare.');

        $cart = (new Cart())
            ->setCustomerId($customer->getId())
            ->setCurrencyId($currency->getId())
            ->setToken(uniqid('vat-exemption-', true))
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

    /**
     * A rule that actually taxes in the delivery country: the seeded one carries
     * no tax there, so an order built on it would be untaxed either way and the
     * control would prove nothing.
     */
    private function taxRuleTaxingAt(Country $country, string $percent): \Thelia\Model\TaxRule
    {
        $taxRule = $this->factory->taxRule(['isDefault' => false]);
        $tax = $this->factory->tax(['requirements' => ['percent' => $percent], 'title' => 'VAT '.$percent]);

        (new \Thelia\Model\TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($country->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $taxRule;
    }

    private function createCartAddress(int $titleId, int $countryId, ?string $vatNumber, ?\DateTime $verifiedAt): CartAddress
    {
        $cartAddress = (new CartAddress())
            ->setCustomerTitleId($titleId)
            ->setFirstname('John')
            ->setLastname('Doe')
            ->setAddress1('1 Main Street')
            ->setAddress2('')
            ->setAddress3('')
            ->setZipcode('1000')
            ->setCity('Brussels')
            ->setCountryId($countryId);

        if (null !== $vatNumber) {
            $cartAddress
                ->setCompany('Acme')
                ->setVatNumber($vatNumber)
                ->setVatVerifiedAt($verifiedAt);
        }

        $cartAddress->save($this->getPropelConnection());

        return $cartAddress;
    }

    /**
     * FixtureFactory::country() inserts a new row on every call that carries
     * overrides, so asking twice for "FR" would hand out two different countries
     * and silently compare a tax rule against a country nothing was bound to.
     */
    private function countryOf(string $isoAlpha2): Country
    {
        return $this->countries[$isoAlpha2] ??= $this->factory->country([
            'isocode' => $isoAlpha2,
            'isoalpha2' => $isoAlpha2,
            'isoalpha3' => $isoAlpha2.'X',
        ]);
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

    /**
     * @param array{cart: Cart, customer: \Thelia\Model\Customer, currency: \Thelia\Model\Currency, deliveryModule: \Thelia\Model\Module, paymentModule: \Thelia\Model\Module, deliveryAddressId: int, invoiceAddressId: int} $fixtures
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
        // The payment module would answer with its own payment page; the amounts
        // are settled by then, so the chain stops here.
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
     * The PDF theme is a Composer package of its own, so the core is tested against
     * whichever version is installed: an older one carries no mention to look for.
     */
    private function skipUnlessThePdfTemplateStatesTheReverseCharge(): void
    {
        $invoicePage = $this->pdfTemplate()->getAbsolutePath().\DIRECTORY_SEPARATOR.self::INVOICE_DOCUMENT.'.html.twig';

        if (!file_exists($invoicePage) || !str_contains((string) file_get_contents($invoicePage), self::REVERSE_CHARGE_MENTION)) {
            self::markTestSkipped('The installed PDF template does not state the reverse charge yet.');
        }
    }

    private function renderInvoice(Order $order): string
    {
        $pdfTemplate = $this->pdfTemplate();

        $parser = $this->getService(ParserResolver::class)->getParser($pdfTemplate->getAbsolutePath(), self::INVOICE_DOCUMENT);
        $parser->setTemplateDefinition($pdfTemplate, true);

        return $parser->render(self::INVOICE_DOCUMENT, ['order_id' => $order->getId()]);
    }

    /**
     * Read through the same catalog the template uses, so the assertion holds
     * whatever language the invoice is rendered in.
     */
    private function reverseChargeMention(): string
    {
        return $this->getService('translator')->trans(self::REVERSE_CHARGE_MENTION, [], 'pdf');
    }

    private function pdfTemplate(): \Thelia\Core\Template\TemplateDefinition
    {
        return $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate();
    }
}
