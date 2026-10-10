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

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\TheliaKernel;
use Thelia\Install\Standalone\DatabaseSetup;
use Thelia\Install\Standalone\ModuleDescriptorReader;
use Thelia\Install\Standalone\ModuleRegistrationWarnings;
use Thelia\Model\ConfigQuery;
use Thelia\Module\Exception\InvalidModuleDescriptorException;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tools\Version\Version;

final class DatabaseSetupTest extends IntegrationTestCase
{
    // This suite issues DDL (CREATE DATABASE), which would wait on the schema metadata
    // lock held by the base class' per-test transaction until lock_wait_timeout (~1 year).
    // DDL tests must opt out of the transactional isolation, per IntegrationTestCase.
    protected bool $useTransaction = false;

    private const string SHIPPED_ACTIVE_CODE = 'InstallSampleShippedActive';

    private const string SHIPPED_INACTIVE_CODE = 'InstallSampleShippedInactive';

    private const string UNDECLARED_CODE = 'InstallSampleUndeclared';

    private const string REFUSED_CODE = 'InstallSampleRefused';

    /** @var string[] */
    private array $moduleDirs = [];

    protected function tearDown(): void
    {
        if ([] !== $this->moduleDirs) {
            $setup = $this->createDatabaseSetup();
            $setup->connect();
            $codes = [self::SHIPPED_ACTIVE_CODE, self::SHIPPED_INACTIVE_CODE, self::UNDECLARED_CODE, self::REFUSED_CODE];
            $placeholders = implode(',', array_fill(0, \count($codes), '?'));
            $setup->getPdo()->prepare("DELETE FROM `module_i18n` WHERE `id` IN (SELECT `id` FROM `module` WHERE `code` IN ($placeholders))")->execute($codes);
            $setup->getPdo()->prepare("DELETE FROM `module` WHERE `code` IN ($placeholders)")->execute($codes);

            (new Filesystem())->remove($this->moduleDirs);
            $this->moduleDirs = [];
        }

        parent::tearDown();
    }

    public function testConstructorRejectsInvalidDatabaseName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid database name');

        new DatabaseSetup('db', '3306', 'DROP DATABASE test; --', 'db', 'db');
    }

    public function testConstructorAcceptsValidDatabaseName(): void
    {
        $setup = new DatabaseSetup('db', '3306', 'test', 'db', 'db');
        self::assertInstanceOf(DatabaseSetup::class, $setup);
    }

    public function testConnectSucceedsWithTestCredentials(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();

        self::assertInstanceOf(\PDO::class, $setup->getPdo());
    }

    public function testGetWarningsIsEmptyByDefault(): void
    {
        $setup = new DatabaseSetup('db', '3306', 'test', 'db', 'db');
        self::assertSame([], $setup->getWarnings());
    }

    public function testCreateDatabaseIsIdempotent(): void
    {
        // Creating a database that already exists should not throw.
        $setup = $this->createDatabaseSetup();
        $setup->createDatabase();

        // If we get here, it didn't throw.
        self::assertTrue(true);
    }

    public function testSeededVersionIsTheVersionOfTheRunningCode(): void
    {
        // This database is built by bin/test-prepare through DatabaseSetup, exactly like a
        // fresh install. The version it carries must be the code version: any older value
        // makes setup/update.php replay the update scripts above it (#3571).
        $parsedVersion = Version::parse(TheliaKernel::THELIA_VERSION);

        // Reading past the config cache: that pool outlives a database rebuild, so the
        // cached copy would answer for a row this test is precisely about.
        self::assertSame($parsedVersion['version'], ConfigQuery::read('thelia_version', null, true));
        self::assertSame($parsedVersion['major'], ConfigQuery::read('thelia_major_version', null, true));
        self::assertSame($parsedVersion['minus'], ConfigQuery::read('thelia_minus_version', null, true));
        self::assertSame($parsedVersion['release'], ConfigQuery::read('thelia_release_version', null, true));
        self::assertSame($parsedVersion['extra'], ConfigQuery::read('thelia_extra_version', null, true));
    }

    /**
     * bin/install reads a config row before deciding whether to write it, so that a value
     * the shop already carries is never overwritten by a re-run. The shop notification
     * address is the row this matters for: it ships seeded empty, and the install writes
     * the administrator address into it only while it is still empty.
     */
    public function testGetConfigReadsTheStoredValue(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();

        $previous = $setup->getConfig('store_notification_emails');

        try {
            $setup->setConfig('store_notification_emails', 'shop@example.com');
            self::assertSame('shop@example.com', $setup->getConfig('store_notification_emails'));
        } finally {
            $setup->setConfig('store_notification_emails', (string) $previous);
        }
    }

    public function testGetConfigReturnsNullForAnUnknownName(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();

        self::assertNull($setup->getConfig('no_such_configuration_row'));
    }

    /**
     * A module ships its schema in its current shape (TheliaMain.sql) along with the
     * update scripts that led there, and a fresh install replays both. A column, index or
     * foreign key an update drops is then already gone: the install must not warn about it.
     */
    public function testAModuleUpdateDroppingWhatIsAlreadyGoneRaisesNoWarning(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();

        try {
            (new \ReflectionMethod($setup, 'applyModuleSchema'))
                ->invoke($setup, THELIA_ROOT.'tests/fixtures/install/AbsentDropProbe', 'AbsentDropProbe');

            self::assertSame([], $setup->getWarnings());
        } finally {
            $setup->getPdo()->exec('DROP TABLE IF EXISTS `absent_drop_probe`');
        }
    }

    /**
     * A module ships active unless its descriptor says otherwise: the module table row
     * follows `<enabled-by-default>`, and a descriptor that does not declare it keeps the
     * historical behaviour, active on install.
     */
    public function testRegisteredModuleFollowsTheActivationItsDescriptorDeclares(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();

        $count = $setup->registerAndApplyModules([$this->writeSampleModules()]);

        self::assertSame(3, $count);
        self::assertSame(1, $this->activationOf($setup->getPdo(), self::SHIPPED_ACTIVE_CODE));
        self::assertSame(0, $this->activationOf($setup->getPdo(), self::SHIPPED_INACTIVE_CODE));
        self::assertSame(1, $this->activationOf($setup->getPdo(), self::UNDECLARED_CODE));
        self::assertSame([], $setup->getWarnings());
    }

    /**
     * In the back-office, `<mandatory>1</mandatory>` hides the deactivation switch of an
     * active module and forbids deleting the module: a mandatory module that ships inactive
     * is registered inactive like any other, and
     * nothing would tell the operator that a module the shop cannot do without is off.
     * The install registers it as asked and says so in its warnings.
     */
    public function testAMandatoryModuleShippedInactiveIsRegisteredInactiveWithAWarning(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $moduleDir = $this->writeMandatoryModule('<enabled-by-default>0</enabled-by-default>');

        $setup->registerAndApplyModules([$moduleDir]);

        self::assertSame(0, $this->activationOf($setup->getPdo(), self::SHIPPED_INACTIVE_CODE));
        self::assertSame([self::SHIPPED_INACTIVE_CODE.' is mandatory but is registered inactive: activate it from the back-office.'], $setup->getWarnings());
    }

    /**
     * Registering writes each module on its own: an active module whose <required> module
     * ships inactive lands next to an inactive dependency, which the install says.
     */
    public function testAnActiveModuleWhoseRequiredModuleShipsInactiveIsReported(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $dependencyDir = $this->writeSingleModule(<<<XML
            <type>classic</type>
            <stability>prod</stability>
            <enabled-by-default>0</enabled-by-default>
            XML, self::SHIPPED_INACTIVE_CODE);
        $dependentDir = $this->writeSingleModule(\sprintf(<<<XML
            <type>classic</type>
            <required>
                <module version="&gt;=1.0.0">%s</module>
            </required>
            <stability>prod</stability>
            XML, self::SHIPPED_INACTIVE_CODE), self::SHIPPED_ACTIVE_CODE);

        $setup->registerAndApplyModules([$dependencyDir, $dependentDir]);

        self::assertSame(1, $this->activationOf($setup->getPdo(), self::SHIPPED_ACTIVE_CODE));
        self::assertSame(0, $this->activationOf($setup->getPdo(), self::SHIPPED_INACTIVE_CODE));
        self::assertSame([\sprintf('%1$s is registered active but requires %2$s, which is registered inactive: activate %2$s from the back-office.', self::SHIPPED_ACTIVE_CODE, self::SHIPPED_INACTIVE_CODE)], $setup->getWarnings());
    }

    public function testAnActiveModuleWhoseRequiredModuleIsActiveIsNotReported(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $dependencyDir = $this->writeSingleModule(<<<XML
            <type>classic</type>
            <stability>prod</stability>
            XML, self::UNDECLARED_CODE);
        $dependentDir = $this->writeSingleModule(\sprintf(<<<XML
            <type>classic</type>
            <required>
                <module version="&gt;=1.0.0">%s</module>
            </required>
            <stability>prod</stability>
            XML, self::UNDECLARED_CODE), self::SHIPPED_ACTIVE_CODE);

        $setup->registerAndApplyModules([$dependencyDir, $dependentDir]);

        self::assertSame(1, $this->activationOf($setup->getPdo(), self::UNDECLARED_CODE));
        self::assertSame([], $setup->getWarnings());
    }

    /**
     * A module registered inactive does not run: its inactive dependency is not worth a
     * warning, the merchant activates both or neither.
     */
    public function testAnInactiveModuleWhoseRequiredModuleIsInactiveIsNotReported(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $dependencyDir = $this->writeSingleModule(<<<XML
            <type>classic</type>
            <stability>prod</stability>
            <enabled-by-default>0</enabled-by-default>
            XML, self::UNDECLARED_CODE);
        $dependentDir = $this->writeSingleModule(\sprintf(<<<XML
            <type>classic</type>
            <required>
                <module version="&gt;=1.0.0">%s</module>
            </required>
            <stability>prod</stability>
            <enabled-by-default>0</enabled-by-default>
            XML, self::UNDECLARED_CODE), self::SHIPPED_INACTIVE_CODE);

        $setup->registerAndApplyModules([$dependencyDir, $dependentDir]);

        self::assertSame(0, $this->activationOf($setup->getPdo(), self::SHIPPED_INACTIVE_CODE));
        self::assertSame([], $setup->getWarnings());
    }

    /**
     * A module found in both module directories is read, and its SQL applied, from each: the
     * first copy written decides its row. A mandatory module left inactive is reported once.
     */
    public function testAModuleFoundInBothDirectoriesIsReportedOnce(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $vendorDir = $this->writeMandatoryModule('<enabled-by-default>0</enabled-by-default>');
        $localDir = $this->writeMandatoryModule('<enabled-by-default>0</enabled-by-default>');

        self::assertSame(2, $setup->registerAndApplyModules([$vendorDir, $localDir]));
        self::assertSame(0, $this->activationOf($setup->getPdo(), self::SHIPPED_INACTIVE_CODE));
        self::assertSame([self::SHIPPED_INACTIVE_CODE.' is mandatory but is registered inactive: activate it from the back-office.'], $setup->getWarnings());
        // The registration drops identical lines too: the warnings are read on one copy
        // before that.
        self::assertCount(1, ModuleRegistrationWarnings::describe(
            (new ModuleDescriptorReader())->read([$vendorDir, $localDir]),
            [self::SHIPPED_INACTIVE_CODE => ['activate' => 0, 'mandatory' => 1]],
        ));
    }

    /**
     * Two copies of a module that disagree: the first copy creates the row, and the warnings
     * describe that row. A local copy declaring the module mandatory does not make the install
     * warn about a row the vendor copy registered as not mandatory.
     */
    public function testTheFirstCopyOfAModuleDecidesItsRowAndItsWarnings(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $vendorDir = $this->writeSingleModule(<<<XML
            <type>classic</type>
            <stability>prod</stability>
            <enabled-by-default>0</enabled-by-default>
            XML);
        $localDir = $this->writeMandatoryModule('');

        $setup->registerAndApplyModules([$vendorDir, $localDir]);

        self::assertSame(0, $this->activationOf($setup->getPdo(), self::SHIPPED_INACTIVE_CODE));
        self::assertSame([], $setup->getWarnings());
    }

    /**
     * Only the first copy of a module is read for its warnings: a local copy that requires a
     * module shipped inactive does not make the install warn about the row the vendor copy,
     * which requires nothing, created.
     */
    public function testTheRequiredModulesOfAModuleAreReadOnItsFirstCopy(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $dependencyDir = $this->writeSingleModule(<<<XML
            <type>classic</type>
            <stability>prod</stability>
            <enabled-by-default>0</enabled-by-default>
            XML, self::SHIPPED_INACTIVE_CODE);
        $vendorDir = $this->writeSingleModule(<<<XML
            <type>classic</type>
            <stability>prod</stability>
            XML, self::SHIPPED_ACTIVE_CODE);
        $localDir = $this->writeSingleModule(\sprintf(<<<XML
            <type>classic</type>
            <required>
                <module version="&gt;=1.0.0">%s</module>
            </required>
            <stability>prod</stability>
            XML, self::SHIPPED_INACTIVE_CODE), self::SHIPPED_ACTIVE_CODE);

        $setup->registerAndApplyModules([$dependencyDir, $vendorDir, $localDir]);

        self::assertSame([], $setup->getWarnings());
    }

    /**
     * Registering on a table that knows the module does not refresh the mandatory flag of its
     * row: a descriptor that turned mandatory since does not make the install warn about a
     * row that is not. The installers recreate the table first; this is the method's contract.
     */
    public function testTheMandatoryWarningReadsTheFlagTheRowCarries(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $setup->registerAndApplyModules([$this->writeSingleModule(<<<XML
            <type>classic</type>
            <stability>prod</stability>
            <enabled-by-default>0</enabled-by-default>
            XML)]);

        $replay = $this->createDatabaseSetup();
        $replay->connect();
        $replay->registerAndApplyModules([$this->writeMandatoryModule('<enabled-by-default>0</enabled-by-default>')]);

        self::assertSame([], $replay->getWarnings());
    }

    /**
     * Both copies of a module apply the same SQL files: a statement both refuse is reported once.
     */
    public function testAnSqlFailureOfAModuleInBothDirectoriesIsReportedOnce(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $vendorDir = $this->writeSingleModule(<<<XML
            <type>classic</type>
            <stability>prod</stability>
            XML, self::SHIPPED_ACTIVE_CODE);
        $localDir = $this->writeSingleModule(<<<XML
            <type>classic</type>
            <stability>prod</stability>
            XML, self::SHIPPED_ACTIVE_CODE);
        foreach ([$vendorDir, $localDir] as $moduleDir) {
            (new Filesystem())->dumpFile($moduleDir.self::SHIPPED_ACTIVE_CODE.'/Config/TheliaMain.sql', "THIS IS NOT SQL;\n");
        }

        $setup->registerAndApplyModules([$vendorDir, $localDir]);

        self::assertCount(1, array_filter($setup->getWarnings(), static fn (string $warning): bool => str_contains($warning, 'TheliaMain.sql')));
    }

    /**
     * The warnings describe the registration that just ran: a second registration that has
     * nothing to report does not hand back the warnings of the first.
     */
    public function testTheWarningsDescribeTheLastRegistrationOnly(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $moduleDir = $this->writeMandatoryModule('<enabled-by-default>0</enabled-by-default>');

        $setup->registerAndApplyModules([$moduleDir]);
        self::assertCount(1, $setup->getWarnings());

        $setup->registerAndApplyModules([$this->newModuleDir()]);
        self::assertSame([], $setup->getWarnings());
    }

    /**
     * A row the table already holds keeps its state, so the warning has to describe that
     * state, not the descriptor: a mandatory module whose row was activated since is not
     * reported, one whose row was switched off is. The installers recreate the table before
     * they register; a caller that does not gets the state of the rows it finds.
     */
    public function testTheMandatoryWarningDescribesTheRegisteredStateNotTheDescriptor(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $shippedInactiveDir = $this->writeMandatoryModule('<enabled-by-default>0</enabled-by-default>');
        $shippedActiveDir = $this->writeMandatoryModule('', self::SHIPPED_ACTIVE_CODE);
        $setup->registerAndApplyModules([$shippedInactiveDir, $shippedActiveDir]);

        $setup->getPdo()->prepare('UPDATE `module` SET `activate` = 1 WHERE `code` = ?')->execute([self::SHIPPED_INACTIVE_CODE]);
        $setup->getPdo()->prepare('UPDATE `module` SET `activate` = 0 WHERE `code` = ?')->execute([self::SHIPPED_ACTIVE_CODE]);

        $replay = $this->createDatabaseSetup();
        $replay->connect();
        $replay->registerAndApplyModules([$shippedInactiveDir, $shippedActiveDir]);

        self::assertSame([self::SHIPPED_ACTIVE_CODE.' is mandatory but is registered inactive: activate it from the back-office.'], $replay->getWarnings());
    }

    /**
     * Registering on a module table that already knows the module never rewrites the
     * activation of its row, in either direction. The installers recreate the table first:
     * this is the contract of the method, which the second copy of a module relies on.
     */
    public function testRegisteringAgainKeepsTheActivationTheMerchantChose(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $moduleDir = $this->writeSampleModules();
        $setup->registerAndApplyModules([$moduleDir]);

        $setup->getPdo()->prepare('UPDATE `module` SET `activate` = 1 WHERE `code` = ?')->execute([self::SHIPPED_INACTIVE_CODE]);
        $setup->getPdo()->prepare('UPDATE `module` SET `activate` = 0 WHERE `code` = ?')->execute([self::UNDECLARED_CODE]);

        $setup->registerAndApplyModules([$moduleDir]);

        self::assertSame(1, $this->activationOf($setup->getPdo(), self::SHIPPED_INACTIVE_CODE));
        self::assertSame(0, $this->activationOf($setup->getPdo(), self::UNDECLARED_CODE));
    }

    /**
     * The installers check the descriptors before creating the database: the check needs
     * no connection, and refuses what the registration would refuse.
     */
    public function testTheDescriptorsAreValidatedWithoutADatabase(): void
    {
        $refusedDir = $this->writeSingleModule(<<<XML
            <type>classic</type>
            <stability>prod</stability>
            <enabled-by-default>maybe</enabled-by-default>
            XML, self::REFUSED_CODE);

        $this->expectException(InvalidModuleDescriptorException::class);
        $this->expectExceptionMessage(self::REFUSED_CODE);

        (new ModuleDescriptorReader())->read([$this->writeSampleModules(), $refusedDir]);
    }

    /**
     * The schema reports each of its errors on a line of its own: the install prints the
     * refusal on one line, so that nothing a descriptor quotes reads as a line of the install.
     */
    public function testASchemaRefusalIsReportedOnOneLine(): void
    {
        $refusedDir = $this->writeSingleModule(<<<XML
            <type>classic</type>
            <stability>prod</stability>
            <enabled-by-default>maybe</enabled-by-default>
            XML, self::REFUSED_CODE);

        try {
            (new ModuleDescriptorReader())->read([$refusedDir]);
            self::fail('The descriptor is refused.');
        } catch (InvalidModuleDescriptorException $exception) {
            self::assertStringContainsString('is refused by the module schema', $exception->getMessage());
            self::assertStringNotContainsString("\n", $exception->getMessage());
        }
    }

    public function testValidDescriptorsPassTheCheckWithoutADatabase(): void
    {
        $records = (new ModuleDescriptorReader())->read([$this->writeSampleModules()]);

        $activation = [];
        foreach ($records as $record) {
            $activation[$record->code] = $record->row['activate'];
        }
        ksort($activation);

        // The disk lists a directory in no fixed order: the codes are compared sorted.
        self::assertSame([self::SHIPPED_ACTIVE_CODE => 1, self::SHIPPED_INACTIVE_CODE => 0, self::UNDECLARED_CODE => 1], $activation);
    }

    /**
     * A module folder whose name reads as a URI ("Mod%41ule") is a folder: libxml, given
     * the path, decoded it and the install skipped the module as unreadable.
     */
    public function testAModuleInAFolderThatReadsAsAUriIsRead(): void
    {
        $moduleDir = $this->newModuleDir();
        (new Filesystem())->dumpFile($moduleDir.'Mod%41ule/Config/module.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <module xmlns="http://thelia.net/schema/dic/module">
                <fullnamespace>Sample\Sample</fullnamespace>
                <descriptive locale="en_US">
                    <title>Sample</title>
                </descriptive>
                <languages>
                    <language>en_US</language>
                </languages>
                <version>1.0.0</version>
                <type>classic</type>
                <stability>prod</stability>
            </module>
            XML);

        $records = (new ModuleDescriptorReader())->read([$moduleDir]);

        self::assertCount(1, $records);
        self::assertSame('Mod%41ule', $records[0]->code);
    }

    public function testAnInvalidActivationValueStopsTheRegistration(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $moduleDir = $this->writeSampleModules('<enabled-by-default>maybe</enabled-by-default>');

        $this->expectException(InvalidModuleDescriptorException::class);
        $this->expectExceptionMessage('enabled-by-default');

        $setup->registerAndApplyModules([$moduleDir]);
    }

    /**
     * The descriptors are all read before anything is written: a refused value must not
     * leave the modules read before it registered. The disk lists a directory in no fixed
     * order, so the refused descriptor sits alone in a second directory: the directories
     * are walked in the order given, the three valid modules are read first, and none of
     * them may have been written.
     */
    public function testAnInvalidActivationValueRegistersNoModuleAtAll(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $validDir = $this->writeSampleModules();
        $refusedDir = $this->writeSingleModule(<<<XML
            <type>classic</type>
            <stability>prod</stability>
            <enabled-by-default>maybe</enabled-by-default>
            XML, self::REFUSED_CODE);

        try {
            $setup->registerAndApplyModules([$validDir, $refusedDir]);
            self::fail('An invalid value must stop the registration.');
        } catch (InvalidModuleDescriptorException $exception) {
            self::assertStringContainsString(self::REFUSED_CODE, $exception->getMessage());
        }

        $statement = $setup->getPdo()->prepare('SELECT COUNT(*) FROM `module` WHERE `code` IN (?, ?, ?, ?)');
        $statement->execute([self::SHIPPED_ACTIVE_CODE, self::SHIPPED_INACTIVE_CODE, self::UNDECLARED_CODE, self::REFUSED_CODE]);

        self::assertSame(0, (int) $statement->fetchColumn());
    }

    /**
     * Only the 2.2 descriptor format knows `<enabled-by-default>`, as the last element of
     * `<module>`. The steps after the install that read the descriptor (module:refresh,
     * template:set) validate it against the schema first: a descriptor the schema refuses
     * must be refused here too, or the module ends up registered and then fails to refresh
     * or to be installed by a theme.
     */
    public function testAnElementOutOfPlaceStopsTheRegistration(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $moduleDir = $this->writeSingleModule(<<<XML
            <type>classic</type>
            <enabled-by-default>0</enabled-by-default>
            <stability>prod</stability>
            XML);

        try {
            $setup->registerAndApplyModules([$moduleDir]);
            self::fail('An element the schema refuses must stop the registration.');
        } catch (InvalidModuleDescriptorException $exception) {
            self::assertStringContainsString('The descriptor '.$moduleDir, $exception->getMessage());
            self::assertStringContainsString('declares <enabled-by-default> and is refused by the module schema', $exception->getMessage());
        }

        self::assertFalse($this->isRegistered($setup->getPdo(), self::SHIPPED_INACTIVE_CODE));
    }

    public function testADescriptorInTheFormerFormatCannotDeclareTheElement(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $moduleDir = $this->writeSingleModule(<<<XML
            <author>
                <name>Former format</name>
                <email>former@example.com</email>
            </author>
            <type>classic</type>
            <stability>prod</stability>
            <enabled-by-default>0</enabled-by-default>
            XML);

        try {
            $setup->registerAndApplyModules([$moduleDir]);
            self::fail('A 2.1 descriptor declaring the element must stop the registration.');
        } catch (InvalidModuleDescriptorException $exception) {
            self::assertStringContainsString('The descriptor '.$moduleDir, $exception->getMessage());
            self::assertStringContainsString('declares <enabled-by-default> and is refused by the module schema', $exception->getMessage());
        }

        self::assertFalse($this->isRegistered($setup->getPdo(), self::SHIPPED_INACTIVE_CODE));
    }

    private function isRegistered(\PDO $pdo, string $code): bool
    {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM `module` WHERE `code` = ?');
        $statement->execute([$code]);

        return 0 < (int) $statement->fetchColumn();
    }

    private function activationOf(\PDO $pdo, string $code): int
    {
        $statement = $pdo->prepare('SELECT `activate` FROM `module` WHERE `code` = ?');
        $statement->execute([$code]);

        $activate = $statement->fetchColumn();
        self::assertNotFalse($activate, \sprintf('Module %s was not registered.', $code));

        return (int) $activate;
    }

    /**
     * Three descriptors in a throwaway module directory: one declaring itself active,
     * one declaring itself inactive, one saying nothing about it. The first and the last
     * declarations can be replaced to exercise an invalid value.
     */
    private function writeSampleModules(string $activeDeclaration = '<enabled-by-default>1</enabled-by-default>', string $undeclaredDeclaration = ''): string
    {
        $moduleDir = $this->newModuleDir();
        $filesystem = new Filesystem();

        $declarations = [
            self::SHIPPED_ACTIVE_CODE => $activeDeclaration,
            self::SHIPPED_INACTIVE_CODE => '<enabled-by-default>0</enabled-by-default>',
            self::UNDECLARED_CODE => $undeclaredDeclaration,
        ];

        foreach ($declarations as $code => $declaration) {
            $filesystem->mkdir($moduleDir.$code.'/Config');
            $filesystem->dumpFile($moduleDir.$code.'/Config/module.xml', <<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <module xmlns="http://thelia.net/schema/dic/module">
                    <fullnamespace>{$code}\\{$code}</fullnamespace>
                    <descriptive locale="en_US">
                        <title>{$code}</title>
                    </descriptive>
                    <languages>
                        <language>en_US</language>
                    </languages>
                    <version>1.0.0</version>
                    <type>classic</type>
                    <stability>prod</stability>
                    {$declaration}
                </module>
                XML);
        }

        return $moduleDir;
    }

    private function writeMandatoryModule(string $declaration, string $code = self::SHIPPED_INACTIVE_CODE): string
    {
        return $this->writeSingleModule(<<<XML
            <type>classic</type>
            <stability>prod</stability>
            <mandatory>1</mandatory>
            {$declaration}
            XML, $code);
    }

    /**
     * One descriptor whose tail (from `<type>` on) is given verbatim, to exercise the
     * element order, the descriptor format and a refused value.
     */
    private function writeSingleModule(string $tail, string $code = self::SHIPPED_INACTIVE_CODE): string
    {
        $moduleDir = $this->newModuleDir();

        (new Filesystem())->dumpFile($moduleDir.$code.'/Config/module.xml', <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <module xmlns="http://thelia.net/schema/dic/module">
                <fullnamespace>{$code}\\{$code}</fullnamespace>
                <descriptive locale="en_US">
                    <title>{$code}</title>
                </descriptive>
                <languages>
                    <language>en_US</language>
                </languages>
                <version>1.0.0</version>
                {$tail}
            </module>
            XML);

        return $moduleDir;
    }

    /**
     * A throwaway module directory, remembered so tearDown removes it and the rows its
     * modules may have left.
     */
    private function newModuleDir(): string
    {
        $moduleDir = sys_get_temp_dir().'/thelia-install-modules-'.bin2hex(random_bytes(4)).'/';
        $this->moduleDirs[] = $moduleDir;

        return $moduleDir;
    }

    /**
     * Builds a DatabaseSetup pointing at the configured test database. Credentials come from
     * the environment ($_SERVER, populated from .env.test.local by the test bootstrap), so the
     * suite connects to the CI MySQL (127.0.0.1) as well as a local DDEV database (db), instead
     * of a hardcoded host that does not resolve on the CI runner.
     */
    private function createDatabaseSetup(): DatabaseSetup
    {
        return new DatabaseSetup(
            (string) ($_SERVER['DATABASE_HOST'] ?? 'db'),
            (string) ($_SERVER['DATABASE_PORT'] ?? '3306'),
            (string) ($_SERVER['DATABASE_NAME'] ?? 'test'),
            (string) ($_SERVER['DATABASE_USER'] ?? 'db'),
            (string) ($_SERVER['DATABASE_PASSWORD'] ?? 'db'),
        );
    }
}
