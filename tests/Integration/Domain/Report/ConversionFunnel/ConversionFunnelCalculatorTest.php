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

namespace Thelia\Tests\Integration\Domain\Report\ConversionFunnel;

use Thelia\Domain\Report\ConversionFunnel\ConversionFunnel;
use Thelia\Domain\Report\ConversionFunnel\ConversionFunnelCalculator;
use Thelia\Domain\Report\ConversionFunnel\FunnelStep;
use Thelia\Model\Cart;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The six counts of the conversion funnel, read on a test database that already holds
 * demo data: every case asserts the delta its fixtures bring over a baseline read first.
 */
final class ConversionFunnelCalculatorTest extends IntegrationTestCase
{
    private ConversionFunnelCalculator $calculator;

    private FixtureFactory $factory;

    private \DateTimeImmutable $from;

    private \DateTimeImmutable $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new ConversionFunnelCalculator($this->getPropelConnection());
        $this->factory = $this->createFixtureFactory();
        $this->from = (new \DateTimeImmutable('-29 days'))->setTime(0, 0);
        $this->to = (new \DateTimeImmutable('today'))->setTime(23, 59, 59);
    }

    public function testAnEmptyCartIsOnlyCreated(): void
    {
        $baseline = $this->counts();

        $this->factory->cart();

        $this->assertDelta($baseline, [1, 0, 0, 0, 0, 0]);
    }

    public function testACartWithALineHasItems(): void
    {
        $baseline = $this->counts();

        $this->cartWithALine();

        $this->assertDelta($baseline, [1, 1, 0, 0, 0, 0]);
    }

    public function testACartWithALineAndADeliveryModuleReachesTheDeliveryStep(): void
    {
        $baseline = $this->counts();

        $this->cartWithALine()->setDeliveryModuleId($this->moduleId('CustomDelivery'))->save($this->getPropelConnection());

        $this->assertDelta($baseline, [1, 1, 1, 0, 0, 0]);
    }

    public function testACartWithALineADeliveryAndAPaymentModuleReachesThePaymentStep(): void
    {
        $baseline = $this->counts();

        $this->cartWithALine()
            ->setDeliveryModuleId($this->moduleId('CustomDelivery'))
            ->setPaymentModuleId($this->moduleId('Cheque'))
            ->save($this->getPropelConnection());

        $this->assertDelta($baseline, [1, 1, 1, 1, 0, 0]);
    }

    public function testACartWithoutALineReachesNoStepEvenWithItsModulesChosen(): void
    {
        $baseline = $this->counts();

        $this->factory->cart()
            ->setDeliveryModuleId($this->moduleId('CustomDelivery'))
            ->setPaymentModuleId($this->moduleId('Cheque'))
            ->save($this->getPropelConnection());

        $this->assertDelta($baseline, [1, 0, 0, 0, 0, 0]);
    }

    public function testALineOfferedByAPromotionIsNotAnItem(): void
    {
        $baseline = $this->counts();

        $this->cartWithALine(['isOffered' => 1]);

        $this->assertDelta($baseline, [1, 0, 0, 0, 0, 0]);
    }

    public function testAPaidOrderIsCreatedAndPaid(): void
    {
        $baseline = $this->counts();

        // order() creates its own empty cart, which counts as a created cart.
        $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);

        $this->assertDelta($baseline, [1, 0, 0, 0, 1, 1]);
    }

    public function testAnUnpaidOrderIsOnlyCreated(): void
    {
        $baseline = $this->counts();

        $this->factory->order();

        $this->assertDelta($baseline, [1, 0, 0, 0, 1, 0]);
    }

    public function testAnOrderInAStatusOfTheShopThatStandsForSentIsPaid(): void
    {
        $baseline = $this->counts();

        $status = $this->factory->orderStatus(['code' => 'test_shipped', 'equivalentCode' => OrderStatus::CODE_SENT]);
        $this->factory->order(null, ['statusCode' => (string) $status->getCode()]);

        $this->assertDelta($baseline, [1, 0, 0, 0, 1, 1]);
    }

    public function testACartCreatedBeforeTheWindowIsLeftOut(): void
    {
        $baseline = $this->counts();

        $this->cartWithALine()
            ->setDeliveryModuleId($this->moduleId('CustomDelivery'))
            ->setPaymentModuleId($this->moduleId('Cheque'))
            ->setCreatedAt(new \DateTime('-2 years'))
            ->save($this->getPropelConnection());

        $this->assertDelta($baseline, [0, 0, 0, 0, 0, 0]);
    }

    public function testTheDailyRowsAddUpToTheTotalOverContiguousDays(): void
    {
        $this->cartWithALine()->setDeliveryModuleId($this->moduleId('CustomDelivery'))->save($this->getPropelConnection());
        $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);

        $total = $this->calculator->total($this->from, $this->to);
        $rows = $this->calculator->daily($this->from, $this->to);

        self::assertCount(30, $rows);

        $expectedDay = $this->from;
        foreach ($rows as $row) {
            self::assertSame($expectedDay->format('Y-m-d'), $row->day->format('Y-m-d'));
            $expectedDay = $expectedDay->modify('+1 day');
        }

        $sums = [0, 0, 0, 0, 0, 0];
        foreach ($rows as $row) {
            $sums[0] += $row->cartsCreated;
            $sums[1] += $row->cartsWithItems;
            $sums[2] += $row->cartsWithDelivery;
            $sums[3] += $row->cartsWithPayment;
            $sums[4] += $row->ordersCreated;
            $sums[5] += $row->ordersPaid;
        }

        self::assertSame($this->countsOf($total), $sums);
        self::assertGreaterThanOrEqual(1, $rows[29]->cartsWithDelivery, 'Today\'s row holds the cart just created.');
        self::assertGreaterThanOrEqual(1, $rows[29]->ordersPaid, 'Today\'s row holds the order just paid.');
    }

    /**
     * @param array<string, mixed> $lineOverrides
     */
    private function cartWithALine(array $lineOverrides = []): Cart
    {
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
        ), null, $lineOverrides);

        return $cart;
    }

    private function moduleId(string $code): int
    {
        $module = ModuleQuery::create()->findOneByCode($code)
            ?? throw new \RuntimeException(\sprintf('Module %s is not installed: run bin/test-prepare.', $code));

        return (int) $module->getId();
    }

    /**
     * @return list<int>
     */
    private function counts(): array
    {
        return $this->countsOf($this->calculator->total($this->from, $this->to));
    }

    /**
     * @return list<int>
     */
    private function countsOf(ConversionFunnel $funnel): array
    {
        return array_map(static fn (FunnelStep $step): int => $step->count, $funnel->steps);
    }

    /**
     * @param list<int> $baseline
     * @param list<int> $delta
     */
    private function assertDelta(array $baseline, array $delta): void
    {
        $expected = array_map(static fn (int $before, int $added): int => $before + $added, $baseline, $delta);

        self::assertSame(
            array_combine($this->stepKeys(), $expected),
            array_combine($this->stepKeys(), $this->counts()),
        );
    }

    /**
     * @return list<string>
     */
    private function stepKeys(): array
    {
        return [
            FunnelStep::CARTS_CREATED,
            FunnelStep::CARTS_WITH_ITEMS,
            FunnelStep::CARTS_WITH_DELIVERY,
            FunnelStep::CARTS_WITH_PAYMENT,
            FunnelStep::ORDERS_CREATED,
            FunnelStep::ORDERS_PAID,
        ];
    }
}
