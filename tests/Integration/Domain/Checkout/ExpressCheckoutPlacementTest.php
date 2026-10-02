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

use Thelia\Domain\Checkout\DTO\ExpressCheckoutRequest;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutRefusedException;
use Thelia\Domain\Checkout\Service\ExpressCheckoutPlacementService;
use Thelia\Model\AddressQuery;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Delivery\FixedPostageDeliveryModule;
use Thelia\Tests\Support\Payment\ExpressPaymentTestModule;

/**
 * An order paid with a wallet, from the checkout.
 *
 * What is held here is that the wallet costs nothing in safety. The order carries the
 * addresses and the carrier the checkout chose; a total the buyer was not shown creates
 * nothing; and a wallet that confirms twice buys once.
 */
final class ExpressCheckoutPlacementTest extends IntegrationTestCase
{
    private ?string $previousGuestMode = null;

    private ?\Thelia\Model\Customer $owner = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousGuestMode = ConfigQuery::read('guest_checkout_mode');
        ConfigQuery::write('guest_checkout_mode', GuestCheckoutMode::Enabled->value);
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('guest_checkout_mode', (string) $this->previousGuestMode);

        parent::tearDown();
    }

    /**
     * The wallet only pays: the order takes the addresses and the carrier the buyer chose in
     * the checkout, a pickup point included, and nothing the wallet knows about them.
     */
    public function testAWalletPaymentKeepsTheAddressesAndTheCarrierOfTheCheckout(): void
    {
        [$cart, $country] = $this->aCartReadyForAWallet();
        $carrier = $this->chooseTheDeliveryInTheCheckout($cart, $country, 'Lyon');
        $addressesBefore = AddressQuery::create()->count();

        $order = OrderQuery::create()->findPk($this->service()->place($this->requestFor($cart, $country))->orderId);

        self::assertNotNull($order);
        self::assertSame($carrier, (int) $order->getDeliveryModuleId());
        self::assertSame('Lyon', OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId())?->getCity());
        self::assertNotNull($order->getInvoiceOrderAddressId());
        self::assertEqualsWithDelta(FixedPostageDeliveryModule::AMOUNT, (float) $order->getPostage(), 0.0001);
        self::assertSame($addressesBefore, AddressQuery::create()->count(), 'The address book must be left as it was.');
    }

    public function testAWalletPaymentIsRefusedBeforeACarrierIsChosen(): void
    {
        [$cart, $country] = $this->aCartReadyForAWallet();
        $ordersBefore = OrderQuery::create()->count();

        try {
            $this->service()->place($this->requestFor($cart, $country));
            self::fail('An order was placed on a cart with no carrier chosen.');
        } catch (ExpressCheckoutRefusedException $refusal) {
            self::assertStringContainsString('delivery method', $refusal->getMessage());
        }

        self::assertSame($ordersBefore, OrderQuery::create()->count());
    }

    /**
     * The sheet showed a number and the buyer agreed to it. If the shop computes another
     * one, nothing is created at all, not the order, not the payment.
     */
    public function testATotalTheBuyerNeverSawCreatesNothing(): void
    {
        [$cart, $country] = $this->aCartReadyForAWallet();
        $this->chooseTheDeliveryInTheCheckout($cart, $country, 'Lyon');
        $ordersBefore = OrderQuery::create()->count();

        try {
            $this->service()->place($this->requestFor($cart, $country, totalShown: 1.0));
            self::fail('An order was placed for a total the buyer never saw.');
        } catch (ExpressCheckoutRefusedException $refusal) {
            self::assertStringContainsString('never saw', $refusal->getMessage());
        }

        self::assertSame($ordersBefore, OrderQuery::create()->count());
    }

    /**
     * A wallet that confirms twice, a retried request or a double tap, buys once. The
     * guarantee is the checkout's own: the same lock, the same cart fingerprint.
     */
    public function testTwoConfirmationsForTheSameCartBuyOnce(): void
    {
        [$cart, $country] = $this->aCartReadyForAWallet();
        $this->chooseTheDeliveryInTheCheckout($cart, $country, 'Lyon');

        $first = $this->service()->place($this->requestFor($cart, $country));
        $second = $this->service()->place($this->requestFor($cart, $country));

        self::assertSame($first->orderId, $second->orderId);
        self::assertTrue($second->alreadyPlaced, 'The second confirmation reported a fresh placement.');
    }

    /**
     * Identifying the buyer is the checkout's job. A cart that belongs to nobody is refused
     * here rather than half ordered.
     */
    public function testACartThatBelongsToNobodyIsRefused(): void
    {
        [$cart, $country] = $this->aCartReadyForAWallet(ownedBySomebody: false);
        $this->chooseTheDeliveryInTheCheckout($cart, $country, 'Lyon');

        self::assertNull($cart->getCustomerId(), 'This test is only meaningful on a cart nobody owns.');

        $this->expectException(ExpressCheckoutRefusedException::class);
        $this->expectExceptionMessageMatches('/belongs to nobody/');

        $this->service()->place($this->requestFor($cart, $country));
    }

    /**
     * What the checkout writes on the cart before the payment step: both addresses and
     * the carrier.
     */
    private function chooseTheDeliveryInTheCheckout(Cart $cart, Country $country, string $city): int
    {
        $factory = $this->createFixtureFactory();
        $delivery = $factory->cartAddress(null, $country, null, ['city' => $city]);
        $invoice = $factory->cartAddress(null, $country, null, ['city' => $city]);
        $carrier = (int) ModuleQuery::create()->findOneByCode(FixedPostageDeliveryModule::MODULE_CODE)?->getId();

        $cart->setAddressDeliveryId($delivery->getId())
            ->setAddressInvoiceId($invoice->getId())
            ->setDeliveryModuleId($carrier)
            ->save($this->getPropelConnection());

        return $carrier;
    }

    private function requestFor(Cart $cart, Country $country, ?float $totalShown = null): ExpressCheckoutRequest
    {
        $wallet = ModuleQuery::create()->findOneByCode(ExpressPaymentTestModule::MODULE_CODE);
        self::assertNotNull($wallet);

        return new ExpressCheckoutRequest(
            $cart,
            (int) $wallet->getId(),
            $totalShown ?? $this->totalTheSheetWouldShow($cart, $country),
            $this->consentsAcceptedInTheSheet(),
        );
    }

    /**
     * The answers the buyer gave in the checkout. Without them a shop with a mandatory
     * consent refuses the order, which is the behaviour this shop is set up with.
     *
     * @return array<string, bool>
     */
    private function consentsAcceptedInTheSheet(): array
    {
        $answers = [];

        foreach (ConsentQuery::create()->filterByActive(1)->find() as $consent) {
            $answers[(string) $consent->getCode()] = true;
        }

        return $answers;
    }

    /**
     * What a sheet displays: the goods, taxes included, plus the carrier the buyer picked.
     */
    private function totalTheSheetWouldShow(Cart $cart, Country $country): float
    {
        return round((float) $cart->getTaxedAmount($country) + FixedPostageDeliveryModule::AMOUNT, 2);
    }

    /**
     * @return array{0: Cart, 1: Country}
     */
    private function aCartReadyForAWallet(bool $ownedBySomebody = true): array
    {
        $factory = $this->createFixtureFactory();
        $country = $factory->country();
        $this->owner = $ownedBySomebody ? $factory->customer($factory->customerTitle()) : null;
        $cart = $factory->cart($this->owner);
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
            ['baseQuantity' => 100],
        );
        $factory->cartItem($cart, $product);

        $this->registerCarrier($country);
        $this->registerWallet();

        return [$cart, $country];
    }

    private function registerCarrier(Country $country): void
    {
        $module = ModuleQuery::create()->findOneByCode(FixedPostageDeliveryModule::MODULE_CODE);

        if (null === $module) {
            $module = (new Module())
                ->setCode(FixedPostageDeliveryModule::MODULE_CODE)
                ->setFullNamespace(FixedPostageDeliveryModule::class)
                ->setVersion('1.0.0')
                ->setType(BaseModule::DELIVERY_MODULE_TYPE)
                ->setCategory('delivery')
                ->setActivate(BaseModule::IS_ACTIVATED);
            $module->save($this->getPropelConnection());
        }

        static::getContainer()->set('module.'.FixedPostageDeliveryModule::MODULE_CODE, new FixedPostageDeliveryModule());

        $countryArea = CountryAreaQuery::create()->filterByCountryId($country->getId())->findOne();

        self::assertNotNull($countryArea, 'The seed data places this country in no zone at all.');

        $served = AreaDeliveryModuleQuery::create()
            ->filterByAreaId($countryArea->getAreaId())
            ->filterByDeliveryModuleId($module->getId())
            ->findOne();

        if (null === $served) {
            (new AreaDeliveryModule())
                ->setAreaId($countryArea->getAreaId())
                ->setDeliveryModuleId($module->getId())
                ->save($this->getPropelConnection());
        }
    }

    private function registerWallet(): void
    {
        $module = ModuleQuery::create()->findOneByCode(ExpressPaymentTestModule::MODULE_CODE);

        if (null === $module) {
            $module = (new Module())
                ->setCode(ExpressPaymentTestModule::MODULE_CODE)
                ->setFullNamespace(ExpressPaymentTestModule::class)
                ->setVersion('1.0.0')
                ->setType(BaseModule::PAYMENT_MODULE_TYPE)
                ->setCategory('payment')
                ->setActivate(BaseModule::IS_ACTIVATED);
            $module->save($this->getPropelConnection());
        }

        ExpressPaymentTestModule::reset((int) $module->getId());

        static::getContainer()->set('module.'.ExpressPaymentTestModule::MODULE_CODE, new ExpressPaymentTestModule());
    }

    private function service(): ExpressCheckoutPlacementService
    {
        return $this->getService(ExpressCheckoutPlacementService::class);
    }
}
