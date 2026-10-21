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

use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Thelia\Api\Bridge\Propel\Event\DeliveryModuleOptionEvent;
use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Map\CartAddressTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Module;
use Thelia\Tests\Support\Delivery\DeliveryDateTestCarrier;
use Thelia\Tests\Support\Delivery\RegistersDeliveryDateTestCarrier;

/**
 * A pickup puts an address on the cart all the same, the one the order is built from. A
 * guest row is reused by whoever types the same e-mail address, and each identification
 * makes its own address the default one: a guest must get an address of their own
 * identification, never the default address of the row.
 *
 * The step belongs to the theme, which ships as its own package: a theme older than the
 * fix is reported as skipped rather than failed.
 */
final class GuestPickupAddressTest extends GuestCheckoutTestCase
{
    use RegistersDeliveryDateTestCarrier;

    /**
     * Written as strings: `::class` on a class the installed theme lacks resolves silently.
     */
    private const string DELIVERY_STEP_COMPONENT = 'FlexyBundle\\Components\\Organisms\\Delivery\\Base';

    private const string GUEST_CHECKOUT_GATE = 'FlexyBundle\\Service\\GuestCheckoutGate';

    private const string PICKUP = 'localpickup';

    /** Where GuestCheckoutGate keeps the addresses of the identification of the session. */
    private const string IDENTIFICATION_ADDRESS_IDS_KEY = 'flexy.guest_address_ids';

    private Module $carrier;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        if (!method_exists(self::GUEST_CHECKOUT_GATE, 'ownAddressId')) {
            self::markTestSkipped('The installed front-office theme puts the default address of the guest row on a pickup.');
        }

        parent::setUp();

        $this->carrier = $this->registerDeliveryDateTestCarrier(static::getContainer(), $this->getPropelConnection());
        $carrierCode = $this->carrier->getCode();
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_OPTIONS, static function (DeliveryModuleOptionEvent $event) use ($carrierCode): void {
            if ($event->getModule()->getCode() !== $carrierCode) {
                return;
            }

            $event->appendDeliveryModuleOptions(
                (new DeliveryModuleOption())
                    ->setCode(self::PICKUP)
                    ->setValid(true)
                    ->setTitle('Pickup in store')
                    ->setImage('')
                    ->setPostage((float) DeliveryDateTestCarrier::POSTAGE)
                    ->setPostageTax(0.0)
                    ->setPostageUntaxed((float) DeliveryDateTestCarrier::POSTAGE),
            );
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            static::getContainer()->get('event_dispatcher')->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        parent::tearDown();
    }

    /**
     * Two buyers on one e-mail address: the second identifies after the first, with another
     * address, which the guest row then holds as its default one. The first then picks the
     * order up: their cart keeps their own address.
     */
    public function testAGuestPickingUpNeverGetsTheAddressOfAnotherBuyerOfTheSameRow(): void
    {
        $firstCart = $this->twoBuyersOfOneRowBackInTheFirstSession();

        $this->pickUpInTheSessionOfTheClient();

        $copy = $this->deliveryCopyOf($firstCart);
        self::assertNotSame('9 rue des Autres', $copy?->getAddress1(), 'The cart of the first buyer received the address of another buyer.');
        self::assertSame('12 rue de la Paix', $copy?->getAddress1());
    }

    /**
     * A guest whose session holds no address of their own (an identification lost with an
     * older session, say): nothing is put on the cart, which keeps what it had.
     */
    public function testAGuestWithNoAddressOfTheirOwnKeepsTheCartAsItIs(): void
    {
        $firstCart = $this->twoBuyersOfOneRowBackInTheFirstSession();

        $this->pickUpInTheSessionOfTheClient(forgetTheIdentification: true);

        self::assertSame('12 rue de la Paix', $this->deliveryCopyOf($firstCart)?->getAddress1(), 'The cart was given an address that is not the buyer\'s.');
    }

    /**
     * The first buyer identifies and opens the delivery step; a second buyer identifies on
     * the same e-mail address with another address, which the row then holds as its default
     * one; the client is back in the session of the first.
     */
    private function twoBuyersOfOneRowBackInTheFirstSession(): Cart
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);

        $firstCart = $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));
        $this->assertResponseRedirectsTo('/checkout/delivery');
        $this->serveTheCountryOfTheGuest();
        // The delivery step puts the buyer's address on the cart, as it opens.
        $this->client->request('GET', '/checkout/delivery');
        self::assertSame('12 rue de la Paix', $this->deliveryCopyOf($firstCart)?->getAddress1());
        $firstSession = $this->client->getCookieJar()->all();

        $this->client->getCookieJar()->clear();
        $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage(), [
            'flexybundle_form_guest_checkout[address1]' => '9 rue des Autres',
            'flexybundle_form_guest_checkout[zipcode]' => '13001',
            'flexybundle_form_guest_checkout[city]' => 'Marseille',
        ]));
        $this->assertResponseRedirectsTo('/checkout/delivery');
        self::assertSame(
            '9 rue des Autres',
            $this->guestCustomerOf(self::GUEST_EMAIL)?->getDefaultAddress()?->getAddress1(),
            'The second identification made its address the default one of the row: the case the fix is about.',
        );

        $this->client->getCookieJar()->clear();
        foreach ($firstSession as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }

        return $firstCart;
    }

    /**
     * A customer with an account picks up with the default address of the account, as before.
     */
    public function testACustomerWithAnAccountPicksUpWithTheDefaultAddressOfTheAccount(): void
    {
        $cart = $this->openASessionWithACart();
        $account = $this->signInAsARealAccount();
        $country = $this->countryOfTheShop();
        $fixtures = $this->fixtures();
        $default = $fixtures->address($account, $country, null, ['address1' => '1 rue du Compte']);
        $default->setIsDefault(1)->save($this->getPropelConnection());
        $other = $fixtures->address($account, $country, null, ['address1' => '2 rue Secondaire']);
        $this->serveCountryWithCarrier($this->carrier, $country, $this->getPropelConnection());
        $cart = $this->reread($cart);
        $cart->setAddressDeliveryId($this->copyOf($other)->getId())->save($this->getPropelConnection());

        $this->pickUpInTheSessionOfTheClient();

        self::assertSame('1 rue du Compte', $this->deliveryCopyOf($cart)?->getAddress1(), 'A pickup puts the default address of the account on the cart.');
    }

    private function pickUpInTheSessionOfTheClient(bool $forgetTheIdentification = false): void
    {
        $requestStack = $this->getService(RequestStack::class);
        $request = Request::create('/checkout/delivery');
        $session = $this->sessionOfTheClient();

        if ($forgetTheIdentification) {
            $session->remove(self::IDENTIFICATION_ADDRESS_IDS_KEY);
        }

        $request->setSession($session);
        $requestStack->push($request);

        try {
            $step = $this->getService(self::DELIVERY_STEP_COMPONENT);
            $step->mount();
            $step->selectDeliveryModuleOption(self::PICKUP, (int) $this->carrier->getId());
        } finally {
            $requestStack->pop();
        }
    }

    private function serveTheCountryOfTheGuest(): void
    {
        $guest = $this->guestCustomerOf(self::GUEST_EMAIL) ?? self::fail('No guest.');
        $country = AddressQuery::create()->filterByCustomerId($guest->getId())->findOne()?->getCountry() ?? self::fail('The guest has no address.');
        $this->serveCountryWithCarrier($this->carrier, $country, $this->getPropelConnection());
    }

    private function countryOfTheShop(): Country
    {
        return CountryQuery::create()->filterByHasStates(0)->filterByNeedZipCode(0)->orderById()->findOne() ?? self::fail('No country to deliver to.');
    }

    private function copyOf(Address $address): CartAddress
    {
        $copy = (new CartAddress())
            ->setAddressId($address->getId())
            ->setCustomerTitleId($address->getTitleId())
            ->setFirstname((string) $address->getFirstname())
            ->setLastname((string) $address->getLastname())
            ->setAddress1((string) $address->getAddress1())
            ->setZipcode((string) $address->getZipcode())
            ->setCity((string) $address->getCity())
            ->setCountryId($address->getCountryId());
        $copy->save($this->getPropelConnection());

        return $copy;
    }

    private function deliveryCopyOf(Cart $cart): ?CartAddress
    {
        CartAddressTableMap::clearInstancePool();

        return CartAddressQuery::create()->findPk($this->reread($cart)->getAddressDeliveryId());
    }

    private function reread(Cart $cart): Cart
    {
        CartTableMap::clearInstancePool();

        return CartQuery::create()->findPk($cart->getId()) ?? self::fail('The cart is gone.');
    }

    private function sessionOfTheClient(): SessionInterface
    {
        $session = $this->getService('session.factory')->createSession();
        $cookie = $this->client->getCookieJar()->get($session->getName());
        self::assertInstanceOf(Cookie::class, $cookie, 'The buyer holds a session.');
        $session->setId($cookie->getValue());
        $session->start();

        return $session;
    }

    private function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        static::getContainer()->get('event_dispatcher')->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }
}
