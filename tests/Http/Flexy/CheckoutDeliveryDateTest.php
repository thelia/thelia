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

use Symfony\Component\DomCrawler\Crawler;
use Thelia\Api\Bridge\Propel\Event\DeliveryModuleOptionEvent;
use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryDateSettings;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;
use Thelia\Tests\Support\Delivery\DeliveryDateTestCarrier;
use Thelia\Tests\Support\Delivery\RegistersDeliveryDateTestCarrier;

/**
 * The delivery day at the delivery step of Flexy: the calendar under the carrier that offers
 * days and nowhere else, the day and the slot written through the live actions, a forged day
 * refused by the server, the step that cannot be left without a day, an address change, and
 * the order that keeps the day.
 */
final class CheckoutDeliveryDateTest extends GuestCheckoutTestCase
{
    use RegistersDeliveryDateTestCarrier;

    /**
     * Shipped with the feature in the theme: a theme published before it has no calendar,
     * and the core suite installs the published theme.
     */
    private const BRIDGE = 'FlexyBundle\Service\DeliveryDateBridge';

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    private Module $carrier;

    /**
     * Null when setUp() skipped the test before reading it: tearDown() then has nothing to restore.
     */
    private ?string $shopClosedWeekdaysBefore = null;

    protected function setUp(): void
    {
        if (!class_exists(self::BRIDGE)) {
            self::markTestSkipped('The installed theme offers no delivery date at its delivery step.');
        }

        parent::setUp();

        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->turnMandatoryConsentsOff();
        $this->carrier = $this->registerDeliveryDateTestCarrier(static::getContainer(), $this->getPropelConnection());
        $this->shopClosedWeekdaysBefore = (string) ConfigQuery::read('delivery_closed_weekdays', '');
        ConfigQuery::write('delivery_closed_weekdays', '');
        ConfigQuery::resetCache();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            static::getContainer()->get('event_dispatcher')->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        if (null !== $this->shopClosedWeekdaysBefore) {
            ConfigQuery::write('delivery_closed_weekdays', $this->shopClosedWeekdaysBefore);
        }

        parent::tearDown();
    }

    /**
     * Recette 1: without a carrier that offers days, the step is what it was.
     */
    public function testNoCalendarUnderACarrierThatOffersNoDay(): void
    {
        $this->openACheckoutReadyCart();

        $crawler = $this->requestTheDeliveryStep();

        self::assertCount(1, $crawler->filter('.DeliveryMode'), 'The test carrier is offered.');
        self::assertCount(0, $crawler->filter('[data-testid="delivery-date-picker"]'));
        self::assertStringNotContainsString('Choose a delivery date', (string) $this->client->getResponse()->getContent());
    }

    /**
     * Recette 2: the calendar opens after the delay, closes at the horizon, Sundays closed.
     */
    public function testTheCalendarOffersTheWindowOfTheCarrierWithItsClosedDaysDisabled(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 2, 21, [7]);
        $this->openACheckoutReadyCart();

        $crawler = $this->requestTheDeliveryStep();

        self::assertCount(1, $crawler->filter('[data-testid="delivery-date-picker"]'));
        self::assertCount(0, $crawler->filter(\sprintf('[data-testid="delivery-date-day-%s"]', $this->day(1))), 'Tomorrow is before the delay.');
        self::assertCount(1, $crawler->filter(\sprintf('[data-testid="delivery-date-day-%s"]', $this->day(2))));

        foreach ($crawler->filter('button[data-live-action-param="chooseDay"]') as $button) {
            $date = new \DateTimeImmutable((string) $button->getAttribute('data-live-date-param'));

            self::assertSame('7' === $date->format('N'), $button->hasAttribute('disabled'), $date->format('l Y-m-d'));
        }
    }

    /**
     * Recette 3: a day picked, the order placed, the day on the order.
     */
    public function testADayPickedAtTheDeliveryStepEndsUpOnTheOrder(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 2, 21, []);
        $cart = $this->openACheckoutReadyCart();

        $crawler = $this->requestTheDeliveryStep();
        self::assertStringContainsString('Choose a delivery date to continue.', (string) $this->client->getResponse()->getContent(), 'The step cannot be left without a day.');

        $this->callLiveAction($crawler->filter(\sprintf('[data-testid="delivery-date-day-%s"]', $this->day(3))), 'chooseDay', ['date' => $this->day(3)]);
        self::assertSame($this->day(3), $this->reread($cart)->getDeliveryDay());

        $crawler = $this->requestTheDeliveryStep();
        self::assertCount(1, $crawler->filter('[data-testid="delivery-date-chosen"]'));
        self::assertCount(1, $crawler->filter('[data-testid="summary-delivery-date"]'), 'The summary of the tunnel says the day.');
        self::assertStringNotContainsString('Choose a delivery date to continue.', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/checkout/pay');

        $order = OrderQuery::create()->filterByCartId($cart->getId())->findOne($this->getPropelConnection());
        self::assertNotNull($order, 'The order must have been placed.');
        self::assertSame($this->day(3), $order->getDeliveryDay());
    }

    /**
     * Recette 4: a slot, after its day.
     */
    public function testASlotIsPickedAfterItsDayAndAFullSlotCannotBe(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 1, 7, []);
        $morning = $this->settings()->addSlot($this->carrier, '09:00', '11:00', 1, ['en_US' => 'Morning', 'fr_FR' => 'Matin']);
        $noon = $this->settings()->addSlot($this->carrier, '11:00', '13:00', null);
        $this->getService(\Thelia\Domain\Shipping\DeliveryDate\Service\DeliverySlotBooker::class)->book((int) $morning->getId(), $this->day(1), $this->getPropelConnection());
        $cart = $this->openACheckoutReadyCart();

        $crawler = $this->requestTheDeliveryStep();
        $this->callLiveAction($crawler->filter(\sprintf('[data-testid="delivery-date-day-%s"]', $this->day(1))), 'chooseDay', ['date' => $this->day(1)]);
        self::assertNull($this->reread($cart)->getDeliveryDay(), 'A day alone is not a choice for a carrier of slots.');

        $answer = new Crawler((string) $this->client->getResponse()->getContent());
        self::assertTrue($answer->filter(\sprintf('[data-testid="delivery-date-slot-%d"]', $morning->getId()))->first()->matches('[disabled]'), 'The full slot is offered disabled.');
        self::assertFalse($answer->filter(\sprintf('[data-testid="delivery-date-slot-%d"]', $noon->getId()))->first()->matches('[disabled]'));

        // A forged click on the full slot is refused by the server.
        $this->callLiveAction($answer->filter(\sprintf('[data-testid="delivery-date-slot-%d"]', $noon->getId())), 'chooseSlot', ['date' => $this->day(1), 'slotId' => (int) $morning->getId()]);
        self::assertNull($this->reread($cart)->getDeliveryDay());
        self::assertStringContainsString('full', strtolower((string) $this->client->getResponse()->getContent()));

        $this->callLiveAction($answer->filter(\sprintf('[data-testid="delivery-date-slot-%d"]', $noon->getId())), 'chooseSlot', ['date' => $this->day(1), 'slotId' => (int) $noon->getId()]);
        self::assertSame($this->day(1), $this->reread($cart)->getDeliveryDay());
        self::assertSame((int) $noon->getId(), (int) $this->reread($cart)->getDeliverySlotId());
    }

    /**
     * Dev check: a day forged in the request is refused by the server, whatever the page.
     */
    public function testAForgedDayIsRefusedByTheServer(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 2, 21, [7]);
        $cart = $this->openACheckoutReadyCart();
        $crawler = $this->requestTheDeliveryStep();
        $anyDay = $crawler->filter(\sprintf('[data-testid="delivery-date-day-%s"]', $this->day(2)));

        foreach ([$this->day(0), $this->day(40), $this->nextSunday()] as $forged) {
            $this->callLiveAction($anyDay, 'chooseDay', ['date' => $forged]);

            self::assertNull($this->reread($cart)->getDeliveryDay(), $forged.' must not be written.');
            self::assertCount(1, (new Crawler((string) $this->client->getResponse()->getContent()))->filter('[data-testid="delivery-date-error"]'), $forged.' is refused with a message.');
        }
    }

    /**
     * A day forged for a carrier of slots is not kept even as the day whose slots are listed:
     * the step keeps rendering and says the day is not available.
     */
    public function testAForgedDayForACarrierOfSlotsIsNotKept(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Slot, 1, 7, []);
        $this->settings()->addSlot($this->carrier, '09:00', '11:00', null);
        $cart = $this->openACheckoutReadyCart();
        $anyDay = $this->requestTheDeliveryStep()->filter(\sprintf('[data-testid="delivery-date-day-%s"]', $this->day(1)));

        foreach (['not a day', '+1 year', $this->day(30)] as $forged) {
            $this->callLiveAction($anyDay, 'chooseDay', ['date' => $forged]);
            $answer = new Crawler((string) $this->client->getResponse()->getContent());

            self::assertCount(1, $answer->filter('[data-testid="delivery-date-error"]'), $forged.' is refused with a message.');
            self::assertCount(0, $answer->filter('[data-testid="delivery-date-slots"]'), $forged.' lists no slot.');
        }

        self::assertNull($this->reread($cart)->getDeliveryDay());
        $this->requestTheDeliveryStep();
    }

    /**
     * Recette 5: an address the carrier no longer serves takes the carrier and the day away,
     * and says so; an address it still serves keeps both.
     */
    public function testAnAddressChangeKeepsTheDayOnlyWhileTheCarrierServesTheAddress(): void
    {
        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::Date, 2, 21, []);
        $cart = $this->openACheckoutReadyCart();
        $address = AddressQuery::create()->filterByCustomerId($cart->getCustomerId())->findOne($this->getPropelConnection()) ?? self::fail('No address.');

        $crawler = $this->requestTheDeliveryStep();
        $this->callLiveAction($crawler->filter(\sprintf('[data-testid="delivery-date-day-%s"]', $this->day(3))), 'chooseDay', ['date' => $this->day(3)]);

        $step = $this->requestTheDeliveryStep()->filter('[data-live-name-value="Organisms:Delivery:Base"]');
        $this->callLiveAction($step->children()->first(), 'selectDeliveryAddress', ['addressId' => (int) $address->getId()]);
        self::assertSame($this->day(3), $this->reread($cart)->getDeliveryDay(), 'The same carrier still serves the address: the day stays.');

        // The buyer moves to a country the carrier does not serve.
        $elsewhere = \Thelia\Model\CountryQuery::create()->filterById($address->getCountryId(), \Propel\Runtime\ActiveQuery\Criteria::NOT_EQUAL)->findOne($this->getPropelConnection()) ?? self::fail('A second country is needed.');
        $address->setCountryId($elsewhere->getId())->save($this->getPropelConnection());

        $step = $this->requestTheDeliveryStep()->filter('[data-live-name-value="Organisms:Delivery:Base"]');
        $this->callLiveAction($step->children()->first(), 'selectDeliveryAddress', ['addressId' => (int) $address->getId()]);

        $cart = $this->reread($cart);
        self::assertNull($cart->getDeliveryModuleId());
        self::assertNull($cart->getDeliveryDay());
        self::assertCount(1, (new Crawler((string) $this->client->getResponse()->getContent()))->filter('[data-testid="delivery-date-notice"]'), 'The buyer is told why the day is gone.');
    }

    private function requestTheDeliveryStep(): Crawler
    {
        $crawler = $this->client->request('GET', '/checkout/delivery');

        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'The delivery step must be served, or nothing below proves anything.');

        return $crawler;
    }

    /**
     * @param array<string, mixed> $args
     */
    private function callLiveAction(Crawler $node, string $action, array $args): void
    {
        self::assertGreaterThan(0, $node->count(), \sprintf('Nothing on the page to send "%s" through.', $action));

        $component = $node->ancestors()->filter('[data-live-url-value]')->first();

        if (0 === $component->count() && $node->matches('[data-live-url-value]')) {
            $component = $node;
        }

        self::assertCount(1, $component, 'The control must live inside a live component.');

        $this->client->request(
            'POST',
            \sprintf('%s/%s', $component->attr('data-live-url-value'), $action),
            ['data' => json_encode([
                'props' => json_decode((string) $component->attr('data-live-props-value'), true, 512, \JSON_THROW_ON_ERROR),
                'updated' => new \stdClass(),
                'args' => $args,
            ], \JSON_THROW_ON_ERROR)],
            [],
            ['HTTP_ACCEPT' => 'application/vnd.live-component+html', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );

        self::assertSame(200, $this->client->getResponse()->getStatusCode(), \sprintf('The action "%s" must answer.', $action));
    }

    /**
     * A guest session at the delivery step, with an address the test carrier serves and the
     * test carrier on the cart.
     */
    private function openACheckoutReadyCart(): Cart
    {
        $cart = $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));

        $connection = $this->getPropelConnection();
        CartTableMap::clearInstancePool();
        $cart = CartQuery::create()->findPk($cart->getId(), $connection) ?? self::fail('The cart the session was filling is gone.');
        $address = AddressQuery::create()->filterByCustomerId($cart->getCustomerId())->findOne($connection) ?? self::fail('The identification form must have written an address.');

        $country = $address->getCountry() ?? self::fail('The address has no country.');
        $this->serveCountryWithCarrier($this->carrier, $country, $connection);

        $cart
            ->setAddressDeliveryId($this->copyToCartAddress($address)->getId())
            ->setAddressInvoiceId($this->copyToCartAddress($address)->getId())
            ->setDeliveryModuleId($this->carrier->getId())
            ->setPaymentModuleId((int) ModuleQuery::create()->findOneByCode('Cheque')?->getId())
            ->setPostage(DeliveryDateTestCarrier::POSTAGE)
            ->save($connection);

        foreach ($cart->getCartItems() as $cartItem) {
            $cartItem->getProductSaleElements()->setQuantity(100)->save($connection);
        }

        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
        ProductSaleElementsTableMap::clearInstancePool();

        $carrierCode = DeliveryDateTestCarrier::getModuleCode();
        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_OPTIONS, static function (DeliveryModuleOptionEvent $event) use ($carrierCode): void {
            if ($event->getModule()->getCode() !== $carrierCode) {
                return;
            }

            $event->appendDeliveryModuleOptions(
                (new DeliveryModuleOption())
                    ->setCode($carrierCode)
                    ->setValid(true)
                    ->setTitle('Dated carrier')
                    ->setImage('')
                    ->setPostage((float) DeliveryDateTestCarrier::POSTAGE)
                    ->setPostageTax(0.0)
                    ->setPostageUntaxed((float) DeliveryDateTestCarrier::POSTAGE),
            );
        });
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->setMinimumAmount(0);
            $event->setMaximumAmount(0);
            $event->stopPropagation();
        }, 512);

        return $cart;
    }

    private function copyToCartAddress(Address $source): CartAddress
    {
        $address = (new CartAddress())
            ->setAddressId($source->getId())
            ->setCustomerTitleId($source->getTitleId())
            ->setFirstname((string) $source->getFirstname())
            ->setLastname((string) $source->getLastname())
            ->setAddress1((string) $source->getAddress1())
            ->setZipcode((string) $source->getZipcode())
            ->setCity((string) $source->getCity())
            ->setCellphone($source->getCellphone())
            ->setCountryId($source->getCountryId());
        $address->save($this->getPropelConnection());

        return $address;
    }

    private function reread(Cart $cart): Cart
    {
        CartTableMap::clearInstancePool();

        return CartQuery::create()->findPk($cart->getId(), $this->getPropelConnection()) ?? self::fail('The cart is gone.');
    }

    private function settings(): DeliveryDateSettings
    {
        return $this->getService(DeliveryDateSettings::class);
    }

    private function day(int $daysFromToday): string
    {
        return (new \DateTimeImmutable('today'))->modify(\sprintf('+%d days', $daysFromToday))->format('Y-m-d');
    }

    private function nextSunday(): string
    {
        return (new \DateTimeImmutable('today'))->modify('next sunday')->modify('+7 days')->format('Y-m-d');
    }

    private function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        static::getContainer()->get('event_dispatcher')->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function turnMandatoryConsentsOff(): void
    {
        foreach (ConsentQuery::create()->filterByActive(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setMandatory(0)->save($this->getPropelConnection());
        }

        static::getContainer()->get(ConsentProvider::class)->forgetCache();
    }
}
