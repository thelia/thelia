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
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\Exception\GiftMessageTooLongException;
use Thelia\Domain\Checkout\Exception\UnknownGiftWrappingException;
use Thelia\Domain\Checkout\Service\CheckoutPlacementService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Checkout\Service\GiftWrappingProvider;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Customer;
use Thelia\Model\GiftWrapping;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * Buying a gift wrapping: what the cart holds, what the order is invoiced for, and what
 * stays readable once the merchant changes their mind about the service.
 *
 * The whole point of invoicing the wrapping as a line of the order is that nothing
 * downstream needs a special case for it. These tests pin that: the line exists, it
 * carries its own frozen wording, it moves the total, and renaming, turning off or
 * deleting the service afterwards leaves the order saying what it said.
 */
final class CheckoutGiftWrappingTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->turnMandatoryConsentsOff();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        $this->getService(ConsentProvider::class)->forgetCache();
        $this->getService(GiftWrappingProvider::class)->forgetCache();

        parent::tearDown();
    }

    /**
     * The reference case: a paid wrapping becomes a line of the order, marked as a
     * service, and the order total is the goods plus the service.
     */
    public function testAPaidWrappingIsInvoicedAsAServiceLineAndRaisesTheTotal(): void
    {
        $wrapping = $this->createGiftWrapping('gift-box', '3.000000', ['en_US' => 'Gift box']);

        [$cart, $customer] = $this->cartReadyToPay();
        $this->cartFacade()->chooseGiftWrapping($cart, (int) $wrapping->getId());

        $orderId = $this->place($cart, $customer);
        $line = $this->serviceLineOf($orderId);

        self::assertInstanceOf(OrderProduct::class, $line, 'The wrapping the buyer picked is invoiced as a line of the order.');
        self::assertSame(OrderProduct::LINE_TYPE_SERVICE, $line->getLineType());
        self::assertSame('Gift box', $line->getTitle());
        self::assertSame('gift-box', $line->getProductRef());
        self::assertSame(1.0, (float) $line->getQuantity());
        self::assertSame(3.0, (float) $line->getPrice());
        self::assertNull($line->getProductSaleElementsId(), 'A service has no sale element behind it.');

        self::assertEqualsWithDelta(
            $this->goodsTotalOf($orderId) + 3.0,
            $this->totalOf($orderId),
            0.01,
            'The order is worth the goods plus the service.',
        );
    }

    /**
     * A wrapping the shop offers still gets its line: the buyer asked for the service and
     * the picking list has to say so. It is charged nothing, so the total does not move.
     */
    public function testAFreeWrappingStillGetsALineAndLeavesTheTotalAlone(): void
    {
        $wrapping = $this->createGiftWrapping('kraft-paper', '0.000000', ['en_US' => 'Kraft paper']);

        [$cart, $customer] = $this->cartReadyToPay();
        $this->cartFacade()->chooseGiftWrapping($cart, (int) $wrapping->getId());
        $orderId = $this->place($cart, $customer);

        $line = $this->serviceLineOf($orderId);

        self::assertInstanceOf(OrderProduct::class, $line);
        self::assertSame(0.0, (float) $line->getPrice());
        self::assertEqualsWithDelta(
            $this->goodsTotalOf($orderId),
            $this->totalOf($orderId),
            0.01,
            'A wrapping the shop offers adds nothing to the bill.',
        );
    }

    /**
     * The whole reason the line copies the wording instead of pointing at the service:
     * a merchant reprices and renames the wrapping the week after, and the invoice
     * already issued keeps saying what the buyer paid for.
     */
    public function testRenamingTheWrappingLeavesThePlacedOrderAsItWas(): void
    {
        $wrapping = $this->createGiftWrapping('gift-box', '3.000000', ['en_US' => 'Gift box']);

        [$cart, $customer] = $this->cartReadyToPay();
        $this->cartFacade()->chooseGiftWrapping($cart, (int) $wrapping->getId());
        $orderId = $this->place($cart, $customer);

        $wrapping
            ->setLocale('en_US')
            ->setTitle('Luxury gift box')
            ->setPrice('9.000000')
            ->save($this->getPropelConnection());

        $line = $this->serviceLineOf($orderId);

        self::assertInstanceOf(OrderProduct::class, $line);
        self::assertSame('Gift box', $line->getTitle());
        self::assertSame(3.0, (float) $line->getPrice());
    }

    /**
     * Deleting the service releases the carts that point at it and leaves the orders
     * alone: the order line is a copy, not a pointer.
     */
    public function testDeletingTheWrappingLeavesThePlacedOrderReadable(): void
    {
        $wrapping = $this->createGiftWrapping('gift-box', '3.000000', ['en_US' => 'Gift box']);

        [$cart, $customer] = $this->cartReadyToPay();
        $this->cartFacade()->chooseGiftWrapping($cart, (int) $wrapping->getId());
        $orderId = $this->place($cart, $customer);

        $wrapping->delete($this->getPropelConnection());

        $line = $this->serviceLineOf($orderId);

        self::assertInstanceOf(OrderProduct::class, $line);
        self::assertSame('Gift box', $line->getTitle());
    }

    /**
     * A wrapping turned off between two visits is dropped rather than charged: the cart
     * still holds its id, and nothing prices or invoices a service that is off sale.
     */
    public function testAWrappingTurnedOffIsNeitherOfferedNorInvoiced(): void
    {
        $wrapping = $this->createGiftWrapping('gift-box', '3.000000', ['en_US' => 'Gift box']);

        [$cart, $customer] = $this->cartReadyToPay();
        $this->cartFacade()->chooseGiftWrapping($cart, (int) $wrapping->getId());

        $wrapping->setActive(0)->save($this->getPropelConnection());
        $this->getService(GiftWrappingProvider::class)->forgetCache();

        self::assertSame([], $this->getService(GiftWrappingProvider::class)->activeGiftWrappings());

        $this->cartFacade()->dropGiftWrappingThatIsNoLongerOffered($cart);
        self::assertNull($cart->getGiftWrappingId());

        $orderId = $this->place($cart, $customer);
        self::assertNull($this->serviceLineOf($orderId), 'Nothing is invoiced for a service that is off sale.');
    }

    /**
     * Only an identifier the shop offers is accepted. Nothing the browser sends carries a
     * price, so this is the one place an unusable choice can be caught.
     */
    public function testAWrappingTheShopDoesNotOfferIsRefused(): void
    {
        [$cart] = $this->cartReadyToPay();

        $this->expectException(UnknownGiftWrappingException::class);

        $this->cartFacade()->chooseGiftWrapping($cart, 999999);
    }

    /**
     * The note is frozen on the order the way every other wording it keeps is.
     */
    public function testTheNoteForTheRecipientIsFrozenOnTheOrder(): void
    {
        $wrapping = $this->createGiftWrapping('gift-box', '3.000000', ['en_US' => 'Gift box']);

        [$cart, $customer] = $this->cartReadyToPay();
        $this->cartFacade()->chooseGiftWrapping($cart, (int) $wrapping->getId());
        $this->cartFacade()->writeGiftMessage($cart, '  Happy birthday, Mum!  ');

        self::assertSame('Happy birthday, Mum!', $cart->getGiftMessage(), 'A note is stored trimmed.');

        $orderId = $this->place($cart, $customer);
        $order = OrderQuery::create()->findPk($orderId, $this->getPropelConnection());

        self::assertSame('Happy birthday, Mum!', $order?->getGiftMessage());
    }

    /**
     * Refused whole rather than cut: the note is printed on the parcel, and half a
     * sentence there is worse than none.
     */
    public function testANoteLongerThanTheShopAcceptsIsRefused(): void
    {
        [$cart] = $this->cartReadyToPay();
        $tooLong = str_repeat('a', GiftWrapping::MAX_GIFT_MESSAGE_LENGTH + 1);

        try {
            $this->cartFacade()->writeGiftMessage($cart, $tooLong);
            self::fail('A note over the limit must be refused.');
        } catch (GiftMessageTooLongException $refusal) {
            self::assertSame(GiftWrapping::MAX_GIFT_MESSAGE_LENGTH, $refusal->maximumLength);
            self::assertSame(GiftWrapping::MAX_GIFT_MESSAGE_LENGTH + 1, $refusal->submittedLength);
        }

        self::assertNull($cart->getGiftMessage(), 'Nothing is stored, and nothing is silently cut.');
    }

    /**
     * A note of accented text is not half as long as the same note without: the limit is
     * counted in characters, which is what the buyer typed, and not in bytes.
     */
    public function testTheNoteLengthIsCountedInCharactersAndNotInBytes(): void
    {
        [$cart] = $this->cartReadyToPay();
        $note = str_repeat('é', GiftWrapping::MAX_GIFT_MESSAGE_LENGTH);

        $this->cartFacade()->writeGiftMessage($cart, $note);

        self::assertSame($note, $cart->getGiftMessage());
    }

    /**
     * A note of nothing but spaces is a note of nothing: stored as null rather than as a
     * blank paragraph printed on the delivery note.
     */
    public function testANoteOfSpacesIsStoredAsNoNoteAtAll(): void
    {
        [$cart] = $this->cartReadyToPay();

        $this->cartFacade()->writeGiftMessage($cart, '   ');

        self::assertNull($cart->getGiftMessage());
    }

    /**
     * With no wrapping active the checkout is the one it was before the feature existed:
     * nothing offered, nothing invoiced, nothing on the order.
     */
    public function testAShopThatOffersNoWrappingPlacesTheOrderItAlwaysDid(): void
    {
        [$cart, $customer] = $this->cartReadyToPay();

        self::assertFalse($this->getService(GiftWrappingProvider::class)->isOffered());

        $orderId = $this->place($cart, $customer);
        $order = OrderQuery::create()->findPk($orderId, $this->getPropelConnection());

        self::assertNull($this->serviceLineOf($orderId));
        self::assertNull($order?->getGiftMessage());
        self::assertSame(
            OrderProduct::LINE_TYPE_PRODUCT,
            $this->goodsLineOf($orderId)?->getLineType(),
            'Every line of such an order reads as a good.',
        );
    }

    // ------------------------------------------------------------------
    // Harness
    // ------------------------------------------------------------------

    /**
     * @param array<string, string> $wordings the title written in each locale
     */
    private function createGiftWrapping(string $code, string $price, array $wordings): GiftWrapping
    {
        $giftWrapping = (new GiftWrapping())
            ->setCode($code)
            ->setPrice($price)
            ->setTaxRuleId((int) $this->factory->taxRule()->getId())
            ->setActive(1);

        foreach ($wordings as $locale => $title) {
            $giftWrapping->setLocale($locale)->setTitle($title);
        }

        $giftWrapping->save($this->getPropelConnection());

        $this->getService(GiftWrappingProvider::class)->forgetCache();

        return $giftWrapping;
    }

    private function cartFacade(): CartFacade
    {
        return $this->getService(CartFacade::class);
    }

    private function place(Cart $cart, Customer $customer): int
    {
        $result = $this->getService(CheckoutPlacementService::class)->place(new CheckoutPlacementRequest(
            $cart,
            $customer,
            $this->factory->currency(),
            $this->factory->lang(),
        ));

        return (int) $result->orderId;
    }

    /**
     * What the goods of that order are worth, read off its own lines rather than off a
     * second order placed for comparison: two carts built from two fixture products are
     * not guaranteed to be worth the same.
     */
    private function goodsTotalOf(int $orderId): float
    {
        $total = 0.0;

        foreach (OrderProductQuery::create()->filterByOrderId($orderId)->find($this->getPropelConnection()) as $line) {
            if (!$line->isProductLine()) {
                continue;
            }

            $total += (float) $line->getQuantity() * (float) $line->getPrice();
        }

        return round($total, 2);
    }

    private function totalOf(int $orderId): float
    {
        $order = OrderQuery::create()->findPk($orderId, $this->getPropelConnection());

        return (float) $order?->getTotalAmount();
    }

    private function serviceLineOf(int $orderId): ?OrderProduct
    {
        return OrderProductQuery::create()
            ->filterByOrderId($orderId)
            ->filterByLineType(OrderProduct::LINE_TYPE_SERVICE)
            ->findOne($this->getPropelConnection());
    }

    private function goodsLineOf(int $orderId): ?OrderProduct
    {
        return OrderProductQuery::create()
            ->filterByOrderId($orderId)
            ->filterByLineType(OrderProduct::LINE_TYPE_PRODUCT)
            ->findOne($this->getPropelConnection());
    }

    /**
     * @return array{0: Cart, 1: Customer}
     */
    private function cartReadyToPay(): array
    {
        $country = $this->factory->country();

        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No Cheque module installed — run bin/test-prepare.');

        $this->serveCountryWith($deliveryModule, $country);
        $this->answerDeliveryQuoteWith();
        $this->answerPaymentValidityWith();

        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->factory->address($customer, $country);

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

    private function serveCountryWith(Module $module, Country $country): void
    {
        $area = (new Area())->setName('Gift wrapping test area '.uniqid());
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($module->getId())->save($this->getPropelConnection());
    }

    private function answerDeliveryQuoteWith(): void
    {
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true);
            $event->setPostage(new OrderPostage(0.0, 0.0, 'VAT'));
            $event->stopPropagation();
        });
    }

    private function answerPaymentValidityWith(): void
    {
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        });
    }

    private function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->dispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }

    /**
     * The shop ships with its terms and conditions mandatory, and nothing here is about
     * consents: an order placed without a browser would otherwise be refused for a box
     * nobody was shown.
     */
    private function turnMandatoryConsentsOff(): void
    {
        foreach (ConsentQuery::create()->filterByActive(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setMandatory(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }
}
