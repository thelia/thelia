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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Domain\DataTransfer\Exception\DataTransferNoDataFoundException;
use Thelia\Domain\DataTransfer\Export\AbstractExport;
use Thelia\Domain\DataTransfer\Export\Type\CustomerCohortsExport;
use Thelia\Domain\DataTransfer\Export\Type\CustomerCohortsPaidExport;
use Thelia\Domain\DataTransfer\Export\Type\CustomerCohortsQuarterExport;
use Thelia\Domain\DataTransfer\Export\Type\CustomerCohortsQuarterPaidExport;
use Thelia\Domain\DataTransfer\Export\Type\CustomerLifetimeValueExport;
use Thelia\Domain\DataTransfer\Export\Type\CustomerLifetimeValuePaidExport;
use Thelia\Domain\DataTransfer\Export\Type\CustomerLifetimeValueQuarterExport;
use Thelia\Domain\DataTransfer\Export\Type\CustomerLifetimeValueQuarterPaidExport;
use Thelia\Domain\DataTransfer\Export\Type\CustomerSegmentsExport;
use Thelia\Domain\DataTransfer\Export\Type\CustomerSegmentsPaidExport;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Domain\Report\Customer\CustomerDormancy;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Export;
use Thelia\Model\ExportQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The customer exports write the cohort table, the lifetime value and the segments as the
 * calculation gives them. The orders are placed in 2001, a year the test database holds no order
 * of, so every figure is asserted as is.
 */
final class CustomerReportExportsTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    /** @var list<string> */
    private array $files = [];

    private ?string $dormancy = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->dormancy = ConfigQuery::read(CustomerDormancy::CONFIG_KEY);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        ConfigQuery::write(CustomerDormancy::CONFIG_KEY, $this->dormancy ?? (string) CustomerDormancy::DEFAULT_DAYS);
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, class-string<AbstractExport>}>
     */
    public static function theExports(): iterable
    {
        yield 'cohorts' => ['thelia.export.customer_cohorts', CustomerCohortsExport::class];
        yield 'cohorts, paid orders' => ['thelia.export.customer_cohorts_paid', CustomerCohortsPaidExport::class];
        yield 'cohorts by quarter' => ['thelia.export.customer_cohorts_quarter', CustomerCohortsQuarterExport::class];
        yield 'cohorts by quarter, paid orders' => ['thelia.export.customer_cohorts_quarter_paid', CustomerCohortsQuarterPaidExport::class];
        yield 'lifetime value' => ['thelia.export.customer_lifetime_value', CustomerLifetimeValueExport::class];
        yield 'lifetime value, paid orders' => ['thelia.export.customer_lifetime_value_paid', CustomerLifetimeValuePaidExport::class];
        yield 'lifetime value by quarter' => ['thelia.export.customer_lifetime_value_quarter', CustomerLifetimeValueQuarterExport::class];
        yield 'lifetime value by quarter, paid orders' => ['thelia.export.customer_lifetime_value_quarter_paid', CustomerLifetimeValueQuarterPaidExport::class];
        yield 'segments' => ['thelia.export.customer_segments', CustomerSegmentsExport::class];
        yield 'segments, paid orders' => ['thelia.export.customer_segments_paid', CustomerSegmentsPaidExport::class];
    }

    /**
     * @param class-string<AbstractExport> $class
     */
    #[DataProvider('theExports')]
    public function testTheExportIsRegisteredInTheReports(string $ref, string $class): void
    {
        $export = ExportQuery::create()->findOneByRef($ref);

        self::assertNotNull($export);
        self::assertSame($class, $export->getHandleClass());
        self::assertSame('thelia.export.reports', $export->getExportCategory()?->getRef());
        self::assertTrue($export->getHandleClassInstance()->useRangeDate());
        self::assertNotSame('', (string) $export->setLocale('fr_FR')->getTitle());
        self::assertNotSame('', (string) $export->getDescription());
    }

    public function testTheCohortFileIsTheCrossTableOfTheScreen(): void
    {
        $alice = $this->customer();
        $this->order($alice, '2001-01-10 10:00:00', '30.000000');
        $this->order($alice, '2001-03-05 10:00:00', '20.000000');
        $this->order($this->customer(), '2001-01-20 10:00:00', '50.000000');
        $this->order($this->customer(), '2001-02-14 10:00:00', '10.000000');

        [$headers, $rows] = $this->csv(CustomerCohortsExport::class, $this->firstThreeMonths());

        self::assertSame(['Cohort', 'Customers', 'Month 0', 'Month 0 (%)', 'Month 1', 'Month 1 (%)', 'Month 2', 'Month 2 (%)'], $headers);
        self::assertSame([
            ['2001-01', '2', '2', '100', '0', '0', '1', '50'],
            ['2001-02', '1', '1', '100', '0', '0', '', ''],
        ], $rows, 'One line per cohort, a month that has not come yet left empty: a spreadsheet reads it as is.');
    }

    public function testTheQuarterCohortFileHasAColumnPerQuarter(): void
    {
        $this->order($this->customer(), '2001-02-10 10:00:00', '30.000000');

        [$headers, $rows] = $this->csv(CustomerCohortsQuarterExport::class, ['start' => new \DateTime('2001-01-01 00:00:00'), 'end' => new \DateTime('2001-06-30 23:59:59')]);

        self::assertSame(['Cohort', 'Customers', 'Quarter 0', 'Quarter 0 (%)', 'Quarter 1', 'Quarter 1 (%)'], $headers);
        self::assertSame([['2001-Q1', '1', '1', '100', '0', '0']], $rows);
    }

    public function testTheLifetimeValueFileGivesTheCumulatedValuePerCustomerOfEachCohort(): void
    {
        $alice = $this->customer();
        $this->order($alice, '2001-01-10 10:00:00', '30.000000');
        $this->order($alice, '2001-03-05 10:00:00', '20.000000');
        $this->order($this->customer(), '2001-01-20 10:00:00', '50.000000');

        [$headers, $rows] = $this->csv(CustomerLifetimeValueExport::class, $this->firstThreeMonths());

        self::assertSame(['Cohort', 'Customers', 'Lifetime value incl. tax', 'Month 0', 'Month 1', 'Month 2'], $headers);
        self::assertSame([['2001-01', '2', '50', '40', '40', '50']], $rows);
        self::assertSame(
            [['Average lifetime value incl. tax', '50.00 €'], ['Average lifespan (days)', '27.0'], ['Repeat rate', '50.00 %']],
            $this->summaryOf(CustomerLifetimeValueExport::class, $this->firstThreeMonths()),
        );
    }

    public function testThePaidVariantLeavesTheUnpaidOrdersOut(): void
    {
        $this->order($this->customer(), '2001-01-10 10:00:00', '30.000000', OrderStatus::CODE_NOT_PAID);

        self::assertCount(1, $this->csv(CustomerCohortsExport::class, $this->firstThreeMonths())[1]);

        $this->expectException(DataTransferNoDataFoundException::class);
        $this->csv(CustomerCohortsPaidExport::class, $this->firstThreeMonths());
    }

    public function testACancelledOrRefundedOrderIsInNoVariant(): void
    {
        $this->order($this->customer(), '2001-01-10 10:00:00', '30.000000', OrderStatus::CODE_CANCELED);
        $this->order($this->customer(), '2001-01-11 10:00:00', '30.000000', OrderStatus::CODE_REFUNDED);

        $this->expectException(DataTransferNoDataFoundException::class);
        $this->csv(CustomerCohortsExport::class, $this->firstThreeMonths());
    }

    public function testTheSegmentFileFollowsTheDormancySettingOfTheShop(): void
    {
        $returning = $this->customer();
        $this->order($returning, '2001-01-10 10:00:00', '30.000000');
        $this->order($returning, '2001-03-10 10:00:00', '20.000000');
        $this->order($this->customer(), '2001-03-20 10:00:00', '60.000000');
        $this->order($this->customer(), '2001-02-01 10:00:00', '40.000000');
        ConfigQuery::write(CustomerDormancy::CONFIG_KEY, '45');

        [$headers, $rows] = $this->csv(CustomerSegmentsExport::class, ['start' => new \DateTime('2001-03-01 00:00:00'), 'end' => new \DateTime('2001-03-31 23:59:59')]);

        self::assertSame(['Segment', 'Customers', 'Revenue of the period incl. tax', 'Share of the revenue of the period (%)', 'Revenue since the first order incl. tax', 'Share of the revenue since the first order (%)'], $headers);
        self::assertSame([
            ['New customers', '1', '60', '75', '60', '40'],
            ['Returning customers', '1', '20', '25', '50', '33.33'],
            ['Lapsing customers (no order in the period, not dormant yet)', '0', '0', '0', '0', '0'],
            ['Dormant customers (no order for 45 days)', '1', '0', '0', '40', '26.67'],
        ], $rows);
    }

    public function testAPeriodWithoutAFirstOrderHasNoData(): void
    {
        $this->expectException(DataTransferNoDataFoundException::class);

        $this->csv(CustomerLifetimeValueExport::class, $this->firstThreeMonths());
    }

    /**
     * @return array{start: \DateTime, end: \DateTime}
     */
    private function firstThreeMonths(): array
    {
        return ['start' => new \DateTime('2001-01-01 00:00:00'), 'end' => new \DateTime('2001-03-31 23:59:59')];
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    private function order(Customer $customer, string $at, string $amount, string $status = OrderStatus::CODE_PAID): Order
    {
        $order = $this->factory->order($customer, ['statusCode' => $status, 'postage' => '0.000000']);
        $order->setCreatedAt(new \DateTime($at));
        $order->save($this->getPropelConnection());

        $this->factory->orderProduct($order, ['productRef' => 'LIFETIME', 'price' => $amount, 'promoPrice' => $amount, 'quantity' => 1]);

        return $order;
    }

    /**
     * @param array{start: \DateTime|null, end: \DateTime|null}|null $range
     *
     * @return array{list<string>, list<list<string>>} the header and the rows of the CSV file
     */
    private function csv(string $handleClass, ?array $range): array
    {
        $export = (new Export())->setRef('customer-report-test')->setHandleClass($handleClass);

        $event = $this->getService(ExportHandler::class)->export(
            $export,
            $this->getService(SerializerManager::class)->get('thelia.csv'),
            null,
            LangQuery::create()->findOneByLocale('en_US'),
            false,
            false,
            $range,
        );
        $this->files[] = $event->getFilePath();

        $handle = fopen($event->getFilePath(), 'r');
        $headers = fgetcsv($handle, null, ',', '"', '');
        $rows = [];

        while (false !== ($row = fgetcsv($handle, null, ',', '"', ''))) {
            $rows[] = $row;
        }

        fclose($handle);

        return [$headers, $rows];
    }

    /**
     * @param array{start: \DateTime|null, end: \DateTime|null}|null $range
     *
     * @return list<array{string, string}>
     */
    private function summaryOf(string $handleClass, ?array $range): array
    {
        $export = new $handleClass();
        $export->setLang(LangQuery::create()->findOneByLocale('en_US'));
        $export->setRangeDate($range);

        return array_map(static fn ($line): array => [$line->label, $line->value], $export->summarize());
    }
}
