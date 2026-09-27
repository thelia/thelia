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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Administrator\AdministratorEvent;
use Thelia\Core\Event\Administrator\AdministratorUpdatePasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;
use Thelia\Model\Map\AdminTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The statement 3.2.0.sql runs to let `admin`.`password_renew_token` be NULL on a shop
 * upgraded from Thelia 2.
 *
 * 2.3.0-alpha2.sql added the column as NOT NULL without a default, while the fresh install
 * creates it nullable, and no later script aligned the two. On such a shop, creating an
 * administrator leaves the column out of the INSERT and changing a password writes NULL
 * into it, and the database refused both.
 *
 * Each case puts the column back in its upgraded definition, runs the statement read from
 * the shipped script, then goes through the real listeners. A schema change commits on its
 * own, so the transaction that keeps the test database clean opens after the statement and
 * is rolled back before the column gets its fresh install definition again.
 */
final class AdminPasswordRenewTokenMigrationTest extends IntegrationTestCase
{
    private const UPGRADED_DEFINITION = 'ALTER TABLE `admin` MODIFY `password_renew_token` VARCHAR(255) NOT NULL';

    private const FRESH_INSTALL_DEFINITION = 'ALTER TABLE `admin` MODIFY `password_renew_token` VARCHAR(255) NULL';

    protected bool $useTransaction = false;

    /**
     * The administrators the test database held without a token: the upgraded definition
     * does not accept them, so they carry an empty token while a case runs.
     *
     * @var list<int>
     */
    private array $administratorsWithoutToken = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (AdminQuery::create()->filterByPasswordRenewToken(null, Criteria::ISNULL)->find() as $administrator) {
            $this->administratorsWithoutToken[] = (int) $administrator->getId();
        }

        if ([] !== $this->administratorsWithoutToken) {
            AdminQuery::create()->filterById($this->administratorsWithoutToken)->update(['PasswordRenewToken' => '']);
        }

        $this->connection()->exec(self::UPGRADED_DEFINITION);
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();

        if ($connection->inTransaction()) {
            $connection instanceof ConnectionWrapper ? $connection->forceRollBack() : $connection->rollBack();
        }

        $this->connection()->exec(self::FRESH_INSTALL_DEFINITION);

        if ([] !== $this->administratorsWithoutToken) {
            AdminQuery::create()->filterById($this->administratorsWithoutToken)->update(['PasswordRenewToken' => null]);
        }

        AdminTableMap::clearInstancePool();

        parent::tearDown();
    }

    public function testAnAdministratorCanBeCreatedOnceTheScriptHasRun(): void
    {
        $this->runMigration();
        $this->connection()->beginTransaction();

        $event = (new AdministratorEvent())
            ->setFirstname('Upgraded')
            ->setLastname('Shop')
            ->setLogin('upgraded-'.bin2hex(random_bytes(4)))
            ->setEmail('upgraded-'.bin2hex(random_bytes(4)).'@example.com')
            ->setPassword('a-password-long-enough')
            ->setProfile(null)
            ->setLocale('en_US');

        $this->dispatcher()->dispatch($event, TheliaEvents::ADMINISTRATOR_CREATE);

        $administrator = $event->getAdministrator();
        self::assertInstanceOf(Admin::class, $administrator);

        self::assertNull(AdminQuery::create()->findPk($administrator->getId())?->getPasswordRenewToken());
    }

    public function testAPasswordCanBeChangedOnceTheScriptHasRun(): void
    {
        $this->runMigration();
        $this->connection()->beginTransaction();

        // An administrator as an upgraded shop stores it: an empty token rather than none.
        $administrator = (new Admin())
            ->setFirstname('Upgraded')
            ->setLastname('Shop')
            ->setLogin('upgraded-'.bin2hex(random_bytes(4)))
            ->setEmail('upgraded-'.bin2hex(random_bytes(4)).'@example.com')
            ->setPassword('a-password-long-enough')
            ->setLocale('en_US')
            ->setPasswordRenewToken('');
        $administrator->save();

        $event = (new AdministratorUpdatePasswordEvent($administrator))->setPassword('a-new-password-long-enough');
        $this->dispatcher()->dispatch($event, TheliaEvents::ADMINISTRATOR_UPDATEPASSWORD);

        $reloaded = AdminQuery::create()->findPk($administrator->getId());
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getPasswordRenewToken());
        self::assertTrue(password_verify('a-new-password-long-enough', (string) $reloaded->getPassword()));
    }

    public function testTheStatementCanBeReplayed(): void
    {
        $this->runMigration();
        $this->runMigration();

        $nullable = $this->connection()
            ->query("SELECT `IS_NULLABLE` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'admin' AND `COLUMN_NAME` = 'password_renew_token'")
            ->fetchColumn();

        self::assertSame('YES', $nullable);
    }

    private function runMigration(): void
    {
        foreach ($this->migrationStatements() as $statement) {
            $this->connection()->exec($statement);
        }

        AdminTableMap::clearInstancePool();
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

            if (preg_match('/^ALTER\s+TABLE\s+`admin`/i', $sql)) {
                $statements[] = $sql;
            }
        }

        self::assertCount(1, $statements, 'The 3.2.0 script does not let admin.password_renew_token be NULL.');

        return $statements;
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(AdminTableMap::DATABASE_NAME);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getService('event_dispatcher');

        return $dispatcher;
    }
}
