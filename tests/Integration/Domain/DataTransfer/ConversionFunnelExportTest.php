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

namespace Thelia\Tests\Integration\Domain\DataTransfer;

use Thelia\Domain\DataTransfer\Export\Type\ConversionFunnelExport;
use Thelia\Model\ExportQuery;
use Thelia\Model\Lang;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The conversion funnel export writes one row per calendar day of the period, zero-filled,
 * so a period without any cart still yields a file instead of "no data found".
 */
final class ConversionFunnelExportTest extends IntegrationTestCase
{
    private const array EXPECTED_HEADERS = [
        'date',
        'carts_created',
        'carts_with_items',
        'carts_with_delivery_module',
        'carts_with_payment_module',
        'orders_created',
        'orders_paid',
    ];

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    public function testAMonthRangeYieldsOneAscendingRowPerDayOfThatMonth(): void
    {
        $rows = $this->exportedRows($this->monthRange(2024, 2));

        self::assertCount(29, $rows);

        $expectedDay = new \DateTimeImmutable('2024-02-01');
        foreach ($rows as $row) {
            self::assertSame($expectedDay->format('Y-m-d'), $row['day']);
            $expectedDay = $expectedDay->modify('+1 day');
        }
    }

    public function testTheCartsAndOrdersOfADayAreCountedOnItsRow(): void
    {
        $range = $this->monthRange(2024, 2);
        $baseline = $this->rowOf('2024-02-15', $this->exportedRows($range));

        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
        ));
        $cart->setCreatedAt(new \DateTime('2024-02-15 10:00:00'))->save($this->getPropelConnection());

        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $order->setCreatedAt(new \DateTime('2024-02-15 11:00:00'))->save($this->getPropelConnection());

        $row = $this->rowOf('2024-02-15', $this->exportedRows($range));

        self::assertSame($baseline['carts_created'] + 1, $row['carts_created']);
        self::assertSame($baseline['carts_with_items'] + 1, $row['carts_with_items']);
        self::assertSame($baseline['carts_with_delivery'], $row['carts_with_delivery']);
        self::assertSame($baseline['orders_created'] + 1, $row['orders_created']);
        self::assertSame($baseline['orders_paid'] + 1, $row['orders_paid']);
    }

    public function testAPeriodWithoutAnyCartYieldsZeroFilledRows(): void
    {
        $rows = $this->exportedRows($this->monthRange(1990, 1));

        self::assertCount(31, $rows);

        foreach ($rows as $row) {
            self::assertSame(0, $row['carts_created']);
            self::assertSame(0, $row['carts_with_items']);
            self::assertSame(0, $row['carts_with_delivery']);
            self::assertSame(0, $row['carts_with_payment']);
            self::assertSame(0, $row['orders_created']);
            self::assertSame(0, $row['orders_paid']);
        }
    }

    public function testWithoutARangeTheLastTwelveMonthsUpToTodayAreExported(): void
    {
        $rows = $this->exportedRows(null);

        $from = new \DateTimeImmutable('first day of -12 months');
        self::assertSame($from->format('Y-m-d'), $rows[0]['day']);
        self::assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $rows[\count($rows) - 1]['day']);
    }

    public function testAStartAloneRunsUpToToday(): void
    {
        $rows = $this->exportedRows(['start' => new \DateTime('-9 days 00:00:00'), 'end' => null]);

        self::assertCount(10, $rows);
        self::assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $rows[9]['day']);
    }

    public function testAnEndAloneRunsTwelveMonthsBeforeIt(): void
    {
        $rows = $this->exportedRows(['start' => null, 'end' => new \DateTime('2024-06-30 23:59:59')]);

        self::assertSame('2023-06-01', $rows[0]['day']);
        self::assertSame('2024-06-30', $rows[\count($rows) - 1]['day']);
    }

    public function testTheHeadersFollowTheDeclaredOrder(): void
    {
        $rows = $this->exportedRows($this->monthRange(1990, 1));

        $aliased = (new ConversionFunnelExport())->applyOrderAndAliases($rows[0]);

        self::assertSame(self::EXPECTED_HEADERS, array_keys($aliased));
        self::assertSame('1990-01-01', $aliased['date']);
    }

    public function testTheExportIsRegisteredInTheCatalogue(): void
    {
        $export = ExportQuery::create()->findOneByRef('thelia.export.conversion_funnel');

        self::assertNotNull($export);
        self::assertSame(ConversionFunnelExport::class, $export->getHandleClass());
        self::assertSame('thelia.export.reports', $export->getExportCategory()?->getRef());
    }

    /**
     * The range exactly as ExportHandler::export() rebuilds it from the back-office month pickers.
     *
     * @return array{start: \DateTime, end: \DateTime}
     */
    private function monthRange(int $year, int $month): array
    {
        $start = \DateTime::createFromFormat('Y-m-d H:i:s', $year.'-'.$month.'-1 00:00:00');
        $end = \DateTime::createFromFormat('Y-m-d H:i:s', $year.'-'.$month.'-1 23:59:59');
        self::assertInstanceOf(\DateTime::class, $start);
        self::assertInstanceOf(\DateTime::class, $end);
        $end->add(new \DateInterval('P1M'))->sub(new \DateInterval('P1D'));

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @param array<string, \DateTime|null>|null $rangeDate
     *
     * @return list<array<string, mixed>>
     */
    private function exportedRows(?array $rangeDate): array
    {
        $export = new ConversionFunnelExport();
        $export->setLang(Lang::getDefaultLanguage());
        $export->setRangeDate($rangeDate);

        $rows = [];
        foreach ($export as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    private function rowOf(string $day, array $rows): array
    {
        foreach ($rows as $row) {
            if ($row['day'] === $day) {
                return $row;
            }
        }

        self::fail("Day $day is missing from the export.");
    }
}
