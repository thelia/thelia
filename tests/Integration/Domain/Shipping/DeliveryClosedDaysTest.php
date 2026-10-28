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

namespace Thelia\Tests\Integration\Domain\Shipping;

use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryClosedDays;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryDateSettings;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Delivery\RegistersDeliveryDateTestCarrier;

/**
 * The closed days anything that counts delivery days reads: an estimate of the arrival of a
 * parcel must skip the same days as the calendar the buyer picks a day from.
 */
final class DeliveryClosedDaysTest extends IntegrationTestCase
{
    use RegistersDeliveryDateTestCarrier;

    /** A Monday, so the days of the week of the period are known. */
    private const string MONDAY = '2030-01-07';

    private Module $carrier;

    private string $shopClosedWeekdaysBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carrier = $this->registerDeliveryDateTestCarrier(static::getContainer(), $this->getPropelConnection());
        $this->shopClosedWeekdaysBefore = (string) ConfigQuery::read('delivery_closed_weekdays', '');
        ConfigQuery::write('delivery_closed_weekdays', '');
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('delivery_closed_weekdays', $this->shopClosedWeekdaysBefore);

        parent::tearDown();
    }

    public function testACarrierWithoutRuleFollowsTheClosedDaysOfTheWeekOfTheShop(): void
    {
        $this->settings()->saveShopClosedWeekdays([6, 7]);

        self::assertSame(['2030-01-12', '2030-01-13'], $this->closedDaysOfTheWeek());
    }

    public function testACarrierRuleReplacesTheClosedDaysOfTheShopEvenWithNone(): void
    {
        $this->settings()->saveShopClosedWeekdays([6, 7]);

        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::None, 0, 30, [3]);
        self::assertSame(['2030-01-09'], $this->closedDaysOfTheWeek(), 'The carrier closes on Wednesday only.');

        $this->settings()->saveRule($this->carrier, DeliveryDateChoiceMode::None, 0, 30, []);
        self::assertSame([], $this->closedDaysOfTheWeek(), 'A carrier that sets no closed day is open every day.');
    }

    public function testClosuresOfTheShopAndOfTheCarrierCountButNotThoseOfAnotherCarrier(): void
    {
        $this->settings()->addClosure(null, '2030-01-01', '2030-01-08', 'New year');
        $this->settings()->addClosure($this->carrier, '2030-01-10', '2030-01-10');
        $this->settings()->addClosure($this->otherModule(), '2030-01-11', '2030-01-11');

        self::assertSame(
            ['2030-01-07', '2030-01-08', '2030-01-10'],
            $this->closedDaysOfTheWeek(),
            'A closure that starts before the period is cut at its first day.',
        );
    }

    public function testTheHourOfTheBoundsIsIgnoredAndAnEmptyPeriodHasNoClosedDay(): void
    {
        $this->settings()->saveShopClosedWeekdays([1]);

        self::assertSame(
            ['2030-01-07' => true],
            $this->closedDays()->between((int) $this->carrier->getId(), new \DateTimeImmutable(self::MONDAY.' 18:30'), new \DateTimeImmutable(self::MONDAY.' 08:00')),
        );
        self::assertSame(
            [],
            $this->closedDays()->between((int) $this->carrier->getId(), new \DateTimeImmutable('2030-01-08'), new \DateTimeImmutable(self::MONDAY)),
        );
    }

    /**
     * @return list<string>
     */
    private function closedDaysOfTheWeek(): array
    {
        $monday = new \DateTimeImmutable(self::MONDAY);

        return array_keys($this->closedDays()->between((int) $this->carrier->getId(), $monday, $monday->modify('+6 days')));
    }

    private function closedDays(): DeliveryClosedDays
    {
        return $this->getService(DeliveryClosedDays::class);
    }

    private function settings(): DeliveryDateSettings
    {
        return $this->getService(DeliveryDateSettings::class);
    }

    private function otherModule(): Module
    {
        return ModuleQuery::create()->findOneByCode('VirtualProductDelivery')
            ?? throw new \RuntimeException('No VirtualProductDelivery module installed — run bin/test-prepare.');
    }
}
