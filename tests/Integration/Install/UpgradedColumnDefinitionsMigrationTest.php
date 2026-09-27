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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Folder\FolderCreateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Map\FolderTableMap;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\State;
use Thelia\Model\StateQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The statements 3.2.0.sql runs to give the columns a shop upgraded from Thelia 2 still
 * carries in their Thelia 2 shape the definition of the fresh install.
 *
 * The Thelia 2 scripts created them NOT NULL without a default, and a DATE for the invoice
 * date. Propel leaves out of an INSERT every column it has nothing to write, the model
 * default included, so on such a shop the database refused a folder created at the root, and
 * a coupon, a currency, a state or an order status saved without the optional value. The
 * invoice date lost its time on every write.
 *
 * Each case puts the columns back in their upgraded definition, runs the statements read from
 * the shipped script, then saves through the model. A schema change commits on its own, so the
 * transaction that keeps the test database clean opens after the statements and is rolled back
 * before the columns get their fresh install definition again.
 */
final class UpgradedColumnDefinitionsMigrationTest extends IntegrationTestCase
{
    /**
     * The upgraded and the fresh install definition of every column the script realigns.
     */
    private const COLUMNS = [
        ['folder', 'parent', 'INTEGER NOT NULL', 'INTEGER DEFAULT 0 NOT NULL'],
        ['folder_version', 'parent', 'INTEGER NOT NULL', 'INTEGER DEFAULT 0 NOT NULL'],
        ['coupon', 'expiration_date', 'DATETIME NOT NULL', 'DATETIME NULL'],
        ['coupon_version', 'expiration_date', 'DATETIME NOT NULL', 'DATETIME NULL'],
        ['currency', 'format', 'CHAR(10) NOT NULL', 'CHAR(10) NULL'],
        ['state', 'isocode', 'VARCHAR(4) NOT NULL', 'VARCHAR(4) NULL'],
        ['order_status', 'color', 'CHAR(7) NOT NULL', 'CHAR(7) NULL'],
        ['order_status', 'position', 'INTEGER NOT NULL', 'INTEGER NULL'],
        ['order', 'invoice_date', 'DATE NULL', 'DATETIME NULL'],
        ['order_version', 'invoice_date', 'DATE NULL', 'DATETIME NULL'],
    ];

    protected bool $useTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::COLUMNS as [$table, $column, $upgradedDefinition]) {
            $this->connection()->exec(\sprintf('ALTER TABLE `%s` MODIFY `%s` %s', $table, $column, $upgradedDefinition));
        }
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();

        if ($connection->inTransaction()) {
            $connection instanceof ConnectionWrapper ? $connection->forceRollBack() : $connection->rollBack();
        }

        foreach (self::COLUMNS as [$table, $column, , $freshInstallDefinition]) {
            $connection->exec(\sprintf('ALTER TABLE `%s` MODIFY `%s` %s', $table, $column, $freshInstallDefinition));
        }

        parent::tearDown();
    }

    public function testAFolderCanBeCreatedAtTheRoot(): void
    {
        $this->runMigrationThenOpenATransaction();

        $event = (new FolderCreateEvent())
            ->setParent(0)
            ->setVisible(1)
            ->setLocale('en_US')
            ->setTitle('Upgraded shop folder');

        $this->dispatcher()->dispatch($event, TheliaEvents::FOLDER_CREATE);

        self::assertSame(0, FolderQuery::create()->findPk($event->getFolder()?->getId())?->getParent());
    }

    public function testACouponCanBeSavedWithoutExpirationDate(): void
    {
        $this->runMigrationThenOpenATransaction();

        $coupon = (new Coupon())
            ->setCode('UPGRADED-'.bin2hex(random_bytes(4)))
            ->setType('thelia.coupon.type.remove_x_amount')
            ->setSerializedEffects('{"amount":5}')
            ->setIsEnabled(true)
            ->setMaxUsage(Coupon::UNLIMITED_COUPON_USE)
            ->setIsCumulative(false)
            ->setIsRemovingPostage(false)
            ->setIsAvailableOnSpecialOffers(false)
            ->setIsUsed(false)
            ->setPerCustomerUsageCount(false)
            ->setSerializedConditions('')
            ->setLocale('en_US')
            ->setTitle('Upgraded shop coupon')
            ->setShortDescription('')
            ->setDescription('');
        $coupon->save();

        self::assertNull(CouponQuery::create()->findPk($coupon->getId())?->getExpirationDate());
    }

    public function testACurrencyCanBeSavedWithoutFormat(): void
    {
        $this->runMigrationThenOpenATransaction();

        $currency = (new Currency())
            ->setCode('XTS')
            ->setSymbol('¤')
            ->setRate(1.0)
            ->setVisible(0)
            ->setByDefault(0);
        $currency->save();

        self::assertNull(CurrencyQuery::create()->findPk($currency->getId())?->getFormat());
    }

    public function testAStateCanBeSavedWithoutIsoCode(): void
    {
        $this->runMigrationThenOpenATransaction();

        $state = (new State())
            ->setCountryId((int) $this->createFixtureFactory()->country()->getId())
            ->setVisible(1)
            ->setLocale('en_US')
            ->setTitle('Upgraded shop state');
        $state->save();

        self::assertNull(StateQuery::create()->findPk($state->getId())?->getIsocode());
    }

    public function testAnOrderStatusCanBeSavedWithoutColorNorPosition(): void
    {
        $this->runMigrationThenOpenATransaction();

        $status = (new OrderStatus())
            ->setCode('upgraded-'.bin2hex(random_bytes(4)))
            ->setLocale('en_US')
            ->setTitle('Upgraded shop status');
        $status->save();

        $reloaded = OrderStatusQuery::create()->findPk($status->getId());
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getColor());
        self::assertNull($reloaded->getPosition());
    }

    public function testTheInvoiceDateKeepsItsTime(): void
    {
        $this->runMigrationThenOpenATransaction();

        $order = $this->createFixtureFactory()->order();
        $order->setInvoiceDate(new \DateTime('2026-09-27 14:32:05'))->save();

        self::assertSame(
            '2026-09-27 14:32:05',
            OrderQuery::create()->findPk($order->getId())?->getInvoiceDate()?->format('Y-m-d H:i:s'),
        );
    }

    public function testTheStatementsCanBeReplayed(): void
    {
        $this->runMigration();
        $this->runMigration();

        foreach (self::COLUMNS as [$table, $column, , $freshInstallDefinition]) {
            $definition = $this->connection()
                ->query(\sprintf(
                    "SELECT `DATA_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '%s' AND `COLUMN_NAME` = '%s'",
                    $table,
                    $column,
                ))
                ->fetch(\PDO::FETCH_ASSOC);

            self::assertIsArray($definition);
            self::assertStringStartsWith(strtolower($definition['DATA_TYPE']), strtolower($freshInstallDefinition), "$table.$column");
            self::assertSame(str_contains($freshInstallDefinition, 'NOT NULL') ? 'NO' : 'YES', $definition['IS_NULLABLE'], "$table.$column");
            self::assertSame(str_contains($freshInstallDefinition, 'DEFAULT 0'), '0' === $definition['COLUMN_DEFAULT'], "$table.$column");
        }
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
     * @return list<string>
     */
    private function migrationStatements(): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.2.0.sql');

        $statements = [];

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (preg_match('/^ALTER\s+TABLE\s+`(folder|folder_version|coupon|coupon_version|currency|state|order_status|order|order_version)`\s+MODIFY/i', $sql)) {
                $statements[] = $sql;
            }
        }

        self::assertCount(9, $statements, 'The 3.2.0 script does not give the upgraded columns their fresh install definition.');

        return $statements;
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(FolderTableMap::DATABASE_NAME);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getService('event_dispatcher');

        return $dispatcher;
    }
}
