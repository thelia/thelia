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
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Model\AddressQuery;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\AreaZipCodeRule;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\CountryArea;
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
     * the carriers. The cart keeps its own copy of the address, which only a new choice of
     * the address refreshes.
     */
    public function testEditingTheDeliveryAddressOnTheStepFollowsItsNewPostalCode(): void
    {
        if (!method_exists(self::DELIVERY_STEP_COMPONENT, 'addressSaved')) {
            self::markTestSkipped('The installed front-office theme does not choose an edited delivery address again.');
        }

        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->deliveryStepOfAGuest(excluding: '20*');
        $guest = $this->guestCustomerOf(self::GUEST_EMAIL);
        self::assertNotNull($guest);
        $address = AddressQuery::create()->filterByCustomerId($guest->getId())->findOne();
        self::assertNotNull($address);

        // Driven outside of a page: the request of the guest's session is provided.
        $requestStack = $this->getService(RequestStack::class);
        $request = Request::create('/checkout/delivery');
        $request->setSession($this->sessionOfTheClient());
        $requestStack->push($request);

        try {
            $step = $this->getService(self::DELIVERY_STEP_COMPONENT);
            $step->mount();
            $step->setEditingAddress((int) $address->getId());
            $address->setZipcode('20000')->setCity('Ajaccio')->save($this->getPropelConnection());
            $step->addressSaved();
            $blamed = $step->getPostalCodeWithoutCarrier();
        } finally {
            $requestStack->pop();
        }

        self::assertSame('20000', $blamed);
        $cart = CartQuery::create()->filterByCustomerId($guest->getId())->orderById(Criteria::DESC)->findOne();
        self::assertSame('20000', CartAddressQuery::create()->findPk($cart?->getAddressDeliveryId())?->getZipcode(), 'The order would leave for the address the buyer no longer reads.');
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
