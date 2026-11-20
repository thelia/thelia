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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Model\Map\SaleUnitProductSaleElementsTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The low stock threshold of a sale unit, added by the 3.5.0 update script.
 *
 * Each case takes the table back to the shape of a 3.4 shop, runs the block read
 * from the shipped script, and compares the columns with the fresh install they
 * were taken from. A schema change commits on its own, so no transaction wraps
 * the cases; the block runs again afterwards to put the fresh install shape back.
 */
final class SaleUnitLowStockThresholdMigrationTest extends IntegrationTestCase
{
    private const SCRIPT = '3.5.0.sql';
    private const BLOCK_TITLE = '-- Sale units: low stock threshold';
    private const ORPHAN_ID = 2147483000;

    protected bool $useTransaction = false;

    /** @var list<array<string, mixed>> */
    private array $freshInstallShape = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->freshInstallShape = $this->shape();
        $this->takeTheTableBackToA34Shop();
    }

    protected function tearDown(): void
    {
        $this->runBlock();

        parent::tearDown();
    }

    public function testTheBlockBuildsWhatTheFreshInstallHolds(): void
    {
        $this->runBlock();

        self::assertSame($this->freshInstallShape, $this->shape());
    }

    public function testTheBlockCanBeReplayed(): void
    {
        $this->runBlock();
        $this->runBlock();

        self::assertSame($this->freshInstallShape, $this->shape());
    }

    public function testTheStockOfAnExistingSaleUnitIsKeptAndHasNoThresholdOfItsOwn(): void
    {
        // A bare row: the generated model already carries the column this case removes.
        $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->connection()->exec(\sprintf('INSERT INTO `sale_unit_product_sale_elements` (`sale_unit_id`, `product_sale_elements_id`, `quantity`) VALUES (%d, %d, 12.5)', self::ORPHAN_ID, self::ORPHAN_ID));
        $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 1');

        try {
            $this->runBlock();

            $row = $this->connection()->query(\sprintf('SELECT `quantity`, `low_stock_threshold` FROM `sale_unit_product_sale_elements` WHERE `sale_unit_id` = %d', self::ORPHAN_ID))->fetch(\PDO::FETCH_ASSOC);

            self::assertSame(['quantity' => '12.500000', 'low_stock_threshold' => null], $row);
        } finally {
            $this->connection()->exec(\sprintf('DELETE FROM `sale_unit_product_sale_elements` WHERE `sale_unit_id` = %d', self::ORPHAN_ID));
        }
    }

    private function takeTheTableBackToA34Shop(): void
    {
        $this->connection()->exec('ALTER TABLE `sale_unit_product_sale_elements` DROP COLUMN IF EXISTS `low_stock_threshold`');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function shape(): array
    {
        return $this->connection()->query(
            "SELECT `COLUMN_NAME`, `ORDINAL_POSITION`, `COLUMN_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT`, `COLUMN_COMMENT` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'sale_unit_product_sale_elements' ORDER BY `ORDINAL_POSITION`"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function runBlock(): void
    {
        foreach ($this->blockStatements() as $statement) {
            $this->connection()->exec($statement);
        }
    }

    /**
     * @return list<string>
     */
    private function blockStatements(): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.self::SCRIPT);
        $start = strpos($script, self::BLOCK_TITLE);
        self::assertIsInt($start, 'The update script has no such block: '.self::BLOCK_TITLE);

        // The block runs from the end of its header to the header of the next block, or to the end of the script.
        $headerEnd = strpos($script, "\n-- ----", $start);
        self::assertIsInt($headerEnd);
        $end = strpos($script, "\n-- ----", $headerEnd + 1);
        $end = false !== $end ? $end : strpos($script, 'SET FOREIGN_KEY_CHECKS = 1', $headerEnd);
        self::assertIsInt($end);

        $statements = [];

        foreach (explode(";\n", substr($script, $headerEnd + 1, $end - $headerEnd - 1)) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if ('' !== $sql) {
                $statements[] = $sql;
            }
        }

        self::assertNotEmpty($statements);

        return $statements;
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(SaleUnitProductSaleElementsTableMap::DATABASE_NAME);
    }
}
