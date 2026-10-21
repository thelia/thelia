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
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session as TheliaSession;
use Thelia\Domain\Checkout\Enum\CheckoutDisplayMode;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Map\CartAddressTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
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

    /**
     * The release of the theme that keeps a guest's own address on a pickup and on a virtual
     * order: the service both go through.
     */
    private const string GUEST_DELIVERY_ADDRESS = 'FlexyBundle\\Service\\GuestDeliveryAddress';

    private const string PICKUP = 'localpickup';

    /** Where GuestCheckoutGate keeps the addresses of the identification of the session. */
    private const string IDENTIFICATION_ADDRESS_IDS_KEY = 'flexy.guest_address_ids';

    private Module $carrier;

    private ?string $previousDisplayMode = null;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        if (!class_exists(self::GUEST_DELIVERY_ADDRESS)) {
            self::markTestSkipped('The installed front-office theme puts the default address of the guest row on a pickup and on a virtual order.');
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

        if (null !== $this->previousDisplayMode) {
            ConfigQuery::write('checkout_display_mode', $this->previousDisplayMode);
            $this->previousDisplayMode = null;
        }

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
        $this->assertThePickupWasTaken($firstCart);
    }

    /**
     * The cart of the first buyer carries a copy of an address they are not shown (another
     * buyer's, on the same row): a pickup puts one of their own on it instead.
     */
    public function testAPickupReplacesACopyOfAnAddressTheGuestIsNotShown(): void
    {
        $firstCart = $this->twoBuyersOfOneRowBackInTheFirstSession();
        $earlierBuyers = $this->guestCustomerOf(self::GUEST_EMAIL)?->getDefaultAddress() ?? self::fail('No default address on the row.');
        $this->reread($firstCart)->setAddressDeliveryId($this->copyOf($earlierBuyers)->getId())->save($this->getPropelConnection());
        // The carrier prices the address it is given: 7.00 for the other buyer's, 5.00 for theirs.
        $carrierId = (int) $this->carrier->getId();
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event) use ($carrierId): void {
            if ($carrierId !== (int) $event->getModule()->getModuleModel()->getId()) {
                return;
            }

            $street = $event->getCart()->getCartAddressRelatedByAddressDeliveryId()?->getAddress1();
            $event->setValidModule(true)->setPostage(new OrderPostage('12 rue de la Paix' === $street ? 5.0 : 7.0));
            $event->stopPropagation();
        }, 2048);

        $step = $this->pickUpInTheSessionOfTheClient();

        $copy = $this->deliveryCopyOf($firstCart);
        self::assertSame('12 rue de la Paix', $copy?->getAddress1(), 'The copy of an address of another buyer stayed on the cart.');
        $this->assertThePickupWasTaken($firstCart);
        self::assertSame($copy?->getAddressId(), $step->deliveryAddressId, 'The step shows the address now on the cart.');
        self::assertEqualsWithDelta(5.0, (float) $this->reread($firstCart)->getPostage(), 0.001, 'The pickup is priced on the address now on the cart.');
    }

    /**
     * A guest with several addresses of their own: the default one among them comes before
     * the first.
     */
    public function testAGuestWithSeveralAddressesPicksUpWithTheirDefaultOne(): void
    {
        $firstCart = $this->twoBuyersOfOneRowBackInTheFirstSession();
        $guest = $this->guestCustomerOf(self::GUEST_EMAIL) ?? self::fail('No guest.');
        $own = AddressQuery::create()->filterByCustomerId($guest->getId())->filterByAddress1('12 rue de la Paix')->findOne() ?? self::fail('The first buyer has no address.');
        $ownDefault = $own->copy()->setLabel('Second own')->setAddress1('3 rue du Défaut')->setIsDefault(1);
        $ownDefault->save($this->getPropelConnection());
        $own->setIsDefault(0)->save($this->getPropelConnection());
        // A copy of the other buyer's address: the step leaves it, the pickup replaces it.
        $earlierBuyers = $this->guestCustomerOf(self::GUEST_EMAIL)?->getDefaultAddress() ?? self::fail('No default address on the row.');
        self::assertSame('9 rue des Autres', $earlierBuyers->getAddress1());
        $this->reread($firstCart)->setAddressDeliveryId($this->copyOf($earlierBuyers)->getId())->save($this->getPropelConnection());

        $this->pickUpInTheSessionOfTheClient(extraIdentificationAddressIds: [(int) $ownDefault->getId()]);

        self::assertSame('3 rue du Défaut', $this->deliveryCopyOf($firstCart)?->getAddress1(), 'The first of the guest\'s addresses was taken over their default one.');
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
    private function twoBuyersOfOneRowBackInTheFirstSession(bool $virtual = false): Cart
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);

        $firstCart = $this->openASessionWithACart();

        if ($virtual) {
            $this->makeEverythingInTheCartVirtual($firstCart);
        }

        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));
        self::assertTrue($this->client->getResponse()->isRedirect(), 'The identification goes through.');
        $this->serveTheCountryOfTheGuest();

        if (!$virtual) {
            // The delivery step puts the buyer's address on the cart, as it opens.
            $this->client->request('GET', '/checkout/delivery');
            self::assertSame('12 rue de la Paix', $this->deliveryCopyOf($firstCart)?->getAddress1());
        }

        $firstSession = $this->client->getCookieJar()->all();

        $this->client->getCookieJar()->clear();
        $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage(), [
            'flexybundle_form_guest_checkout[address1]' => '9 rue des Autres',
            'flexybundle_form_guest_checkout[zipcode]' => '13001',
            'flexybundle_form_guest_checkout[city]' => 'Marseille',
        ]));
        self::assertTrue($this->client->getResponse()->isRedirect(), 'The second identification goes through.');
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
     * A virtual order has no delivery step: the checkout settles its carrier, and the core
     * put the default address of the customer row on the cart, another buyer's for a guest
     * row shared by an e-mail address. The first buyer's cart carries their own address.
     */
    public function testAGuestOfAVirtualOrderNeverGetsTheAddressOfAnotherBuyerOfTheSameRow(): void
    {
        $firstCart = $this->twoBuyersOfOneRowBackInTheFirstSession(virtual: true);

        $this->client->request('GET', '/checkout/payment');

        $copy = $this->deliveryCopyOf($firstCart);
        self::assertNotSame('9 rue des Autres', $copy?->getAddress1(), 'The virtual order of the first buyer received the address of another buyer.');
        self::assertSame('12 rue de la Paix', $copy?->getAddress1(), 'The virtual order carries the buyer\'s own address.');
        self::assertSame((int) ModuleQuery::create()->findOneByCode('VirtualProductDelivery')?->getId(), (int) $this->reread($firstCart)->getDeliveryModuleId(), 'The virtual carrier was settled.');
    }

    /**
     * The order of a virtual cart paid straight away: the payment settles its carrier, after
     * the theme put an address of the buyer on the cart. The cart carries no billing address,
     * which the core would copy: the address comes from the theme.
     */
    public function testAVirtualOrderPaidStraightAwayKeepsTheGuestsOwnAddress(): void
    {
        $firstCart = $this->twoBuyersOfOneRowBackInTheFirstSession(virtual: true);
        $this->reread($firstCart)->setAddressInvoiceId(null)->save($this->getPropelConnection());

        $this->client->request('GET', '/checkout/pay');

        $this->assertTheVirtualOrderCarriesTheBuyersOwnAddress($firstCart);
    }

    /**
     * The same on a checkout of one page, which the cart page serves whole.
     */
    public function testAVirtualOrderOnAOnePageCheckoutKeepsTheGuestsOwnAddress(): void
    {
        $firstCart = $this->twoBuyersOfOneRowBackInTheFirstSession(virtual: true);
        $this->reread($firstCart)->setAddressInvoiceId(null)->save($this->getPropelConnection());
        $this->previousDisplayMode = ConfigQuery::getCheckoutDisplayMode();
        ConfigQuery::write('checkout_display_mode', CheckoutDisplayMode::OnePage->value);

        $this->client->request('GET', '/checkout/cart');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $this->assertTheVirtualOrderCarriesTheBuyersOwnAddress($firstCart);
    }

    /**
     * A copy with no source address was written for this cart alone, by this buyer: the
     * address a pickup or a virtual order puts on the cart is not put over it. Asked of the
     * service: the step, as it mounts, puts an address of the buyer over a copy it cannot
     * show.
     */
    public function testAPickupKeepsACopyWrittenForTheCartAlone(): void
    {
        $firstCart = $this->twoBuyersOfOneRowBackInTheFirstSession();
        $typed = (new CartAddress())
            ->setCustomerTitleId((int) $this->deliveryCopyOf($firstCart)?->getCustomerTitleId())
            ->setFirstname('Jean')
            ->setLastname('Dupont')
            ->setAddress1('7 rue Saisie')
            ->setZipcode('75001')
            ->setCity('Paris')
            ->setCountryId((int) $this->deliveryCopyOf($firstCart)?->getCountryId());
        $typed->save($this->getPropelConnection());
        $this->reread($firstCart)->setAddressDeliveryId($typed->getId())->save($this->getPropelConnection());

        $changed = $this->inTheSessionOfTheClient(fn (): bool => $this->getService(self::GUEST_DELIVERY_ADDRESS)->keepAnAddressOfTheGuest($this->reread($firstCart)));

        self::assertFalse($changed);
        self::assertSame($typed->getId(), $this->reread($firstCart)->getAddressDeliveryId());
        self::assertSame('7 rue Saisie', $this->deliveryCopyOf($firstCart)?->getAddress1(), 'The copy written for the cart was replaced.');
    }

    /**
     * The pickup cannot quote the default address of the account: once that address is on
     * the cart, the pickup goes, and the step says so.
     */
    public function testAPickupThatCannotQuoteTheDefaultAddressOfTheAccountGoes(): void
    {
        $cart = $this->openASessionWithACart();
        $account = $this->signInAsARealAccount();
        $country = $this->countryOfTheShop();
        $fixtures = $this->fixtures();
        $default = $fixtures->address($account, $country, null, ['address1' => '1 Far Street']);
        $default->setIsDefault(1)->save($this->getPropelConnection());
        $other = $fixtures->address($account, $country, null, ['address1' => '2 rue Secondaire']);
        $this->serveCountryWithCarrier($this->carrier, $country, $this->getPropelConnection());
        $carrierId = (int) $this->carrier->getId();
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event) use ($carrierId): void {
            if ($carrierId !== (int) $event->getModule()->getModuleModel()->getId()) {
                return;
            }

            $street = $event->getCart()->getCartAddressRelatedByAddressDeliveryId()?->getAddress1();
            $event->setValidModule('1 Far Street' !== $street)->setPostage(new OrderPostage(5.0));
            $event->stopPropagation();
        }, 2048);
        $cart = $this->reread($cart);
        $cart->setAddressDeliveryId($this->copyOf($other)->getId())->save($this->getPropelConnection());

        $step = $this->pickUpInTheSessionOfTheClient();

        self::assertSame('1 Far Street', $this->deliveryCopyOf($cart)?->getAddress1());
        self::assertNull($this->reread($cart)->getDeliveryModuleId(), 'A pickup that cannot quote the address stayed on the cart.');
        self::assertNull($step->deliveryModuleId, 'The step still shows the pickup as chosen.');
        self::assertNull($step->deliveryModuleOptionCode);
        self::assertSame('carrier_gone', $step->deliveryDateNotice);
        self::assertNull($this->getService(TheliaSession::class)->get('deliveryModuleOption'), 'The session still holds the pickup.');
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

    /**
     * @param list<int> $extraIdentificationAddressIds addresses the identification of the
     *                                                 session holds besides its own
     */
    private function pickUpInTheSessionOfTheClient(bool $forgetTheIdentification = false, array $extraIdentificationAddressIds = []): object
    {
        $requestStack = $this->getService(RequestStack::class);
        $request = Request::create('/checkout/delivery');
        $session = $this->sessionOfTheClient();

        if ($forgetTheIdentification) {
            $session->remove(self::IDENTIFICATION_ADDRESS_IDS_KEY);
        }

        if ([] !== $extraIdentificationAddressIds) {
            $session->set(self::IDENTIFICATION_ADDRESS_IDS_KEY, [...(array) $session->get(self::IDENTIFICATION_ADDRESS_IDS_KEY, []), ...$extraIdentificationAddressIds]);
        }

        $request->setSession($session);
        $requestStack->push($request);

        try {
            $step = $this->getService(self::DELIVERY_STEP_COMPONENT);
            $step->mount();
            $step->selectDeliveryModuleOption(self::PICKUP, (int) $this->carrier->getId());

            return $step;
        } finally {
            $requestStack->pop();
        }
    }

    private function assertTheVirtualOrderCarriesTheBuyersOwnAddress(Cart $cart): void
    {
        self::assertSame('12 rue de la Paix', $this->deliveryCopyOf($cart)?->getAddress1(), 'The virtual order carries the buyer\'s own address.');
        self::assertSame((int) ModuleQuery::create()->findOneByCode('VirtualProductDelivery')?->getId(), (int) $this->reread($cart)->getDeliveryModuleId(), 'The virtual carrier was settled.');
    }

    /**
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    private function inTheSessionOfTheClient(callable $call): mixed
    {
        $requestStack = $this->getService(RequestStack::class);
        $request = Request::create('/checkout/delivery');
        $request->setSession($this->sessionOfTheClient());
        $requestStack->push($request);

        try {
            return $call();
        } finally {
            $requestStack->pop();
        }
    }

    private function assertThePickupWasTaken(Cart $cart): void
    {
        self::assertSame((int) $this->carrier->getId(), (int) $this->reread($cart)->getDeliveryModuleId(), 'The carrier of the pickup is on the cart.');
        // The step writes the option through the session service it is given.
        self::assertSame(self::PICKUP, $this->getService(TheliaSession::class)->get('deliveryModuleOption'), 'The pickup is the option of the session.');
    }

    private function makeEverythingInTheCartVirtual(Cart $cart): void
    {
        $connection = $this->getPropelConnection();
        CartTableMap::clearInstancePool();

        foreach ((CartQuery::create()->findPk($cart->getId(), $connection) ?? self::fail('The cart is gone.'))->getCartItems() as $cartItem) {
            // Both halves of what makes a cart virtual: a virtual product, no weight to ship.
            $cartItem->getProductSaleElements()->setWeight(0)->save($connection);
            $cartItem->getProductSaleElements()->getProduct()->setVirtual(1)->save($connection);
        }

        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
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
