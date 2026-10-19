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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
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

/**
 * The delivery step of a guest whose postal code no zone takes: the step names the postal
 * code, instead of only saying that no delivery method is available.
 *
 * The step belongs to the theme, which ships as its own package: a theme older than the
 * message is reported as skipped rather than failed.
 */
final class DeliveryPostalCodeMessageTest extends GuestCheckoutTestCase
{
    /**
     * Written as a string on purpose: `::class` on a missing import resolves silently in
     * the current namespace, and the guard would then skip the whole file for ever.
     */
    private const DELIVERY_STEP_COMPONENT = 'FlexyBundle\\Components\\Organisms\\Delivery\\Base';

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
        $crawler = $this->deliveryStepOfAGuest(excluding: '20*');

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
     * A new address saved on the step is not the one the cart is delivered to: the copy stays.
     */
    public function testSavingANewAddressLeavesTheDeliveryCopy(): void
    {
        [$guest, $address] = $this->guestOnTheStepOfAZoneExcluding('20*');
        $new = $address->copy()->setIsDefault(0)->setLabel('New')->setZipcode('20000');

        $this->editOnTheStep(null, static fn () => $new);

        self::assertSame(self::GUEST_ZIP_CODE, $this->deliveryCopyOf($guest)?->getZipcode());
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
    private function deliveryStepOfAGuest(string $excluding, string $zipCode = self::GUEST_ZIP_CODE): Crawler
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
        $this->zoneServingTheCountryExcept((int) $countryId, $excluding);

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

    private function zoneServingTheCountryExcept(int $countryId, string $excluded): void
    {
        $connection = $this->getPropelConnection();
        $carrier = ModuleQuery::create()->filterByType(BaseModule::DELIVERY_MODULE_TYPE)->filterByActivate(1)->findOne($connection);
        self::assertNotNull($carrier, 'An active carrier is installed.');

        $area = (new Area())->setName('Postal code message zone');
        $area->save($connection);
        (new CountryArea())->setAreaId($area->getId())->setCountryId($countryId)->save($connection);
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($carrier->getId())->save($connection);
        (new AreaZipCodeRule())->setAreaId($area->getId())->setPattern($excluded)->setExcluded(true)->save($connection);
    }
}
