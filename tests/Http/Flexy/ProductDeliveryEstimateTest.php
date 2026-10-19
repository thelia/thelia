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

use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryDateSettings;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryTimeEstimator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Module;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Delivery\RegistersDeliveryDateTestCarrier;

/**
 * The delivery a product page announces before anything is in the cart, named after the
 * country it holds for.
 *
 * The page belongs to the theme, which ships as its own package: a theme older than the
 * estimate is reported as skipped rather than failed.
 */
final class ProductDeliveryEstimateTest extends WebIntegrationTestCase
{
    use RegistersDeliveryDateTestCarrier;

    /**
     * Written as a string on purpose: `::class` on a missing import resolves silently in
     * the current namespace, and the guard would then skip the whole file for ever.
     */
    private const COMPONENT = 'FlexyBundle\\Components\\Organisms\\DeliveryEstimate\\Base';

    private const PRODUCT_URL = 'flexy-delivery-estimate-test.html';

    private Module $carrier;

    private string $shopClosedWeekdaysBefore;

    private string $cutoffBefore;

    protected function setUp(): void
    {
        if (!class_exists(self::COMPONENT)) {
            self::markTestSkipped('The installed front-office theme does not announce a delivery on the product page.');
        }

        parent::setUp();

        $this->carrier = $this->registerDeliveryDateTestCarrier(static::getContainer(), $this->getPropelConnection());
        $this->shopClosedWeekdaysBefore = (string) ConfigQuery::read('delivery_closed_weekdays', '');
        $this->cutoffBefore = (string) ConfigQuery::read(DeliveryTimeEstimator::CUTOFF_CONFIG_NAME, '');
        ConfigQuery::write('delivery_closed_weekdays', '');
        ConfigQuery::write(DeliveryTimeEstimator::CUTOFF_CONFIG_NAME, '');
    }

    protected function tearDown(): void
    {
        if (isset($this->carrier)) {
            ConfigQuery::write('delivery_closed_weekdays', $this->shopClosedWeekdaysBefore);
            ConfigQuery::write(DeliveryTimeEstimator::CUTOFF_CONFIG_NAME, $this->cutoffBefore);
        }

        parent::tearDown();
    }

    public function testThePageAnnouncesTheIntervalAndTheCountryItHoldsFor(): void
    {
        $country = $this->defaultCountry();
        $this->serveCountryWithCarrier($this->carrier, $country, $this->getPropelConnection());
        $this->getService(DeliveryDateSettings::class)->saveDeliveryDelays($this->carrier, 1, 2, 3);
        $this->productUnderTest();

        $crawler = $this->client->request('GET', '/'.self::PRODUCT_URL);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $estimate = $crawler->filter('[data-testid="product-delivery-estimate"]');
        self::assertCount(1, $estimate);
        $text = $estimate->text();
        self::assertStringContainsString($this->spelled(2), $text);
        self::assertStringContainsString($this->spelled(3), $text);
        self::assertStringContainsString((string) $country->setLocale('en_US')->getTitle(), $text);

        // The estimate depends on the visitor's address and on today: a shared cache would
        // hand one visitor's days to another, and keep them after they are past.
        $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringNotContainsString('s-maxage', $cacheControl);
    }

    public function testNoCarrierWithDelaysAnnouncesNothing(): void
    {
        $this->serveCountryWithCarrier($this->carrier, $this->defaultCountry(), $this->getPropelConnection());
        $this->productUnderTest();

        $crawler = $this->client->request('GET', '/'.self::PRODUCT_URL);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        self::assertCount(0, $crawler->filter('[data-testid="product-delivery-estimate"]'));
    }

    /**
     * The theme promises shipping within 24 hours on every product page: where the shop
     * announces its own dates, that promise gives way to them, and it stays where nothing is
     * announced.
     */
    public function testTheShippingPromiseOfTheThemeGivesWayToTheAnnouncedDates(): void
    {
        $this->serveCountryWithCarrier($this->carrier, $this->defaultCountry(), $this->getPropelConnection());
        $this->getService(DeliveryDateSettings::class)->saveDeliveryDelays($this->carrier, 1, 2, 3);
        $this->productUnderTest();

        $announced = $this->client->request('GET', '/'.self::PRODUCT_URL);
        self::assertCount(1, $announced->filter('[data-testid="product-delivery-estimate"]'));
        self::assertStringNotContainsString('within 24 hours', $announced->filter('body')->text());

        $this->getService(DeliveryDateSettings::class)->saveDeliveryDelays($this->carrier, null, null, null);
        $silent = $this->client->request('GET', '/'.self::PRODUCT_URL);
        self::assertCount(0, $silent->filter('[data-testid="product-delivery-estimate"]'));
        self::assertStringContainsString('within 24 hours', $silent->filter('body')->text());
    }

    /**
     * Nothing is promised for a product the shop cannot sell from its stock.
     */
    public function testAProductOutOfAManagedStockAnnouncesNothing(): void
    {
        $this->serveCountryWithCarrier($this->carrier, $this->defaultCountry(), $this->getPropelConnection());
        $this->getService(DeliveryDateSettings::class)->saveDeliveryDelays($this->carrier, 1, 2, 3);
        $checkStockBefore = (string) ConfigQuery::read('check-available-stock', '1');
        ConfigQuery::write('check-available-stock', '1');

        try {
            $this->productUnderTest(quantity: 0);
            $crawler = $this->client->request('GET', '/'.self::PRODUCT_URL);
        } finally {
            ConfigQuery::write('check-available-stock', $checkStockBefore);
        }

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(0, $crawler->filter('[data-testid="product-delivery-estimate"]'));
    }

    private function productUnderTest(int $quantity = 100): void
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['baseQuantity' => $quantity]);
        $product->setLocale('en_US')->setTitle('Product whose delivery is estimated')->save($this->getPropelConnection());
        $product->setRewrittenUrl('en_US', self::PRODUCT_URL);
    }

    private function defaultCountry(): Country
    {
        $country = CountryQuery::create()->findOneByByDefault(1);
        self::assertInstanceOf(Country::class, $country);

        return $country;
    }

    /** The day as the theme writes it in English: "Thursday 3 September". */
    private function spelled(int $daysFromToday): string
    {
        $day = (new \DateTimeImmutable('today'))->modify(\sprintf('+%d days', $daysFromToday));

        return (string) (new \IntlDateFormatter('en', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'EEEE d MMMM'))->format($day);
    }
}
