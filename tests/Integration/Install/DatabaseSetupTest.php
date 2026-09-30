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
     * `<mandatory>1</mandatory>` only keeps an active module from being deactivated: a
     * mandatory module that ships inactive is registered inactive like any other, and
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
     * On a populated database the row keeps the state the merchant chose, so the warning
     * has to describe that state, not the descriptor: a mandatory module the merchant
     * activated since is not reported, one the merchant switched off is.
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
     * Registering again on a populated database (a module table that already knows the
     * module) must never rewrite the activation the merchant chose, in either direction.
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
     * `<module>`. Every step after the install (module:refresh, template:set, the activation
     * from the back-office) validates the descriptor against the schema before reading it:
     * a descriptor the schema refuses must be refused here too, or the module ends up
     * registered but impossible to activate.
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
            self::assertStringContainsString('<enabled-by-default> in '.$moduleDir, $exception->getMessage());
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
            self::assertStringContainsString('<enabled-by-default> in '.$moduleDir, $exception->getMessage());
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
