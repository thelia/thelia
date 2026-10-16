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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\DeliveryModuleOptionEvent;
use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Enum\GuestCheckoutMode;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryDateSettings;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryTimeEstimator;
use Thelia\Model\AddressQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\Module;
use Thelia\Tests\Support\Delivery\RegistersDeliveryDateTestCarrier;

/**
 * The delivery step shows, on the card of each carrier, the interval its delays announce,
 * and nothing on a carrier without delays.
 *
 * The step belongs to the theme, which ships as its own package: a theme older than the
 * interval is reported as skipped rather than failed.
 */
final class DeliveryStepEstimateTest extends GuestCheckoutTestCase
{
    use RegistersDeliveryDateTestCarrier;

    /**
     * Written as a string on purpose: `::class` on a missing import resolves silently in
     * the current namespace, and the guard would then skip the whole file for ever.
     */
    private const CARRIER_CARD = 'FlexyBundle\\Components\\Organisms\\DeliveryMode\\Base';

    private Module $carrier;

    private string $shopClosedWeekdaysBefore;

    private string $cutoffBefore;

    /** @var callable|null */
    private $optionListener;

    /** What the test carrier writes in the dates of its option, before the core fills them. */
    private ?string $moduleEarliest = null;

    private ?string $moduleLatest = null;

    protected function setUp(): void
    {
        if (!property_exists(self::CARRIER_CARD, 'earliestDelivery')) {
            self::markTestSkipped('The installed front-office theme does not show the delivery interval on a carrier card.');
        }

        parent::setUp();

        $this->carrier = $this->registerDeliveryDateTestCarrier(static::getContainer(), $this->getPropelConnection());
        $this->shopClosedWeekdaysBefore = (string) ConfigQuery::read('delivery_closed_weekdays', '');
        $this->cutoffBefore = (string) ConfigQuery::read(DeliveryTimeEstimator::CUTOFF_CONFIG_NAME, '');
        ConfigQuery::write('delivery_closed_weekdays', '');
        ConfigQuery::write(DeliveryTimeEstimator::CUTOFF_CONFIG_NAME, '');

        $carrierId = $this->carrier->getId();
        $this->optionListener = function (DeliveryModuleOptionEvent $event) use ($carrierId): void {
            if ($event->getModule()->getId() === $carrierId) {
                $event->appendDeliveryModuleOptions(
                    (new DeliveryModuleOption())
                        ->setCode('estimate-step-test')
                        ->setValid(true)
                        ->setPostage(4.9)
                        ->setMinimumDeliveryDate($this->moduleEarliest)
                        ->setMaximumDeliveryDate($this->moduleLatest),
                );
            }
        };
        $this->getService(EventDispatcherInterface::class)->addListener(TheliaEvents::MODULE_DELIVERY_GET_OPTIONS, $this->optionListener);
    }

    protected function tearDown(): void
    {
        if (null !== $this->optionListener) {
            $this->getService(EventDispatcherInterface::class)->removeListener(TheliaEvents::MODULE_DELIVERY_GET_OPTIONS, $this->optionListener);
            ConfigQuery::write('delivery_closed_weekdays', $this->shopClosedWeekdaysBefore);
            ConfigQuery::write(DeliveryTimeEstimator::CUTOFF_CONFIG_NAME, $this->cutoffBefore);
        }

        parent::tearDown();
    }

    public function testTheCardOfACarrierShowsTheIntervalOfItsDelays(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->getService(DeliveryDateSettings::class)->saveDeliveryDelays($this->carrier, 1, 2, 3);

        $estimate = $this->deliveryStepOfAGuest()->filter('[data-testid="delivery-estimate"]');

        self::assertCount(1, $estimate);
        self::assertStringContainsString($this->spelled(2), $estimate->text());
        self::assertStringContainsString($this->spelled(3), $estimate->text());
    }

    public function testTheCardOfACarrierWithoutDelaysShowsNoInterval(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();

        $crawler = $this->deliveryStepOfAGuest();

        self::assertStringContainsString('estimate-step-test', (string) $this->client->getResponse()->getContent(), 'The step lists the test carrier.');
        self::assertCount(0, $crawler->filter('[data-testid="delivery-estimate"]'));
    }

    /**
     * A module may write anything in the dates of its option: the step shows no interval for
     * what is not a day, and is drawn all the same.
     */
    public function testADateTheModuleWroteAsFreeTextShowsNoIntervalAndBreaksNothing(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->moduleEarliest = $this->moduleLatest = '2 to 3 days';

        $crawler = $this->deliveryStepOfAGuest();

        self::assertStringContainsString('estimate-step-test', (string) $this->client->getResponse()->getContent(), 'The step lists the test carrier.');
        self::assertCount(0, $crawler->filter('[data-testid="delivery-estimate"]'));
    }

    public function testASingleDayIsAnnouncedAsThatDay(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->moduleEarliest = $this->moduleLatest = $this->day(4);

        $estimate = $this->deliveryStepOfAGuest()->filter('[data-testid="delivery-estimate"]');

        self::assertCount(1, $estimate);
        self::assertSame('Estimated delivery on '.$this->spelled(4), trim((string) preg_replace('/\s+/', ' ', $estimate->text())));
    }

    /**
     * A module that writes its two dates the wrong way round still announces the earlier one
     * first.
     */
    public function testDatesWrittenTheWrongWayRoundOpenWithTheEarlierOne(): void
    {
        $this->skipUnlessTheThemeHasTheIdentificationPage();
        $this->moduleEarliest = $this->day(5);
        $this->moduleLatest = $this->day(2);

        $text = $this->deliveryStepOfAGuest()->filter('[data-testid="delivery-estimate"]')->text();

        self::assertStringContainsString($this->spelled(2), $text);
        self::assertStringContainsString($this->spelled(5), $text);
        self::assertLessThan(strpos($text, $this->spelled(5)), strpos($text, $this->spelled(2)), $text);
    }

    private function deliveryStepOfAGuest(): Crawler
    {
        $this->setGuestCheckoutMode(GuestCheckoutMode::Enabled);
        $this->openASessionWithACart();
        $this->client->submit($this->guestFormOf($this->requestIdentificationPage()));
        $this->assertResponseRedirectsTo('/checkout/delivery');

        $guest = $this->guestCustomerOf(self::GUEST_EMAIL);
        self::assertNotNull($guest);
        $countryId = AddressQuery::create()->filterByCustomerId($guest->getId())->findOne()?->getCountryId();
        $country = null === $countryId ? null : CountryQuery::create()->findPk($countryId);
        self::assertNotNull($country);
        $this->serveCountryWithCarrier($this->carrier, $country, $this->getPropelConnection());

        $crawler = $this->client->request('GET', '/checkout/delivery');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return $crawler;
    }

    private function day(int $daysFromToday): string
    {
        return (new \DateTimeImmutable('today'))->modify(\sprintf('+%d days', $daysFromToday))->format('Y-m-d');
    }

    /** The day as the theme writes it in English: "Thursday 3 September". */
    private function spelled(int $daysFromToday): string
    {
        $day = (new \DateTimeImmutable('today'))->modify(\sprintf('+%d days', $daysFromToday));

        return (string) (new \IntlDateFormatter('en', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'EEEE d MMMM'))->format($day);
    }
}
