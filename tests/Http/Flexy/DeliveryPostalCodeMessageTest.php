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
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\AreaZipCodeRule;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartAddressTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Tests\Support\Delivery\RegistersDeliveryDateTestCarrier;

/**
 * The delivery step of a guest whose postal code no zone takes: the step names the postal
 * code, instead of only saying that no delivery method is available.
 *
 * The step belongs to the theme, which ships as its own package: a theme older than the
 * message is reported as skipped rather than failed.
 */
final class DeliveryPostalCodeMessageTest extends GuestCheckoutTestCase
{
    use RegistersDeliveryDateTestCarrier;

    /**
     * Written as a string on purpose: `::class` on a missing import resolves silently in
     * the current namespace, and the guard would then skip the whole file for ever.
     */
    private const DELIVERY_STEP_COMPONENT = 'FlexyBundle\\Components\\Organisms\\Delivery\\Base';

    private const PAYMENT_STEP_INVOICE_COMPONENT = 'FlexyBundle\\Components\\Organisms\\Invoice\\Base';

    private const GUEST_ZIP_CODE = '75002';

    protected function setUp(): void
    {
        if (!method_exists(self::DELIVERY_STEP_COMPONENT, 'getPostalCodeWithoutCarrier')) {
            self::markTestSkipped('The installed front-office theme does not name the postal code no carrier delivers to.');
        }

        parent::setUp();
    }

    public function testTheDeliveryStepNamesThePostalCodeNoCarrierDeliversTo(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);

        $crawler = $this->deliveryStepOfAGuest(excluding: '75*');

        self::assertStringContainsString(
            'No carrier delivers to the postal code '.self::GUEST_ZIP_CODE.'.',
            $crawler->text(),
        );
    }

    public function testTheDeliveryStepDoesNotBlameAPostalCodeTheZoneTakes(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);

        // The carrier is eligible but quotes nothing here: the step shows the general
        // message, and must not put the blame on the postal code.
        $crawler = $this->deliveryStepOfAGuest(excluding: '20*', quoting: false);

        self::assertStringNotContainsString('No carrier delivers to the postal code', $crawler->text());
        self::assertStringContainsString('No delivery method is available for this order.', $crawler->text());
    }

    public function testThePostalCodeIsPrintedAsTextNotAsMarkup(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);

        // The snack bar prints its text raw; the code typed by the buyer must not be markup.
        $crawler = $this->deliveryStepOfAGuest(excluding: '<*', zipCode: '<b>20</b>');

        self::assertStringContainsString('No carrier delivers to the postal code <b>20</b>.', $crawler->text());
        self::assertSame(0, $crawler->filter('.SnackBar-message b')->count(), 'The postal code became an element of the page.');
    }

    /**
     * Recette 6: the delivery address edited on the step itself, its new postal code decides
     * the carriers. The cart keeps its own copy of the address, which the step refreshes once
     * the address is saved.
     */
    public function testEditingTheDeliveryAddressOnTheStepFollowsItsNewPostalCode(): void
    {
        [$guest, $address] = $this->guestOnTheStepOfAZoneExcluding('20*');

        $blamed = $this->editOnTheStep($address, static fn (Address $edited) => $edited->setZipcode('20000')->setCity('Ajaccio'));

        self::assertSame('20000', $blamed);
        self::assertSame('20000', $this->deliveryCopyOf($guest)?->getZipcode(), 'The order would leave for the address the buyer no longer reads.');
    }

    /**
     * Another street, the same postal code: the copy follows, the carrier the buyer chose stays.
     */
    public function testEditingTheDeliveryAddressWithinItsPostalCodeKeepsTheCarrier(): void
    {
        [$guest, $address] = $this->guestOnTheStepOfAZoneExcluding('20*');
        $cart = $this->cartOf($guest);
        $carrierId = (int) ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->findOne()?->getId();
        $cart->setDeliveryModuleId($carrierId)->save($this->getPropelConnection());

        $this->editOnTheStep($address, static fn (Address $edited) => $edited->setAddress1('2 avenue des Tests'));

        self::assertSame('2 avenue des Tests', $this->deliveryCopyOf($guest)?->getAddress1());
        CartTableMap::clearInstancePool();
        self::assertSame($carrierId, (int) CartQuery::create()->findPk($cart->getId())?->getDeliveryModuleId(), 'A new street took away the carrier.');
    }

    /**
     * Another city, the same postal code: a carrier may price by city, so the address is
     * chosen again and the carrier asked again.
     */
    public function testEditingTheCityOfTheDeliveryAddressOnTheStepAsksTheCarriersAgain(): void
    {
        [$guest, $address] = $this->guestOnTheStepOfAZoneExcluding('20*');
        $cart = $this->guestWithACarrier($guest);

        $this->editOnTheStep($address, static fn (Address $edited) => $edited->setCity('Paris 2e'));

        self::assertSame('Paris 2e', $this->deliveryCopyOf($guest)?->getCity());
        CartTableMap::clearInstancePool();
        self::assertNull(CartQuery::create()->findPk($cart->getId())?->getDeliveryModuleId(), 'The carrier chosen for the former city stayed.');
    }

    public function testBackOnTheStepACityEditedElsewhereAsksTheCarriersAgain(): void
    {
        [$guest, $address] = $this->guestOnTheStepOfAZoneExcluding('20*');
        $cart = $this->guestWithACarrier($guest);
        $address->setCity('Paris 2e')->save($this->getPropelConnection());

        $this->client->request('GET', '/checkout/delivery');

        self::assertSame('Paris 2e', $this->deliveryCopyOf($guest)?->getCity());
        CartTableMap::clearInstancePool();
        self::assertNull(CartQuery::create()->findPk($cart->getId())?->getDeliveryModuleId(), 'The carrier chosen for the former city stayed.');
    }

    public function testACityEditedOnThePaymentStepSendsBackToTheDeliveryStep(): void
    {
        [, $address] = $this->guestOnTheStepOfAZoneExcluding('20*');
        $this->skipUnlessThePaymentStepFollowsTheDeliveryAddress();

        $response = $this->editOnThePaymentStep($address, static fn (Address $edited) => $edited->setCity('Paris 2e'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/checkout/delivery', $response->getTargetUrl());
    }

    /**
     * A new address saved on the step is not the one the cart is delivered to: the copy stays.
     */
    public function testSavingANewAddressLeavesTheDeliveryCopy(): void
    {
        [$guest, $address] = $this->guestOnTheStepOfAZoneExcluding('20*');
        $new = $address->copy()->setIsDefault(0)->setLabel('New')->setZipcode('20000');

        $this->editOnTheStep(null, static fn () => $new);

        self::assertSame(self::GUEST_ZIP_CODE, $this->deliveryCopyOf($guest)?->getZipcode());
    }

    /**
     * The delivery address edited elsewhere, from the account or another step: back on the
     * delivery step, the cart follows its new postal code.
     */
    public function testBackOnTheStepAnAddressEditedElsewhereDecidesTheCarriers(): void
    {
        [$guest, $address] = $this->guestOnTheStepOfAZoneExcluding('20*');
        $address->setZipcode('20000')->setCity('Ajaccio')->save($this->getPropelConnection());

        $crawler = $this->client->request('GET', '/checkout/delivery');

        self::assertStringContainsString('No carrier delivers to the postal code 20000.', $crawler->text());
        self::assertSame('20000', $this->deliveryCopyOf($guest)?->getZipcode());
    }

    /**
     * Another street edited elsewhere: back on the step, the copy follows and the carrier stays.
     */
    public function testBackOnTheStepAStreetEditedElsewhereKeepsTheCarrier(): void
    {
        [$guest, $address] = $this->guestOnTheStepOfAZoneExcluding('20*');
        $cart = $this->cartOf($guest);
        $carrierId = (int) ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->findOne()?->getId();
        $cart->setDeliveryModuleId($carrierId)->save($this->getPropelConnection());
        $address->setAddress1('2 avenue des Tests')->save($this->getPropelConnection());

        $this->client->request('GET', '/checkout/delivery');

        self::assertSame('2 avenue des Tests', $this->deliveryCopyOf($guest)?->getAddress1());
        CartTableMap::clearInstancePool();
        self::assertSame($carrierId, (int) CartQuery::create()->findPk($cart->getId())?->getDeliveryModuleId(), 'A new street took away the carrier.');
    }

    /**
     * The delivery address edited on the payment step moves elsewhere: the buyer is sent back
     * to the delivery step, which chooses it again and asks its carriers.
     */
    public function testMovingTheDeliveryAddressFromThePaymentStepSendsBackToTheDeliveryStep(): void
    {
        [, $address] = $this->guestOnTheStepOfAZoneExcluding('20*');
        $this->skipUnlessThePaymentStepFollowsTheDeliveryAddress();

        $response = $this->editOnThePaymentStep($address, static fn (Address $edited) => $edited->setZipcode('20000')->setCity('Ajaccio'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/checkout/delivery', $response->getTargetUrl());
    }

    /**
     * Another street on the payment step: the cart's copy follows at once, nothing moves.
     */
    public function testAStreetEditedOnThePaymentStepReachesTheCartAndKeepsTheCarrier(): void
    {
        [$guest, $address] = $this->guestOnTheStepOfAZoneExcluding('20*');
        $this->skipUnlessThePaymentStepFollowsTheDeliveryAddress();
        $cart = $this->cartOf($guest);
        $carrierId = (int) ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->findOne()?->getId();
        $cart->setDeliveryModuleId($carrierId)->save($this->getPropelConnection());

        $response = $this->editOnThePaymentStep($address, static fn (Address $edited) => $edited->setAddress1('2 avenue des Tests'));

        self::assertNull($response);
        self::assertSame('2 avenue des Tests', $this->deliveryCopyOf($guest)?->getAddress1());
        CartTableMap::clearInstancePool();
        self::assertSame($carrierId, (int) CartQuery::create()->findPk($cart->getId())?->getDeliveryModuleId());
    }

    public function testThePaymentStepListensToTheSavedAddress(): void
    {
        $this->skipUnlessThePaymentStepFollowsTheDeliveryAddress();
        $listeners = (new \ReflectionMethod(self::PAYMENT_STEP_INVOICE_COMPONENT, 'addressSaved'))->getAttributes(LiveListener::class);

        self::assertSame([['ADD_NEW_DELIVERY_ADDRESS']], array_map(static fn ($listener): array => $listener->getArguments(), $listeners));
    }

    public function testTheDeliveryStepListensToTheSavedAddress(): void
    {
        $this->skipUnlessTheStepChoosesAnEditedAddressAgain();
        $listeners = (new \ReflectionMethod(self::DELIVERY_STEP_COMPONENT, 'addressSaved'))->getAttributes(LiveListener::class);

        self::assertSame([['ADD_NEW_DELIVERY_ADDRESS']], array_map(static fn ($listener): array => $listener->getArguments(), $listeners));
    }

    /**
     * @return array{0: Customer, 1: Address}
     */
    private function guestOnTheStepOfAZoneExcluding(string $excluded): array
    {
        $this->skipUnlessTheStepChoosesAnEditedAddressAgain();
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->deliveryStepOfAGuest(excluding: $excluded);
        $guest = $this->guestCustomerOf(self::GUEST_EMAIL);
        self::assertNotNull($guest);
        $address = AddressQuery::create()->filterByCustomerId($guest->getId())->findOne();
        self::assertNotNull($address);

        return [$guest, $address];
    }

    /**
     * The step opens the edit form of the address (none for a new one), the address is saved,
     * the form tells the step. Driven outside of a page: the request of the guest's session is
     * provided. Returns the postal code the step then blames.
     *
     * @param callable(?Address): Address $edit
     */
    private function editOnTheStep(?Address $address, callable $edit): ?string
    {
        $requestStack = $this->getService(RequestStack::class);
        $request = Request::create('/checkout/delivery');
        $request->setSession($this->sessionOfTheClient());
        $requestStack->push($request);

        try {
            $step = $this->getService(self::DELIVERY_STEP_COMPONENT);
            $step->mount();
            if (null !== $address) {
                $step->setEditingAddress((int) $address->getId());
            }
            $edit($address)->save($this->getPropelConnection());
            $step->addressSaved();

            return $step->getPostalCodeWithoutCarrier();
        } finally {
            $requestStack->pop();
        }
    }

    /**
     * The buyer is on the payment step, billed where the order ships, as the step leaves
     * them once open; the billing card opens the edit form of the address, the address is
     * saved, the form tells the card. Returns what the card answers.
     *
     * @param callable(Address): Address $edit
     */
    private function editOnThePaymentStep(Address $address, callable $edit): ?Response
    {
        $billingCopy = $this->getService(CartAddressService::class)->getOrCreateCartAddressFromAddress($address);
        $this->cartOf($this->guestCustomerOf(self::GUEST_EMAIL) ?? self::fail('No guest.'))->setAddressInvoiceId($billingCopy->getId())->save($this->getPropelConnection());

        $requestStack = $this->getService(RequestStack::class);
        $request = Request::create('/checkout/payment');
        $request->setSession($this->sessionOfTheClient());
        $requestStack->push($request);

        try {
            $card = $this->getService(self::PAYMENT_STEP_INVOICE_COMPONENT);
            $card->mount();
            $card->setEditingAddress((int) $address->getId());
            $edit($address)->save($this->getPropelConnection());

            return $card->addressSaved();
        } finally {
            $requestStack->pop();
        }
    }

    private function skipUnlessThePaymentStepFollowsTheDeliveryAddress(): void
    {
        if (!method_exists(self::PAYMENT_STEP_INVOICE_COMPONENT, 'addressSaved')) {
            self::markTestSkipped('The installed front-office theme does not follow the delivery address edited on the payment step.');
        }
    }

    private function guestWithACarrier(Customer $guest): Cart
    {
        $cart = $this->cartOf($guest);
        $carrierId = (int) ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->findOne()?->getId();
        $cart->setDeliveryModuleId($carrierId)->save($this->getPropelConnection());

        return $cart;
    }

    private function cartOf(Customer $guest): Cart
    {
        $cart = CartQuery::create()->filterByCustomerId($guest->getId())->orderById(Criteria::DESC)->findOne();
        self::assertNotNull($cart);

        return $cart;
    }

    private function deliveryCopyOf(Customer $guest): ?CartAddress
    {
        CartTableMap::clearInstancePool();
        CartAddressTableMap::clearInstancePool();

        return CartAddressQuery::create()->findPk($this->cartOf($guest)->getAddressDeliveryId());
    }

    private function skipUnlessTheStepChoosesAnEditedAddressAgain(): void
    {
        if (!method_exists(self::DELIVERY_STEP_COMPONENT, 'addressSaved')) {
            self::markTestSkipped('The installed front-office theme does not choose an edited delivery address again.');
        }
    }

    /**
     * The guest identifies, then a zone served by an active carrier takes their country
     * with one exclusion, before the delivery step is displayed.
     */
    private function deliveryStepOfAGuest(string $excluding, string $zipCode = self::GUEST_ZIP_CODE, bool $quoting = true): Crawler
    {
        $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage(), [
            'flexybundle_form_guest_checkout[zipcode]' => $zipCode,
        ]));
        $this->assertResponseRedirectsTo('/checkout/delivery');

        $guest = $this->guestCustomerOf(self::GUEST_EMAIL);
        self::assertNotNull($guest);
        $countryId = AddressQuery::create()->filterByCustomerId($guest->getId())->findOne()?->getCountryId();
        self::assertNotNull($countryId);
        $this->zoneServingTheCountryExcept((int) $countryId, $excluding, $quoting);

        $crawler = $this->client->request('GET', '/checkout/delivery');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return $crawler;
    }

    private function sessionOfTheClient(): SessionInterface
    {
        $session = $this->getService('session.factory')->createSession();
        $cookie = $this->client->getCookieJar()->get($session->getName());
        self::assertNotNull($cookie, 'The guest holds a session.');
        $session->setId($cookie->getValue());
        $session->start();

        return $session;
    }

    /**
     * A carrier that quotes every cart, the postal code being then the one reason it is not
     * offered, or else an installed carrier that quotes nothing in the guest's country.
     */
    private function zoneServingTheCountryExcept(int $countryId, string $excluded, bool $quoting): void
    {
        $connection = $this->getPropelConnection();
        $carrier = $quoting
            ? $this->registerDeliveryDateTestCarrier(static::getContainer(), $connection)
            : ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->findOne($connection);
        self::assertNotNull($carrier, 'An active carrier is installed.');

        $area = (new Area())->setName('Postal code message zone');
        $area->save($connection);
        (new CountryArea())->setAreaId($area->getId())->setCountryId($countryId)->save($connection);
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($carrier->getId())->save($connection);
        (new AreaZipCodeRule())->setAreaId($area->getId())->setPattern($excluded)->setExcluded(true)->save($connection);
    }
}
