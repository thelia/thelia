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
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The statements 3.3.0.sql runs to turn the promotional price of an order line back into a number.
 *
 * Thelia 2 created `order_product`.`promo_price` as a VARCHAR(45), and a fresh install makes it a
 * DECIMAL(16,6). The total of an order is computed by SQL, and ROUND() of a text reads it as a
 * floating point number: 63.325 rounds to 63.32, where the DECIMAL rounds it to 63.33, as the cart
 * the buyer paid from does. On a shop updated from Thelia 2 the order of a promotional line ending
 * in a half cent was one cent short.
 *
 * The case puts the column back in its Thelia 2 shape, runs the statements read from the shipped
 * script, then totals an order. A schema change commits on its own, so the transaction that keeps
 * the test database clean opens after the statements and is rolled back before the column gets its
 * fresh install definition again.
 *
 * The orders already placed keep the total of their invoice: before converting the column, the script stores the
 * highest order id in `last_text_promo_price_order_id`, and the orders up to it are totalled with the promotional
 * price read as a text, as they always were.
 */
final class OrderProductPromoPriceMigrationTest extends IntegrationTestCase
{
    private const THRESHOLD = 'last_text_promo_price_order_id';

    protected bool $useTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection()->exec('ALTER TABLE `order_product` MODIFY `promo_price` VARCHAR(45) DEFAULT NULL');
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();

        if ($connection->inTransaction()) {
            $connection instanceof ConnectionWrapper ? $connection->forceRollBack() : $connection->rollBack();
        }

        $connection->exec('ALTER TABLE `order_product` MODIFY `promo_price` DECIMAL(16,6) DEFAULT 0.000000');

        // Outside any transaction, the script commits the variable it stores.
        $connection->exec('DELETE FROM `config` WHERE `name` = '.$connection->quote(self::THRESHOLD));
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testThePromotionalPriceIsADecimalAfterTheScript(): void
    {
        $this->runMigration();

        self::assertSame(['decimal', '16', '6'], $this->columnDefinition());
    }

    public function testAnOrderWithAPromotionalLineEndingInAHalfCentIsTotalledLikeTheCart(): void
    {
        $this->runMigrationThenOpenATransaction();

        $factory = $this->createFixtureFactory();
        $order = $factory->order(null, ['postage' => '9.95']);
        $line = $factory->orderProduct($order, ['price' => '66.658333']);
        $line->setWasInPromo(1)->setPromoPrice('63.325000')->save();

        // 63.325 + 9.95 is 73.275, which the cart of the buyer shows as 73.28.
        self::assertEqualsWithDelta(73.28, $order->getTotalAmount(), 0.0001);
    }

    /**
     * The orders already placed are read again with the new type: the highest of them is stored before the column is
     * converted, and the order created in the transaction is the one the threshold has to reach.
     */
    public function testTheHighestOrderIdIsStoredBeforeTheColumnIsConverted(): void
    {
        $connection = $this->connection();
        $connection->beginTransaction();

        $order = $this->createFixtureFactory()->order();

        foreach ($this->thresholdStatements() as $statement) {
            $connection->exec($statement);
        }

        self::assertSame((string) $order->getId(), $this->storedThreshold());
        self::assertSame(1, $this->storedThresholdSecurity(), 'The variable is hidden and secured, like last_legacy_rounding_order_id.');
    }

    public function testTheThresholdIsKeptWhenTheStatementsAreReplayed(): void
    {
        $connection = $this->connection();
        $connection->beginTransaction();

        $factory = $this->createFixtureFactory();
        $first = $factory->order();

        foreach ($this->thresholdStatements() as $statement) {
            $connection->exec($statement);
        }

        $factory->order();

        foreach ($this->thresholdStatements() as $statement) {
            $connection->exec($statement);
        }

        self::assertSame((string) $first->getId(), $this->storedThreshold(), 'A conversion that stopped halfway keeps the first value.');
    }

    public function testNothingIsStoredWhenThePromotionalPriceIsAlreadyADecimal(): void
    {
        $connection = $this->connection();
        $connection->exec('ALTER TABLE `order_product` MODIFY `promo_price` DECIMAL(16,6) DEFAULT 0.000000');
        $connection->beginTransaction();

        $this->createFixtureFactory()->order();

        foreach ($this->thresholdStatements() as $statement) {
            $connection->exec($statement);
        }

        self::assertNull($this->storedThreshold());
    }

    /**
     * The whole script: the variable is committed with the conversion, and an order placed afterwards is above it.
     */
    public function testTheScriptStoresTheThresholdAndConvertsTheColumn(): void
    {
        $highest = (int) $this->connection()->query('SELECT COALESCE(MAX(`id`), 0) FROM `order`')->fetchColumn();

        $this->runMigration();

        self::assertSame((string) $highest, $this->storedThreshold());
        self::assertSame(['decimal', '16', '6'], $this->columnDefinition());
    }

    /**
     * The statements run on a scratch table of the same shape, so that the rows can hold the texts of a Thelia 2 shop
     * without an order, a customer and the addresses they take.
     */
    public function testTheValuesAreKeptAndAnEmptyTextIsNoPrice(): void
    {
        $connection = $this->connection();
        $connection->exec('DROP TABLE IF EXISTS `order_product_probe`');
        $connection->exec('CREATE TABLE `order_product_probe` (`id` INT NOT NULL PRIMARY KEY, `promo_price` VARCHAR(45) DEFAULT NULL)');
        $connection->exec("INSERT INTO `order_product_probe` (`id`, `promo_price`) VALUES (1, '63.325000'), (2, ''), (3, NULL), (4, '0.000000')");

        try {
            foreach ($this->migrationStatements('order_product_probe') as $statement) {
                $connection->exec($statement);
            }

            self::assertSame(
                [[1, '63.325000'], [2, null], [3, null], [4, '0.000000']],
                $connection->query('SELECT `id`, `promo_price` FROM `order_product_probe` ORDER BY `id`')->fetchAll(\PDO::FETCH_NUM),
            );
            self::assertSame(
                'decimal',
                strtolower((string) $connection->query("SELECT `DATA_TYPE` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_product_probe' AND `COLUMN_NAME` = 'promo_price'")->fetchColumn()),
            );
        } finally {
            $connection->exec('DROP TABLE IF EXISTS `order_product_probe`');
        }
    }

    public function testTheStatementsCanBeReplayed(): void
    {
        $this->runMigration();
        $this->runMigration();

        self::assertSame(['decimal', '16', '6'], $this->columnDefinition());
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function columnDefinition(): array
    {
        $definition = $this->connection()
            ->query("SELECT `DATA_TYPE`, `NUMERIC_PRECISION`, `NUMERIC_SCALE` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_product' AND `COLUMN_NAME` = 'promo_price'")
            ->fetch(\PDO::FETCH_NUM);

        self::assertIsArray($definition);

        return [strtolower((string) $definition[0]), (string) $definition[1], (string) $definition[2]];
    }

    /**
     * The statements that store the threshold: the detection of the column and the four of the INSERT, none of them
     * changes the schema, so that a transaction can hold them.
     *
     * @return list<string>
     */
    private function thresholdStatements(): array
    {
        $statements = \array_slice($this->migrationStatements(), 0, 5);

        self::assertStringContainsString(self::THRESHOLD, $statements[1], 'The script does not store the threshold before converting the column.');

        return $statements;
    }

    private function storedThreshold(): ?string
    {
        $rows = $this->connection()
            ->query('SELECT `value` FROM `config` WHERE `name` = '.$this->connection()->quote(self::THRESHOLD))
            ->fetchAll(\PDO::FETCH_COLUMN);

        return [] === $rows ? null : (string) $rows[0];
    }

    private function storedThresholdSecurity(): int
    {
        return (int) $this->connection()->query('SELECT `secured` + `hidden` - 1 FROM `config` WHERE `name` = '.$this->connection()->quote(self::THRESHOLD))->fetchColumn();
    }

    private function runMigrationThenOpenATransaction(): void
    {
        $this->runMigration();
        $this->connection()->beginTransaction();
    }

    private function runMigration(): void
    {
        foreach ($this->migrationStatements() as $statement) {
            $this->connection()->exec($statement);
        }
    }

    /**
     * The statements of the script that convert the promotional price, optionally aimed at another table.
     *
     * @return list<string>
     */
    private function migrationStatements(string $table = 'order_product'): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.3.0.sql');

        $statements = [];

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (!str_contains($sql, 'convert_column')) {
                continue;
            }

            // The threshold is read from `order` and written to `config`, whatever the table the column is on: the
            // scratch table of the probe must not write a variable the real shop would read.
            if ('order_product' !== $table && str_contains($sql, self::THRESHOLD)) {
                $sql = "SET @statement := 'DO 0'";
            }

            $statements[] = str_replace('`order_product`', "`$table`", str_replace("'order_product'", "'$table'", $sql));
        }

        self::assertCount(13, $statements, 'The 3.3.0 script does not convert the promotional price of an order line.');

        return $statements;
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(OrderProductTableMap::DATABASE_NAME);
    }
}
