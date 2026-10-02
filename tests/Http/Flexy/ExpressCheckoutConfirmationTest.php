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

namespace Thelia\Tests\Http\Flexy;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Domain\Checkout\DTO\ExpressWalletAnswer;
use Thelia\Domain\Checkout\Enum\CheckoutDisplayMode;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Domain\Checkout\Service\ExpressCheckoutConfirmationToken;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;
use Thelia\Module\BaseModule;
use Thelia\Tests\Support\Delivery\FixedPostageDeliveryModule;
use Thelia\Tests\Support\Payment\ExpressPaymentTestModule;

/**
 * The shop's express routes, driven the way a wallet script drives them: a real session in
 * which the checkout identified the buyer and chose the delivery, a POST with the token
 * that came with the button.
 *
 * What is held here is what would otherwise be each module's to get right. The order hangs
 * off the cart the buyer filled and carries what the checkout chose; a request that did not
 * come from a page served for this cart is not read at all; a session that holds nobody is
 * refused; and a refusal, whoever makes it, leaves nothing behind.
 */
final class ExpressCheckoutConfirmationTest extends GuestCheckoutTestCase
{
    private Country $country;

    private int $walletModuleId;

    private ?string $previousDisplayMode = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDisplayMode = ConfigQuery::read('checkout_display_mode');

        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);

        $this->country = $this->fixtures()->country();
        $this->registerCarrier($this->country);
        $this->walletModuleId = $this->registerWallet();
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('checkout_display_mode', (string) $this->previousDisplayMode);

        parent::tearDown();
    }

    /**
     * A guest identified by the checkout pays with a wallet. The order hangs off the cart
     * they filled, carries the carrier they chose, and the buyer lands on the confirmation
     * page of the shop, which recognises their order.
     */
    public function testAGuestIdentifiedByTheCheckoutPaysWithAWallet(): void
    {
        $cart = $this->aGuestReadyToPay();
        ExpressPaymentTestModule::$answer = $this->answerFor($cart);

        $this->confirm($this->tokenFor($cart));

        $payload = $this->jsonAnswer(200);
        $order = OrderQuery::create()->findPk($payload['orderId']);

        self::assertNotNull($order, 'The confirmation must place an order.');
        self::assertSame($cart->getId(), ExpressPaymentTestModule::$cartIdSeenAtConfirmation, 'The module must be handed the cart of the session.');
        self::assertNotNull(CartQuery::create()->findPk($order->getCartId()), 'The cart the order was placed from must still exist.');
        self::assertSame((int) ModuleQuery::create()->findOneByCode(FixedPostageDeliveryModule::MODULE_CODE)?->getId(), (int) $order->getDeliveryModuleId());

        $buyer = CustomerQuery::create()->findPk($order->getCustomerId());
        self::assertNotNull($buyer);
        self::assertTrue($buyer->isGuest());
        self::assertSame(self::GUEST_EMAIL, $buyer->getEmail());

        self::assertSame('/checkout/confirm?order_id='.$order->getId(), $payload['nextUrl']);

        $this->forgetHydratedModels();
        $this->client->request('GET', $payload['nextUrl']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'The guest must be shown the confirmation page, not sent to sign in.');

        // The page recognises the guest order of this session: it offers the tracking link,
        // which only an order placed here can carry.
        $trackingPath = $this->getService(RouterInterface::class)->generate('guest_order_track', ['token' => 'TOKEN']);
        self::assertStringContainsString(
            str_replace('TOKEN', '', $trackingPath),
            (string) $this->client->getResponse()->getContent(),
            'The confirmation page must offer the guest the link to follow this order.',
        );
    }

    /**
     * The guest is in the session to carry that one order. Once it is paid they leave it,
     * as they do after the ordinary checkout: left behind, the next checkout on this browser
     * takes them for a guest who already identified, and hides every address they type.
     */
    public function testAGuestWhoPaidWithAWalletIsNotLeftInTheSessionForTheNextCheckout(): void
    {
        $cart = $this->aGuestReadyToPay();
        ExpressPaymentTestModule::$answer = $this->answerFor($cart);
        ExpressPaymentTestModule::$paysOnTheSpot = true;

        $this->confirm($this->tokenFor($cart));
        $payload = $this->jsonAnswer(200);
        self::assertTrue($payload['paid'], 'This test is only meaningful for a wallet that takes the money on the spot.');

        $this->forgetHydratedModels();
        $this->client->request('GET', $payload['nextUrl']);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // The replacement cart is only written once a page asks for it.
        $this->client->request('GET', '/checkout/cart');

        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
        $nextCart = CartQuery::create()->filterById($cart->getId(), Criteria::GREATER_THAN)->orderById(Criteria::DESC)->findOne();

        self::assertNotNull($nextCart, 'The paid cart must have been replaced.');
        self::assertNull($nextCart->getCustomerId(), 'The cart handed back after the payment must belong to nobody.');

        // The same browser comes back for another order, through the ordinary checkout.
        $fixtures = $this->fixtures();
        $fixtures->cartItem($nextCart, $fixtures->product(
            $fixtures->category(),
            $fixtures->taxRule(),
            $fixtures->currency(),
            ['title' => 'A product for the next order'],
        ));
        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
        $this->forgetHydratedModels();

        $this->client->request('GET', '/checkout/delivery');

        $this->assertResponseRedirectsTo('/checkout/identify');
    }

    public function testASignedInBuyerPaysOnTheirOwnAccount(): void
    {
        $this->openASessionWithACart();
        $account = $this->signInAsARealAccount();
        $cart = $this->cartOf((int) $account->getId());
        $this->prepareTheCheckoutOf($cart);
        ExpressPaymentTestModule::$answer = $this->answerFor($cart);

        $this->confirm($this->tokenFor($cart));

        $order = OrderQuery::create()->findPk($this->jsonAnswer(200)['orderId']);

        self::assertNotNull($order);
        self::assertSame($account->getId(), $order->getCustomerId());
    }

    /**
     * The checkout identifies the buyer before it shows a wallet's button: a session that
     * holds nobody did not come through it, and nobody is opened for it.
     */
    public function testAWalletPaymentIsRefusedForABuyerNobodyIdentified(): void
    {
        $cart = $this->openASessionWithACart();
        $this->prepareTheCheckoutOf($cart);
        ExpressPaymentTestModule::$answer = $this->answerFor($cart);
        $ordersBefore = OrderQuery::create()->count();

        $this->confirm($this->tokenFor($cart));

        self::assertStringContainsString('has to be identified before paying', $this->jsonAnswer(422)['error']);
        self::assertSame($ordersBefore, OrderQuery::create()->count());
    }

    /**
     * The payment step of the checkout in steps lists the wallets with the other methods.
     */
    public function testThePaymentMethodsOfTheCheckoutInStepsOfferAnExpressOnlyModule(): void
    {
        ConfigQuery::write('checkout_display_mode', CheckoutDisplayMode::Steps->value);
        $this->openASessionWithACart();

        self::assertContains(ExpressPaymentTestModule::MODULE_CODE, $this->paymentMethodCodes());
    }

    public function testThePaymentMethodsOfTheOnePageCheckoutOfferAnExpressOnlyModule(): void
    {
        ConfigQuery::write('checkout_display_mode', CheckoutDisplayMode::OnePage->value);
        $this->openASessionWithACart();

        self::assertContains(ExpressPaymentTestModule::MODULE_CODE, $this->paymentMethodCodes());
    }

    /**
     * A token is only worth something for the cart it was issued for: a page on another
     * site, or a button rendered for another cart, cannot order this one.
     */
    public function testATokenIssuedForAnotherCartIsNotRead(): void
    {
        $cart = $this->aGuestReadyToPay();
        ExpressPaymentTestModule::$answer = $this->answerFor($cart);
        $ordersBefore = OrderQuery::create()->count();

        $this->confirm($this->tokenFor($this->fixtures()->cart()));

        $this->jsonAnswer(403);
        self::assertNull(ExpressPaymentTestModule::$cartIdSeenAtConfirmation, 'The module must not even be asked.');
        self::assertSame($ordersBefore, OrderQuery::create()->count());
    }

    public function testAConfirmationWithoutATokenIsNotRead(): void
    {
        $cart = $this->aGuestReadyToPay();
        ExpressPaymentTestModule::$answer = $this->answerFor($cart);

        $this->confirm('');

        $this->jsonAnswer(403);
    }

    public function testAModuleThatOffersNoExpressPaymentIsNotReachable(): void
    {
        $cart = $this->openASessionWithACart();

        $this->client->request('POST', '/checkout/express/Cheque/confirm', server: [
            'HTTP_X_EXPRESS_CONFIRMATION_TOKEN' => $this->tokenFor($cart),
        ]);

        $this->jsonAnswer(403);
    }

    public function testAPaymentTheProviderDoesNotVouchForLeavesNothingBehind(): void
    {
        $cart = $this->aGuestReadyToPay();
        ExpressPaymentTestModule::$answer = null;
        $ordersBefore = OrderQuery::create()->count();

        $this->confirm($this->tokenFor($cart));

        $this->jsonAnswer(422);
        self::assertSame($ordersBefore, OrderQuery::create()->count());
    }

    public function testAProductOutOfStockIsAConflictNotACrash(): void
    {
        $cart = $this->aGuestReadyToPay();
        ExpressPaymentTestModule::$answer = $this->answerFor($cart);

        foreach (CartQuery::create()->findPk($cart->getId())?->getCartItems() ?? [] as $item) {
            $item->getProductSaleElements()?->setQuantity(0)->save($this->getPropelConnection());
        }

        $this->confirm($this->tokenFor($cart));

        $this->jsonAnswer(409);
    }

    /**
     * The module asks what the sheet will charge every time the carrier or the cart
     * changes. Nothing until a carrier is chosen, then the goods for the chosen address plus
     * its postage, the sum the placement checks the wallet's total against.
     */
    public function testTheAmountOfTheCheckoutWaitsForACarrierThenNamesTheTotal(): void
    {
        $cart = $this->openASessionWithACart();

        $this->amount($this->tokenFor($cart));
        self::assertSame(['deliveryChosen' => false, 'totalTaxIncluded' => null], $this->jsonAnswer(200));

        $this->prepareTheCheckoutOf($cart);

        $this->amount($this->tokenFor($cart));
        $payload = $this->jsonAnswer(200);

        self::assertTrue($payload['deliveryChosen']);
        self::assertEqualsWithDelta($this->answerFor($cart)->totalTaxIncludedShownToTheBuyer, $payload['totalTaxIncluded'], 0.001);
    }

    public function testTheAmountIsNotGivenForAnotherCartsToken(): void
    {
        $this->openASessionWithACart();

        $this->amount($this->tokenFor($this->fixtures()->cart()));

        self::assertArrayNotHasKey('totalTaxIncluded', $this->jsonAnswer(403));
    }

    /**
     * A session with a guest identified through the checkout's own page, a cart that is
     * theirs, and the delivery chosen: what the checkout leaves before its payment step.
     */
    private function aGuestReadyToPay(): Cart
    {
        $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));
        $this->assertResponseRedirectsTo('/checkout/delivery');

        $guest = $this->guestCustomerOf(self::GUEST_EMAIL);
        self::assertNotNull($guest);

        $cart = $this->cartOf((int) $guest->getId());
        $this->prepareTheCheckoutOf($cart);

        return $cart;
    }

    /**
     * What the checkout writes on the cart before its payment step, and enough stock for
     * the order to go through.
     */
    private function prepareTheCheckoutOf(Cart $cart): void
    {
        $fixtures = $this->fixtures();
        $delivery = $fixtures->cartAddress(null, $this->country);
        $invoice = $fixtures->cartAddress(null, $this->country);

        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
        $fresh = CartQuery::create()->findPk($cart->getId());
        self::assertNotNull($fresh);

        foreach ($fresh->getCartItems() as $item) {
            $item->getProductSaleElements()?->setQuantity(100)->save($this->getPropelConnection());
        }

        $fresh->setAddressDeliveryId($delivery->getId())
            ->setAddressInvoiceId($invoice->getId())
            ->setDeliveryModuleId((int) ModuleQuery::create()->findOneByCode(FixedPostageDeliveryModule::MODULE_CODE)?->getId())
            ->setPostage((string) FixedPostageDeliveryModule::AMOUNT)
            ->save($this->getPropelConnection());
        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
    }

    /**
     * What the wallet paid: the goods taxed for the delivery country plus the postage, and
     * the consents the buyer gave.
     */
    private function answerFor(Cart $cart): ExpressWalletAnswer
    {
        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
        $fresh = CartQuery::create()->findPk($cart->getId());
        self::assertNotNull($fresh);

        $consents = [];
        foreach (ConsentQuery::create()->filterByActive(1)->find() as $consent) {
            $consents[(string) $consent->getCode()] = true;
        }

        return new ExpressWalletAnswer(
            round((float) $fresh->getTaxedAmount($this->country) + FixedPostageDeliveryModule::AMOUNT, 2),
            $consents,
        );
    }

    /**
     * @return list<string>
     */
    private function paymentMethodCodes(): array
    {
        $this->client->request('GET', '/api/front/payment/modules', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());

        return array_column(json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR), 'code');
    }

    private function amount(string $token): void
    {
        $url = $this->getService(RouterInterface::class)->generate('express_checkout_amount', [
            'moduleCode' => ExpressPaymentTestModule::MODULE_CODE,
        ]);

        $this->client->request('POST', $url, server: ['HTTP_X_EXPRESS_CONFIRMATION_TOKEN' => $token]);
    }

    private function confirm(string $token): void
    {
        $url = $this->getService(RouterInterface::class)->generate('express_checkout_confirm', [
            'moduleCode' => ExpressPaymentTestModule::MODULE_CODE,
        ]);

        $this->client->request('POST', $url, server: ['HTTP_X_EXPRESS_CONFIRMATION_TOKEN' => $token]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonAnswer(int $expectedStatus): array
    {
        $response = $this->client->getResponse();

        self::assertSame($expectedStatus, $response->getStatusCode(), (string) $response->getContent());

        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        return $payload;
    }

    private function tokenFor(Cart $cart): string
    {
        return $this->getService(ExpressCheckoutConfirmationToken::class)->issueFor($cart, $this->walletModuleId);
    }

    /**
     * The cart a sign-in left the account with. The login may have replaced the one the
     * visitor filled, which is why it is looked up rather than carried over.
     */
    private function cartOf(int $customerId): Cart
    {
        CartTableMap::clearInstancePool();

        $cart = CartQuery::create()->filterByCustomerId($customerId)->orderById(Criteria::DESC)->findOne();

        self::assertNotNull($cart, 'The sign-in must leave the account with the cart.');

        return $cart;
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

        if (null === AreaDeliveryModuleQuery::create()->filterByAreaId($countryArea->getAreaId())->filterByDeliveryModuleId($module->getId())->findOne()) {
            (new AreaDeliveryModule())
                ->setAreaId($countryArea->getAreaId())
                ->setDeliveryModuleId($module->getId())
                ->save($this->getPropelConnection());
        }
    }

    private function registerWallet(): int
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

        // Given the dispatcher of the kernel the requests run in: a wallet that takes the
        // money on the spot raises the status change through it.
        $wallet = new ExpressPaymentTestModule();
        $wallet->setDispatcher($this->getService('event_dispatcher'));
        static::getContainer()->set('module.'.ExpressPaymentTestModule::MODULE_CODE, $wallet);

        return (int) $module->getId();
    }
}
