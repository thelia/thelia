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

namespace Thelia\Tests\Integration\Install;

use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The statement 3.4.0.sql runs to keep the orders already invoiced on the total they were
 * invoiced with, once the line totals are rounded as decimals under the rounding of sums:
 * it writes the highest order id in last_float_line_rounding_order_id, and a second run
 * leaves it where the first one put it.
 */
final class FloatLineRoundingPivotMigrationTest extends IntegrationTestCase
{
    private const KEY = 'last_float_line_rounding_order_id';

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheUpdateWritesTheLastOrderPlacedAndAReplayLeavesIt(): void
    {
        $factory = $this->createFixtureFactory();
        $this->getPropelConnection()->exec("DELETE FROM `config` WHERE `name` = '".self::KEY."'");
        $invoiced = $factory->order();

        $this->runTheStatement();
        $placedAfter = $factory->order();
        $this->runTheStatement();

        self::assertGreaterThan($invoiced->getId(), $placedAfter->getId());
        self::assertSame((string) $invoiced->getId(), $this->pivot());
    }

    public function testANewShopProtectsNoOrder(): void
    {
        $insert = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'insert.sql.tpl');

        self::assertMatchesRegularExpression("/\\(\\d+, '".self::KEY."', '0', 1, 1, NOW\\(\\), NOW\\(\\)\\)/", $insert);
    }

    private function runTheStatement(): void
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.4.0.sql');
        $statements = [];

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (str_contains($sql, self::KEY)) {
                $statements[] = $sql;
            }
        }

        self::assertCount(1, $statements, 'The 3.4.0 script does not write the pivot of the line totals.');
        $this->getPropelConnection()->exec($statements[0]);
    }

    private function pivot(): ?string
    {
        $value = $this->getPropelConnection()
            ->query("SELECT `value` FROM `config` WHERE `name` = '".self::KEY."'")
            ->fetchColumn();

        return false === $value ? null : (string) $value;
    }
}
