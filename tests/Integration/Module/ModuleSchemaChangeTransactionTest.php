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

namespace Thelia\Tests\Integration\Module;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\ModuleManagement;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\TestToken;

/**
 * MySQL and MariaDB commit the open transaction before a schema change. A module that alters
 * a table from update(), an activation or deactivation method or destroy() ends the
 * transaction the core opened around the call, and Propel kept counting it: every later
 * transaction of the process committed each statement on its own and rolled nothing back.
 *
 * Each case runs the lifecycle of a sample module, then checks what the rest of the process
 * depends on: a transaction opened afterwards keeps its writes to itself, rollBack() takes
 * them back and commit() makes them last.
 */
final class ModuleSchemaChangeTransactionTest extends ActionIntegrationTestCase
{
    // The lifecycle under test commits on purpose: no transaction of the test case around
    // it, the test cleans up by hand on both ends.
    protected bool $useTransaction = false;

    private const string SAMPLE_PREFIX = 'SchemaChange';

    // A temporary table: the ALTER TABLE ends the transaction as on any table, and leaves
    // nothing behind in the database.
    private const string ALTER_A_TABLE = "DROP TEMPORARY TABLE IF EXISTS schema_change_sample;\n"
        ."CREATE TEMPORARY TABLE schema_change_sample (id INT NOT NULL);\n"
        ."ALTER TABLE schema_change_sample ADD COLUMN label VARCHAR(16) NULL;\n";

    private const string FAILING_STATEMENT = "INSERT INTO schema_change_missing_table (id) VALUES (1);\n";

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->removeSampleModule();
    }

    protected function tearDown(): void
    {
        $this->rollBackWhatTheTestLeftOpen();
        $this->removeSampleModule();

        parent::tearDown();
    }

    public function testAnUpdateThatAltersATableLeavesTheTransactionsOfTheProcessWorking(): void
    {
        $this->installSampleModule('1.0.0');

        $this->installSampleModule('2.0.0', ['update.sql' => self::ALTER_A_TABLE]);

        self::assertTheModuleEndedTheTransaction('update');
        $this->assertTheTransactionsOfTheProcessWork();
    }

    public function testAnUpdateThatAltersATableInATransactionOfItsOwnLeavesTheTransactionsOfTheProcessWorking(): void
    {
        $this->installSampleModule('1.0.0');

        $this->installSampleModule('2.0.0', [
            'update.sql' => self::ALTER_A_TABLE,
            'update.own-transaction' => '',
        ]);

        self::assertTheModuleEndedTheTransaction('update');
        $this->assertTheTransactionsOfTheProcessWork();
    }

    public function testAnUpdateFailingAfterAnAlterReportsTheErrorOfTheScriptAndLeavesTheTransactionsWorking(): void
    {
        $this->installSampleModule('1.0.0');

        try {
            $this->installSampleModule('2.0.0', ['update.sql' => self::ALTER_A_TABLE.self::FAILING_STATEMENT]);

            self::fail('The update script fails on its last statement.');
        } catch (\PDOException|\RuntimeException $failure) {
            self::assertStringContainsString(
                'schema_change_missing_table',
                $failure->getMessage(),
                'The error the caller gets is the one of the statement that failed.',
            );
        }

        self::assertTheModuleEndedTheTransaction('update');
        $this->assertTheTransactionsOfTheProcessWork();
    }

    public function testAnActivationThatAltersATableLeavesTheTransactionsOfTheProcessWorking(): void
    {
        $module = $this->installSampleModule('1.0.0', ['postActivation.sql' => self::ALTER_A_TABLE]);

        $this->dispatch(new ModuleToggleActivationEvent($module->getId()), TheliaEvents::MODULE_TOGGLE_ACTIVATION);

        self::assertSame(
            BaseModule::IS_ACTIVATED,
            ModuleQuery::create()->findPk($module->getId())?->getActivate(),
            'The module is activated.',
        );
        self::assertTheModuleEndedTheTransaction('postActivation');
        $this->assertTheTransactionsOfTheProcessWork();
    }

    public function testAPreActivationThatAltersATableLeavesTheTransactionsOfTheProcessWorking(): void
    {
        $module = $this->installSampleModule('1.0.0', ['preActivation.sql' => self::ALTER_A_TABLE]);

        $this->dispatch(new ModuleToggleActivationEvent($module->getId()), TheliaEvents::MODULE_TOGGLE_ACTIVATION);

        self::assertSame(
            BaseModule::IS_ACTIVATED,
            ModuleQuery::create()->findPk($module->getId())?->getActivate(),
            'The module is activated.',
        );
        self::assertTheModuleEndedTheTransaction('preActivation');
        $this->assertTheTransactionsOfTheProcessWork();
    }

    public function testADeactivationThatAltersATableLeavesTheTransactionsOfTheProcessWorking(): void
    {
        $module = $this->installSampleModule('1.0.0', ['postDeactivation.sql' => self::ALTER_A_TABLE]);
        // Marked active in the database: the deactivation is the step under test, an
        // activation would rebuild the Propel models for nothing.
        ModuleQuery::create()->filterById($module->getId())->update(['Activate' => BaseModule::IS_ACTIVATED]);

        $this->dispatch(new ModuleToggleActivationEvent($module->getId()), TheliaEvents::MODULE_TOGGLE_ACTIVATION);

        self::assertSame(
            BaseModule::IS_NOT_ACTIVATED,
            ModuleQuery::create()->findPk($module->getId())?->getActivate(),
            'The module is deactivated.',
        );
        self::assertTheModuleEndedTheTransaction('postDeactivation');
        $this->assertTheTransactionsOfTheProcessWork();
    }

    public function testADeletionThatAltersATableLeavesTheTransactionsOfTheProcessWorking(): void
    {
        $module = $this->installSampleModule('1.0.0', ['destroy.sql' => self::ALTER_A_TABLE]);

        $this->dispatch(new ModuleDeleteEvent($module->getId()), TheliaEvents::MODULE_DELETE);

        self::assertNull(ModuleQuery::create()->findPk($module->getId()), 'The module is deleted.');
        self::assertTheModuleEndedTheTransaction('destroy');
        $this->assertTheTransactionsOfTheProcessWork();
    }

    /**
     * What the process does after the lifecycle of the module: a transaction keeps its writes
     * from the other connections until commit(), rollBack() takes them back, commit() makes
     * them last, and nothing is left counted or open.
     */
    private function assertTheTransactionsOfTheProcessWork(): void
    {
        $connection = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);
        self::assertInstanceOf(ConnectionWrapper::class, $connection);

        $connection->beginTransaction();
        $this->writeProbe($connection, 'rolled back');
        self::assertNull($this->probeSeenByAnotherConnection(), 'A write made in a transaction was committed straight away.');
        $connection->rollBack();
        self::assertNull($this->probeSeenByAnotherConnection(), 'rollBack() did not take the write back.');

        $connection->beginTransaction();
        $this->writeProbe($connection, 'committed');
        $connection->commit();
        self::assertSame('committed', $this->probeSeenByAnotherConnection(), 'commit() did not make the write last.');

        self::assertSame(0, $connection->getNestedTransactionCount(), 'Propel still counts a transaction of the module lifecycle.');
        self::assertFalse($connection->inTransaction(), 'A transaction of the module lifecycle is still open in the database.');
    }

    /**
     * The sample module writes this line when the database no longer has a transaction once
     * its script ran: without it, the case would prove nothing.
     */
    private static function assertTheModuleEndedTheTransaction(string $method): void
    {
        self::assertStringContainsString(
            $method." ended the transaction\n",
            (string) @file_get_contents(self::callLog()),
            'The database did not end the transaction on the ALTER TABLE: this case proves nothing on it.',
        );
    }

    private function writeProbe(ConnectionInterface $connection, string $value): void
    {
        $statement = $connection->prepare(
            'INSERT INTO config (name, value, secured, hidden, created_at, updated_at) VALUES (:name, :value, 1, 1, NOW(), NOW())'
            .' ON DUPLICATE KEY UPDATE value = VALUES(value)',
        );
        $statement->execute([':name' => self::probeName(), ':value' => $value]);
    }

    /**
     * What a second connection reads: a write shows there once committed only.
     */
    private function probeSeenByAnotherConnection(): ?string
    {
        $statement = $this->anotherConnection()->prepare('SELECT value FROM config WHERE name = :name');
        $statement->execute([':name' => self::probeName()]);
        $row = $statement->fetch(\PDO::FETCH_NUM);

        return \is_array($row) ? (string) $row[0] : null;
    }

    private function anotherConnection(): ConnectionInterface
    {
        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(ModuleTableMap::DATABASE_NAME);
        self::assertInstanceOf(ConnectionManagerSingle::class, $connectionManager);

        return ConnectionFactory::create(
            $connectionManager->getConfiguration(),
            $serviceContainer->getAdapter(ModuleTableMap::DATABASE_NAME),
        );
    }

    /**
     * Installs the sample module, or updates it when it is installed with another version,
     * the way module:refresh does.
     *
     * @param array<string, string> $configFiles files of the Config directory of the module:
     *                                           <method>.sql is the script that method runs,
     *                                           <method>.own-transaction makes it run it in a
     *                                           transaction of its own
     */
    private function installSampleModule(string $version, array $configFiles = []): Module
    {
        $moduleDirectory = THELIA_MODULE_DIR.self::sampleCode();
        $this->filesystem->remove($moduleDirectory.DS.'Config');

        $this->filesystem->dumpFile($moduleDirectory.DS.self::sampleCode().'.php', $this->moduleClassSource());
        $this->filesystem->dumpFile($moduleDirectory.DS.'Config'.DS.'module.xml', $this->moduleDescriptorSource($version));

        foreach ($configFiles as $fileName => $contents) {
            $this->filesystem->dumpFile($moduleDirectory.DS.'Config'.DS.$fileName, $contents);
        }

        $container = static::getContainer();

        return (new ModuleManagement($container, $this->dispatcher))->updateModule(
            new \SplFileInfo($moduleDirectory.DS.'Config'.DS.'module.xml'),
            $container,
        );
    }

    /**
     * A test that fails leaves the connection the way the module left it: put it back, so
     * the next test of the process starts with working transactions.
     */
    private function rollBackWhatTheTestLeftOpen(): void
    {
        $connection = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);

        if (!$connection instanceof ConnectionWrapper || !$connection->isInTransaction()) {
            return;
        }

        if (!$connection->inTransaction()) {
            $connection->getWrappedConnection()?->beginTransaction();
        }

        $connection->forceRollBack();
    }

    private function removeSampleModule(): void
    {
        $connection = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);
        $connection->exec('DROP TEMPORARY TABLE IF EXISTS schema_change_sample');
        $connection->prepare('DELETE FROM config WHERE name = :name')->execute([':name' => self::probeName()]);

        ModuleQuery::create()->filterByCode(self::sampleCode(), Criteria::EQUAL)->delete();

        $this->filesystem->remove([THELIA_MODULE_DIR.self::sampleCode(), self::callLog()]);
    }

    /**
     * Test processes running side by side each get their own module (SchemaChange2Sample).
     */
    private static function sampleCode(): string
    {
        return self::SAMPLE_PREFIX.(TestToken::current() ?? '').'Sample';
    }

    private static function probeName(): string
    {
        return 'schema_change_probe'.TestToken::suffix();
    }

    private static function callLog(): string
    {
        return sys_get_temp_dir().'/thelia-schema-change-sample'.TestToken::suffix().'.log';
    }

    private function moduleClassSource(): string
    {
        $code = self::sampleCode();
        $callLog = var_export(self::callLog(), true);

        return <<<PHP
            <?php

            namespace {$code};

            use Propel\\Runtime\\Connection\\ConnectionInterface;
            use Thelia\\Core\\Install\\Database;
            use Thelia\\Module\\BaseModule;

            class {$code} extends BaseModule
            {
                public function update(\$currentVersion, \$newVersion, ?ConnectionInterface \$con = null): void
                {
                    \$this->runScript('update', \$con);
                }

                public function preActivation(?ConnectionInterface \$con = null): bool
                {
                    \$this->runScript('preActivation', \$con);

                    return true;
                }

                public function postActivation(?ConnectionInterface \$con = null): void
                {
                    \$this->runScript('postActivation', \$con);
                }

                public function postDeactivation(?ConnectionInterface \$con = null): void
                {
                    \$this->runScript('postDeactivation', \$con);
                }

                public function destroy(?ConnectionInterface \$con = null, \$deleteModuleData = false): void
                {
                    \$this->runScript('destroy', \$con);
                }

                // Like the modules that change their tables with a script of their own.
                private function runScript(string \$method, ConnectionInterface \$con): void
                {
                    \$script = __DIR__.'/Config/'.\$method.'.sql';

                    if (!is_file(\$script)) {
                        return;
                    }

                    \$ownTransaction = is_file(__DIR__.'/Config/'.\$method.'.own-transaction');

                    if (\$ownTransaction) {
                        \$con->beginTransaction();
                    }

                    try {
                        (new Database(\$con))->insertSql(null, [\$script]);
                    } finally {
                        if (!\$con->inTransaction()) {
                            file_put_contents({$callLog}, \$method." ended the transaction\\n", \\FILE_APPEND);
                        }
                    }

                    if (\$ownTransaction) {
                        \$con->commit();
                    }
                }
            }

            PHP;
    }

    private function moduleDescriptorSource(string $version): string
    {
        $code = self::sampleCode();

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <module xmlns="http://thelia.net/schema/dic/module"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xsi:schemaLocation="http://thelia.net/schema/dic/module http://thelia.net/schema/dic/module/module-2_1.xsd">
                <fullnamespace>{$code}\\{$code}</fullnamespace>
                <descriptive locale="en_US">
                    <title>{$code}</title>
                </descriptive>
                <languages>
                    <language>en_US</language>
                </languages>
                <version>{$version}</version>
                <author>
                    <name>Thelia</name>
                    <email>info@thelia.net</email>
                </author>
                <type>classic</type>
                <thelia>2.5.5</thelia>
                <stability>alpha</stability>
            </module>

            XML;
    }
}
