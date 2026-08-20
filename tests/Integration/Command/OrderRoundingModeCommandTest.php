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

namespace Thelia\Tests\Integration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * Turning the rounding mode on without the pivot restates the total of every
 * order already invoiced, and nothing in Thelia used to write that pivot. The
 * command exists so the two writes cannot be separated.
 */
final class OrderRoundingModeCommandTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        // ConfigQuery caches in a static array the transaction rollback cannot
        // reach, so a mode written here would answer the next test.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testItShowsTheCurrentStateWithoutWritingAnything(): void
    {
        $tester = $this->runCommand([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('sum-of-roundings', $tester->getDisplay());
        self::assertSame(ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS, ConfigQuery::getOrderRoundingMode());
        self::assertSame('0', (string) ConfigQuery::read('last_sum_of_roundings_order_id', 0));
    }

    public function testOptingInWritesThePivotAlongsideTheMode(): void
    {
        $maximumOrderId = $this->maximumOrderId();

        $tester = $this->runCommand(['mode' => 'rounding-of-sums']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS, ConfigQuery::getOrderRoundingMode());
        self::assertSame(
            $maximumOrderId,
            (int) ConfigQuery::read('last_sum_of_roundings_order_id', 0),
            'The pivot must name the last order placed before the switch.',
        );
    }

    public function testAnOrderPlacedBeforeTheSwitchStillReadsAsSumOfRoundings(): void
    {
        $order = $this->createFixtureFactory()->order();

        $this->runCommand(['mode' => 'rounding-of-sums']);

        self::assertSame(
            ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS,
            ConfigQuery::getOrderRoundingMode((int) $order->getId()),
        );
        self::assertSame(
            ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS,
            ConfigQuery::getOrderRoundingMode((int) $order->getId() + 1),
        );
    }

    public function testSwitchingBackLeavesThePivotInPlace(): void
    {
        $this->runCommand(['mode' => 'rounding-of-sums']);
        $pivot = (int) ConfigQuery::read('last_sum_of_roundings_order_id', 0);

        $this->runCommand(['mode' => 'sum-of-roundings']);

        self::assertSame(ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS, ConfigQuery::getOrderRoundingMode());
        self::assertSame($pivot, (int) ConfigQuery::read('last_sum_of_roundings_order_id', 0));
    }

    public function testAnUnknownModeIsRefusedWithoutWritingAnything(): void
    {
        $tester = $this->runCommand(['mode' => 'nearest-guess']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Unknown mode', $tester->getDisplay());
        self::assertSame(ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS, ConfigQuery::getOrderRoundingMode());
        self::assertSame('0', (string) ConfigQuery::read('last_sum_of_roundings_order_id', 0));
    }

    public function testDecliningTheConfirmationWritesNothing(): void
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('thelia:order:rounding-mode'),
        );
        $tester->setInputs(['no']);
        $tester->execute(['mode' => 'rounding-of-sums']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS, ConfigQuery::getOrderRoundingMode());
        self::assertSame('0', (string) ConfigQuery::read('last_sum_of_roundings_order_id', 0));
    }

    /**
     * @param array<string, string> $arguments
     */
    private function runCommand(array $arguments): CommandTester
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('thelia:order:rounding-mode'),
        );
        $tester->execute($arguments, ['interactive' => false]);

        return $tester;
    }

    private function maximumOrderId(): int
    {
        return (int) (OrderQuery::create()
            ->withColumn('MAX('.OrderTableMap::COL_ID.')', 'max_order_id')
            ->select(['max_order_id'])
            ->findOne() ?? 0);
    }
}
