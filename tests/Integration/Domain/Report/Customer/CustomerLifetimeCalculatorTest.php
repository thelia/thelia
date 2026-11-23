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

namespace Thelia\Tests\Integration\Domain\Report\Customer;

use Thelia\Domain\Customer\Service\CustomerAnonymizer;
use Thelia\Domain\Report\Customer\CohortCell;
use Thelia\Domain\Report\Customer\CohortGranularity;
use Thelia\Domain\Report\Customer\CohortRow;
use Thelia\Domain\Report\Customer\CustomerLifetimeCalculator;
use Thelia\Domain\Report\Customer\CustomerSegment;
use Thelia\Domain\Report\Customer\SegmentRow;
use Thelia\Domain\Report\Sales\SalesReportCriteria;
use Thelia\Domain\Report\Sales\StatusScope;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The cohorts, the lifetime value and the segments of the customers. The orders are placed in
 * 2001, a year the test database holds no order of, so every figure is asserted as is.
 */
final class CustomerLifetimeCalculatorTest extends IntegrationTestCase
{
    private const array ROUNDING_CONFIG = [
        'order_rounding_mode',
        'last_legacy_rounding_order_id',
        'last_sum_of_roundings_order_id',
        'last_text_promo_price_order_id',
    ];

    private CustomerLifetimeCalculator $calculator;

    private FixtureFactory $factory;

    /** @var array<string, string|null> */
    private array $roundingConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new CustomerLifetimeCalculator($this->getPropelConnection());
        $this->factory = $this->createFixtureFactory();

        foreach (self::ROUNDING_CONFIG as $name) {
            $this->roundingConfig[$name] = ConfigQuery::read($name);
        }

        ConfigQuery::write('order_rounding_mode', (string) ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS);
        ConfigQuery::write('last_legacy_rounding_order_id', '0');
        ConfigQuery::write('last_sum_of_roundings_order_id', '0');
        ConfigQuery::write('last_text_promo_price_order_id', '0');
    }

    protected function tearDown(): void
    {
        foreach ($this->roundingConfig as $name => $value) {
            if (null !== $value) {
                ConfigQuery::write($name, $value);
            }
        }

        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testACustomerAcquiredInJanuaryWhoOrdersAgainInMarchIsInTheJanuaryCohortTwoMonthsLater(): void
    {
        $alice = $this->customer();
        $this->order($alice, '2001-01-10 10:00:00', '30.000000');
        $this->order($alice, '2001-03-05 10:00:00', '20.000000');
        $bob = $this->customer();
        $this->order($bob, '2001-01-20 10:00:00', '50.000000');
        $carol = $this->customer();
        $this->order($carol, '2001-02-14 10:00:00', '10.000000');

        $table = $this->calculator->cohorts($this->firstFourMonths(), CohortGranularity::Month);

        self::assertSame(4, $table->columnCount);
        self::assertSame(['2001-01', '2001-02'], array_map(static fn (CohortRow $row): string => $row->label, $table->rows), 'A month without a first order has no row.');

        [$january, $february] = $table->rows;
        self::assertSame(2, $january->size);
        self::assertSame([2, 0, 1, 0], $this->active($january), 'Alice ordered again two months after her first order.');
        self::assertSame([1.0, 0.0, 0.5, 0.0], $this->shares($january));
        self::assertSame([40.0, 40.0, 50.0, 50.0], $this->values($january), 'The cumulated value per customer never goes down.');
        self::assertSame(50.0, $january->lifetimeValue());

        self::assertSame(1, $february->size);
        self::assertSame([1, 0, 0, null], $this->active($february), 'A month that has not come yet is empty, not zero.');
    }

    public function testEveryCohortStartsAtOneHundredPercent(): void
    {
        foreach (['2001-01-03', '2001-01-28', '2001-02-01', '2001-03-31', '2001-04-15'] as $day) {
            $this->order($this->customer(), $day.' 09:00:00', '15.000000');
        }

        foreach ($this->calculator->cohorts($this->firstFourMonths(), CohortGranularity::Month)->rows as $row) {
            self::assertSame(1.0, $row->cells[0]?->share, $row->label);
            self::assertSame($row->size, $row->cells[0]->activeCustomers, $row->label);
        }
    }

    public function testACustomerBelongsToTheCohortOfTheirFirstOrderNotOfTheirSignUp(): void
    {
        $customer = $this->customer();
        $customer->setCreatedAt(new \DateTime('2000-06-01 00:00:00'))->save($this->getPropelConnection());
        $this->order($customer, '2001-02-10 10:00:00', '10.000000');
        $this->customer(); // signed up, never ordered

        $table = $this->calculator->cohorts($this->firstFourMonths(), CohortGranularity::Month);

        self::assertSame(['2001-02'], array_map(static fn (CohortRow $row): string => $row->label, $table->rows));
        self::assertSame(1, $table->rows[0]->size, 'An account without an order is in no cohort.');
    }

    public function testACustomerWhoOrderedBeforeTheTableIsInNoCohortOfIt(): void
    {
        $old = $this->customer();
        $this->order($old, '2000-11-10 10:00:00', '10.000000');
        $this->order($old, '2001-02-10 10:00:00', '10.000000');

        self::assertSame([], $this->calculator->cohorts($this->firstFourMonths(), CohortGranularity::Month)->rows);
        self::assertSame(0, $this->calculator->lifetime($this->firstFourMonths())->customerCount);
    }

    public function testAnAnonymizedCustomerKeepsTheirCohortAndTheirAmounts(): void
    {
        $customer = $this->customer();
        $this->order($customer, '2001-01-10 10:00:00', '30.000000');
        $this->order($customer, '2001-02-10 10:00:00', '12.000000');
        $before = $this->calculator->cohorts($this->firstFourMonths(), CohortGranularity::Month);

        $this->getService(CustomerAnonymizer::class)->anonymize($customer);

        $after = $this->calculator->cohorts($this->firstFourMonths(), CohortGranularity::Month);
        self::assertNotSame('Ada', CustomerQuery::create()->findPk($customer->getId())?->getFirstname());
        self::assertEquals($before, $after);
    }

    public function testAQuarterGathersTheFirstOrdersOfThreeMonths(): void
    {
        $january = $this->customer();
        $this->order($january, '2001-01-10 10:00:00', '10.000000');
        $this->order($january, '2001-03-10 10:00:00', '10.000000');
        $this->order($january, '2001-04-10 10:00:00', '10.000000');
        $this->order($this->customer(), '2001-03-31 23:00:00', '30.000000');

        $table = $this->calculator->cohorts($this->firstFourMonths(), CohortGranularity::Quarter);

        self::assertSame(2, $table->columnCount);
        self::assertSame(['2001-Q1'], array_map(static fn (CohortRow $row): string => $row->label, $table->rows));
        self::assertSame(2, $table->rows[0]->size);
        self::assertSame([2, 1], $this->active($table->rows[0]), 'Two orders in the same quarter are one active customer.');
        self::assertSame([25.0, 30.0], $this->values($table->rows[0]));
    }

    public function testTheLifetimeValueIsWhatACustomerSpentOnAverageAndTheRepeatRateTheShareOfCustomersWithMoreThanOneOrder(): void
    {
        $alice = $this->customer();
        $this->order($alice, '2001-01-10 10:00:00', '30.000000');
        $this->order($alice, '2001-01-20 10:00:00', '20.000000');
        $this->order($alice, '2001-03-11 10:00:00', '10.000000');
        $this->order($this->customer(), '2001-02-14 10:00:00', '40.000000');

        $summary = $this->calculator->lifetime($this->firstFourMonths());

        self::assertSame(2, $summary->customerCount);
        self::assertSame(4, $summary->orderCount);
        self::assertEqualsWithDelta(100.0, $summary->revenue, 0.000001);
        self::assertEqualsWithDelta(50.0, $summary->averageLifetimeValue, 0.000001);
        self::assertEqualsWithDelta(0.5, $summary->repeatRate, 0.000001);
        self::assertEqualsWithDelta(2.0, $summary->averageOrderCount, 0.000001);
        self::assertEqualsWithDelta((60 + 0) / 2, $summary->averageLifespanDays, 0.05, 'Alice stayed 60 days with the shop, the customer of a single order none.');
    }

    public function testTheAmountOfAnOrderIsWhatItsCustomerWasChargedPostageAndDiscountIncluded(): void
    {
        $order = $this->order($this->customer(), '2001-01-10 10:00:00', '33.333333', ['postage' => '4.990000', 'discount' => '3.000000']);
        $tax = 0.0;
        $charged = round($order->getTotalAmount($tax), 2);

        $table = $this->calculator->cohorts($this->firstFourMonths(), CohortGranularity::Month);

        self::assertEqualsWithDelta($charged, $table->rows[0]->cells[0]?->revenue, 0.000001);
        self::assertEqualsWithDelta($charged, $this->calculator->lifetime($this->firstFourMonths())->revenue, 0.000001);
    }

    public function testThePaidScopeLeavesAnUnpaidOrderOut(): void
    {
        $customer = $this->customer();
        $this->order($customer, '2001-01-10 10:00:00', '30.000000', ['status' => OrderStatus::CODE_NOT_PAID]);
        $this->order($customer, '2001-02-10 10:00:00', '10.000000');

        $paid = $this->calculator->cohorts($this->firstFourMonths(StatusScope::Paid), CohortGranularity::Month);
        $all = $this->calculator->cohorts($this->firstFourMonths(StatusScope::All), CohortGranularity::Month);

        self::assertSame(['2001-02'], array_map(static fn (CohortRow $row): string => $row->label, $paid->rows), 'Paid orders only: the first one is the order of February.');
        self::assertSame(['2001-01'], array_map(static fn (CohortRow $row): string => $row->label, $all->rows));
    }

    public function testACancelledOrRefundedOrderCountsInNeitherScopeACustomStatusStandingForOneIncluded(): void
    {
        $cancelledAtTheCounter = $this->factory->orderStatus(['code' => 'cancelled-at-the-counter', 'equivalentCode' => OrderStatus::CODE_CANCELED]);
        $this->forgetTheStatusLists();
        $customer = $this->customer();
        $this->order($customer, '2001-01-10 10:00:00', '30.000000', ['status' => OrderStatus::CODE_CANCELED]);
        $this->order($customer, '2001-01-20 10:00:00', '40.000000', ['status' => $cancelledAtTheCounter->getCode()]);
        $this->order($customer, '2001-02-10 10:00:00', '50.000000', ['status' => OrderStatus::CODE_REFUNDED]);
        $this->order($customer, '2001-03-10 10:00:00', '10.000000');

        try {
            foreach (StatusScope::cases() as $scope) {
                $table = $this->calculator->cohorts($this->firstFourMonths($scope), CohortGranularity::Month);

                self::assertSame(['2001-03'], array_map(static fn (CohortRow $row): string => $row->label, $table->rows), $scope->value.': the first order kept is the order of March.');
                self::assertSame([1, 10.0], [$this->calculator->lifetime($this->firstFourMonths($scope))->orderCount, $this->calculator->lifetime($this->firstFourMonths($scope))->revenue], $scope->value);
            }
        } finally {
            $this->forgetTheStatusLists();
        }
    }

    public function testASaleUnitAdministratorSeesTheCohortsOfTheOrdersOfTheirUnitsOnly(): void
    {
        $saleUnit = $this->factory->saleUnit();
        $this->order($this->customer(), '2001-01-10 10:00:00', '10.000000', ['saleUnit' => $saleUnit->getId()]);
        $this->order($this->customer(), '2001-01-11 10:00:00', '10.000000');

        $scoped = new SalesReportCriteria(new \DateTimeImmutable('2001-01-01 00:00:00'), new \DateTimeImmutable('2001-04-30 23:59:59'), StatusScope::All, [(int) $saleUnit->getId()]);

        self::assertSame(1, $this->calculator->cohorts($scoped, CohortGranularity::Month)->rows[0]->size);
        self::assertSame(1, $this->calculator->lifetime($scoped)->customerCount);
    }

    public function testTheSegmentsPutEachCustomerInOneSegment(): void
    {
        $returning = $this->customer();
        $this->order($returning, '2001-01-10 10:00:00', '30.000000');
        $this->order($returning, '2001-06-10 10:00:00', '20.000000');
        $new = $this->customer();
        $this->order($new, '2001-06-20 10:00:00', '60.000000');
        $sixMonthsAgo = $this->customer();
        $this->order($sixMonthsAgo, '2000-12-25 10:00:00', '40.000000');
        $lastMonth = $this->customer();
        $this->order($lastMonth, '2001-05-20 10:00:00', '50.000000');

        $june = new SalesReportCriteria(new \DateTimeImmutable('2001-06-01 00:00:00'), new \DateTimeImmutable('2001-06-30 23:59:59'));
        $segments = $this->bySegment($this->calculator->segments($june, 180));

        self::assertSame([1, 20.0], [$segments['returning']->customerCount, $segments['returning']->periodRevenue]);
        self::assertSame([1, 60.0], [$segments['new']->customerCount, $segments['new']->periodRevenue]);
        self::assertEqualsWithDelta(0.75, $segments['new']->periodShare, 0.000001);
        self::assertEqualsWithDelta(0.25, $segments['returning']->periodShare, 0.000001);
        self::assertSame([1, 50.0], [$segments['lapsing']->customerCount, $segments['lapsing']->lifetimeRevenue], 'No order in June, the last one within 180 days on June 30: the order of May 20.');
        self::assertSame(1, $segments['dormant']->customerCount, 'Without an order for more than 180 days on June 30: the order of December 25.');
        self::assertSame(40.0, $segments['dormant']->lifetimeRevenue);
        self::assertEqualsWithDelta(40 / 200, $segments['dormant']->lifetimeShare, 0.000001);
        $this->assertSegmentsAddUp($segments, 4);

        $atThirtyDays = $this->bySegment($this->calculator->segments($june, 30));
        self::assertSame([0, 2], [$atThirtyDays['lapsing']->customerCount, $atThirtyDays['dormant']->customerCount], 'At 30 days, the order of May 20 is dormant too.');
        $this->assertSegmentsAddUp($atThirtyDays, 4);
    }

    public function testACustomerWithoutAnOrderForTheThresholdAtTheEndOfALongerPeriodIsDormantEvenIfTheyOrderedInIt(): void
    {
        $february = $this->customer();
        $this->order($february, '2001-02-10 10:00:00', '30.000000');
        $december = $this->customer();
        $this->order($december, '2001-12-10 10:00:00', '10.000000');

        $year = new SalesReportCriteria(new \DateTimeImmutable('2001-01-01 00:00:00'), new \DateTimeImmutable('2001-12-31 23:59:59'));
        $segments = $this->bySegment($this->calculator->segments($year, 180));

        self::assertSame([1, 30.0], [$segments['dormant']->customerCount, $segments['dormant']->periodRevenue], 'Last order on February 10, more than 180 days before December 31: dormant, with what they spent in the year.');
        self::assertSame([1, 10.0], [$segments['new']->customerCount, $segments['new']->periodRevenue], 'Only the customer of December is new.');
        self::assertSame(0, $segments['returning']->customerCount);
        self::assertSame(0, $segments['lapsing']->customerCount);
        $this->assertSegmentsAddUp($segments, 2);
    }

    public function testADormancyThresholdUnderOneDayIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->segments($this->firstFourMonths(), 0);
    }

    /**
     * @param array<string, SegmentRow> $segments
     */
    private function assertSegmentsAddUp(array $segments, int $customers): void
    {
        self::assertSame($customers, array_sum(array_map(static fn (SegmentRow $row): int => $row->customerCount, $segments)), 'Each customer is in one segment.');
        self::assertEqualsWithDelta(1.0, array_sum(array_map(static fn (SegmentRow $row): float => $row->periodShare, $segments)), 0.000001, 'The shares of the revenue of the period add up.');
        self::assertEqualsWithDelta(1.0, array_sum(array_map(static fn (SegmentRow $row): float => $row->lifetimeShare, $segments)), 0.000001, 'The shares of the revenue since the first order add up.');
    }

    private function forgetTheStatusLists(): void
    {
        (new \ReflectionProperty(OrderStatusQuery::class, 'statusIdListsCache'))->setValue(null, []);
    }

    private function firstFourMonths(StatusScope $scope = StatusScope::All): SalesReportCriteria
    {
        return new SalesReportCriteria(new \DateTimeImmutable('2001-01-01 00:00:00'), new \DateTimeImmutable('2001-04-30 23:59:59'), $scope);
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['firstname' => 'Ada']);
    }

    /**
     * An order of one untaxed line. Overrides: `status` (a status code, paid by default), `postage`, `discount`, `saleUnit`.
     *
     * @param array<string, mixed> $overrides
     */
    private function order(Customer $customer, string $at, string $amount, array $overrides = []): Order
    {
        $order = $this->factory->order($customer, [
            'statusCode' => $overrides['status'] ?? OrderStatus::CODE_PAID,
            'postage' => $overrides['postage'] ?? '0.000000',
        ]);
        $order->setCreatedAt(new \DateTime($at));
        $order->setDiscount($overrides['discount'] ?? '0.000000');
        $order->setSaleUnitId($overrides['saleUnit'] ?? null);
        $order->save($this->getPropelConnection());

        $this->factory->orderProduct($order, ['productRef' => 'LIFETIME', 'price' => $amount, 'promoPrice' => $amount, 'quantity' => 1]);

        return $order;
    }

    /**
     * @return list<int|null>
     */
    private function active(CohortRow $row): array
    {
        return array_map(static fn (?CohortCell $cell): ?int => $cell?->activeCustomers, $row->cells);
    }

    /**
     * @return list<float|null>
     */
    private function shares(CohortRow $row): array
    {
        return array_map(static fn (?CohortCell $cell): ?float => $cell?->share, $row->cells);
    }

    /**
     * @return list<float|null>
     */
    private function values(CohortRow $row): array
    {
        return array_map(static fn (?CohortCell $cell): ?float => null === $cell ? null : round($cell->cumulativeValuePerCustomer, 6), $row->cells);
    }

    /**
     * @param list<SegmentRow> $rows
     *
     * @return array<string, SegmentRow>
     */
    private function bySegment(array $rows): array
    {
        self::assertSame(CustomerSegment::cases(), array_map(static fn (SegmentRow $row): CustomerSegment => $row->segment, $rows));

        return array_combine(array_map(static fn (SegmentRow $row): string => $row->segment->value, $rows), $rows);
    }
}
