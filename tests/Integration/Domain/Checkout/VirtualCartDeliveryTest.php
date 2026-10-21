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
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Domain\Checkout\Exception\MissingAddressException;
use Thelia\Domain\Checkout\Service\CheckoutProgressionService;
use Thelia\Domain\Checkout\Service\CheckoutValidationService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Model\Cart;
use Thelia\Model\Consent;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\Customer;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The carrier a cart with nothing to ship still has to carry.
 *
 * The delivery step is left out of the tunnel of such a cart — there is no question to
 * ask the buyer — and the order is nevertheless refused while the cart names no carrier
 * and no address. Something has to settle that on the buyer's behalf, and the rule is
 * the core's rather than a theme's: every theme, the front API and a command line place
 * orders through the same refusal, so they all have to be able to lift it the same way.
 */
final class VirtualCartDeliveryTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->progression()->forget();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        $this->progression()->forget();
        $this->getService(ConsentProvider::class)->forgetCache();

        parent::tearDown();
    }

    /**
     * The point of the whole method: before it, the order is refused for a delivery the
     * buyer was never asked about; after it, it goes through.
     */
    public function testACartWithNothingToShipIsGivenTheDeliveryTheOrderNeeds(): void
    {
        $this->turnMandatoryConsentsOff();
        $cart = $this->virtualCartReadyToPay();

        try {
            $this->validation()->validateForOrder($cart);
            self::fail('A cart naming no carrier cannot be ordered, shippable or not.');
        } catch (MissingAddressException) {
            // That is the refusal the method exists to lift.
        }

        $this->facade()->settleVirtualDeliveryIfNeeded($cart);

        self::assertSame(
            ModuleQuery::create()->findOneByCode('VirtualProductDelivery')?->getId(),
            $cart->getDeliveryModuleId(),
        );

        $this->validation()->validateForOrder($cart);
    }

    /**
     * A cart holding something to ship is the buyer's own business: they choose the
     * carrier on the delivery screen, and nothing here may choose one for them.
     */
    public function testACartWithSomethingToShipIsLeftAlone(): void
    {
        $cart = $this->cartOfACustomerWithAnAddress(virtual: false);

        $this->facade()->settleVirtualDeliveryIfNeeded($cart);

        self::assertNull($cart->getDeliveryModuleId());
        self::assertNull($cart->getAddressDeliveryId());
    }

    /**
     * Called on every page of the tunnel, it settles once and then does nothing: the
     * choice already on the cart is never written over.
     */
    public function testACartWhoseDeliveryIsAlreadySettledIsLeftAlone(): void
    {
        $cart = $this->cartOfACustomerWithAnAddress(virtual: true);

        $this->facade()->settleVirtualDeliveryIfNeeded($cart);
        $settledModule = $cart->getDeliveryModuleId();

        self::assertNotNull($settledModule);
        self::assertNotNull($cart->getAddressDeliveryId());

        $cart->setAddressDeliveryId(null)->save($this->getPropelConnection());
        $this->facade()->settleVirtualDeliveryIfNeeded($cart);

        self::assertSame($settledModule, $cart->getDeliveryModuleId());
        self::assertNull(
            $cart->getAddressDeliveryId(),
            'A cart already naming a carrier is settled: nothing is written on it a second time.',
        );
    }

    /**
     * A guest's cart keeps the delivery address it has, an address of the buyer's own, and
     * gets its carrier: the default address of the guest row, another buyer's, is not read.
     */
    public function testAGuestCartKeepsItsDeliveryAddressAndGetsItsCarrier(): void
    {
        [$cart, $guest] = $this->virtualCartOfAGuestWhoseRowDefaultIsAnotherBuyers();
        $ownAddress = $this->factory->address($guest, $this->factory->country(), null, ['address1' => '1 Own Street']);
        $copy = $this->factory->cartAddress($ownAddress, $this->factory->country());
        $cart->setAddressDeliveryId($copy->getId())->save($this->getPropelConnection());

        $this->facade()->settleVirtualDeliveryIfNeeded($cart);

        self::assertSame($this->virtualDeliveryModuleId(), $cart->getDeliveryModuleId());
        self::assertSame($copy->getId(), $cart->getAddressDeliveryId());
        self::assertSame('1 Own Street', $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddress1());
    }

    /**
     * A guest's cart with no delivery address takes a copy of its own billing address, in a
     * row of its own, never the default address of the guest row.
     */
    public function testAGuestCartWithoutADeliveryAddressTakesACopyOfItsBillingAddress(): void
    {
        [$cart] = $this->virtualCartOfAGuestWhoseRowDefaultIsAnotherBuyers();
        $billing = $this->factory->cartAddress(null, $this->factory->country(), null, ['address1' => '2 Billing Street']);
        $cart->setAddressInvoiceId($billing->getId())->save($this->getPropelConnection());

        $this->facade()->settleVirtualDeliveryIfNeeded($cart);

        $delivery = $cart->getCartAddressRelatedByAddressDeliveryId();
        self::assertSame($this->virtualDeliveryModuleId(), $cart->getDeliveryModuleId());
        self::assertNotNull($delivery);
        self::assertNotSame($billing->getId(), $delivery->getId(), 'A row of its own: a later change of the billing address leaves it as it is.');
        self::assertSame('2 Billing Street', $delivery->getAddress1());
        self::assertSame($billing->getId(), $cart->getAddressInvoiceId());
    }

    /**
     * Without any address of this cart, a guest's cart gets its carrier and no address: the
     * order is refused for the address, never placed with another buyer's.
     */
    public function testAGuestCartWithNoAddressOfItsOwnGetsNoneAndIsRefused(): void
    {
        $this->turnMandatoryConsentsOff();
        [$cart] = $this->virtualCartOfAGuestWhoseRowDefaultIsAnotherBuyers();

        $this->facade()->settleVirtualDeliveryIfNeeded($cart);

        self::assertSame($this->virtualDeliveryModuleId(), $cart->getDeliveryModuleId());
        self::assertNull($cart->getAddressDeliveryId());

        $this->expectException(MissingAddressException::class);
        $this->expectExceptionMessage('Delivery address is required');
        $this->validation()->validateForOrder($cart);
    }

    /**
     * Settled before the guest gave a billing address (a front API caller places in that
     * order): the carrier is there, the address is not. Once the billing address is on the
     * cart, settling again gives it a copy of it, and the order goes through.
     */
    public function testAGuestCartSettledBeforeItsBillingAddressGetsItOnceThere(): void
    {
        $this->turnMandatoryConsentsOff();
        $this->answerPaymentValidityWith(valid: true);
        $this->answerDeliveryQuoteWith(valid: true);
        [$cart] = $this->virtualCartOfAGuestWhoseRowDefaultIsAnotherBuyers();
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque') ?? self::fail('No payment module installed.');
        $cart->setPaymentModuleId($paymentModule->getId())->save($this->getPropelConnection());

        $this->facade()->settleVirtualDeliveryIfNeeded($cart);
        self::assertSame($this->virtualDeliveryModuleId(), $cart->getDeliveryModuleId());
        self::assertNull($cart->getAddressDeliveryId(), 'No address of the guest yet.');

        $billing = $this->factory->cartAddress(null, $this->factory->country(), null, ['address1' => '3 Later Street']);
        $cart->setAddressInvoiceId($billing->getId())->save($this->getPropelConnection());
        $this->facade()->settleVirtualDeliveryIfNeeded($cart);

        $delivery = $cart->getCartAddressRelatedByAddressDeliveryId();
        self::assertSame('3 Later Street', $delivery?->getAddress1(), 'The billing address given since is the delivery address.');
        self::assertNotSame($billing->getId(), $delivery?->getId());
        self::assertSame($this->virtualDeliveryModuleId(), $cart->getDeliveryModuleId());

        $this->validation()->validateForOrder($cart);
    }

    /**
     * @return array{0: Cart, 1: Customer}
     */
    private function virtualCartOfAGuestWhoseRowDefaultIsAnotherBuyers(): array
    {
        $guest = $this->factory->guestCustomer($this->factory->customerTitle());
        $this->factory->address($guest, $this->factory->country(), null, ['address1' => '9 Another Buyer Street'])
            ->setIsDefault(1)
            ->save($this->getPropelConnection());

        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );
        $product->setVirtual(1)->save($this->getPropelConnection());

        $cart = $this->factory->cart($guest);
        $this->factory->cartItem($cart, $product);

        return [$cart, $guest];
    }

    private function virtualDeliveryModuleId(): ?int
    {
        return ModuleQuery::create()->findOneByCode('VirtualProductDelivery')?->getId();
    }

    /**
     * A virtual cart with an invoice address and a payment module on it: the delivery is
     * the one thing left, and it is the one thing the buyer is never asked about.
     */
    private function virtualCartReadyToPay(): Cart
    {
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No payment module installed — run bin/test-prepare.');

        $this->answerPaymentValidityWith(valid: true);
        // VirtualProductDelivery quotes the cart of the session rather than the one it
        // is handed, which no integration test has: the carrier answers here instead.
        $this->answerDeliveryQuoteWith(valid: true);

        $cart = $this->cartOfACustomerWithAnAddress(virtual: true);
        $cart
            ->setAddressInvoiceId($this->factory->cartAddress(null, $this->factory->country())->getId())
            ->setPaymentModuleId($paymentModule->getId())
            ->save($this->getPropelConnection());

        return $cart;
    }

    private function cartOfACustomerWithAnAddress(bool $virtual): Cart
    {
        $country = $this->factory->country();
        $customer = $this->customerWithAnAddress($country);

        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );

        if ($virtual) {
            $product->setVirtual(1)->save($this->getPropelConnection());
        }

        $cart = $this->factory->cart($customer);
        $this->factory->cartItem($cart, $product);

        return $cart;
    }

    private function customerWithAnAddress(Country $country): Customer
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->factory->address($customer, $country);

        return $customer;
    }

    private function turnMandatoryConsentsOff(): void
    {
        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            /* @var Consent $consent */
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }

    private function answerPaymentValidityWith(bool $valid): void
    {
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event) use ($valid): void {
            $event->setValidModule($valid);
            $event->stopPropagation();
        });
    }

    private function answerDeliveryQuoteWith(bool $valid): void
    {
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event) use ($valid): void {
            $event->setValidModule($valid);
            if ($valid) {
                $event->setPostage(new OrderPostage(0.0, 0.0, 'VAT'));
            }
            $event->stopPropagation();
        });
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->dispatcher()->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }

    private function facade(): CheckoutFacade
    {
        return $this->getService(CheckoutFacade::class);
    }

    private function validation(): CheckoutValidationService
    {
        return $this->getService(CheckoutValidationService::class);
    }

    private function progression(): CheckoutProgressionService
    {
        return $this->getService(CheckoutProgressionService::class);
    }
}
