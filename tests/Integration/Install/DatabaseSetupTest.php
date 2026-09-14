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
use Thelia\Install\Standalone\DistributionModuleDefaults;
use Thelia\Model\ConfigQuery;
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

    private ?string $moduleDir = null;

    protected function tearDown(): void
    {
        if (null !== $this->moduleDir) {
            $setup = $this->createDatabaseSetup();
            $setup->connect();
            $codes = [self::SHIPPED_ACTIVE_CODE, self::SHIPPED_INACTIVE_CODE];
            $placeholders = implode(',', array_fill(0, \count($codes), '?'));
            $setup->getPdo()->prepare("DELETE FROM `module_i18n` WHERE `id` IN (SELECT `id` FROM `module` WHERE `code` IN ($placeholders))")->execute($codes);
            $setup->getPdo()->prepare("DELETE FROM `module` WHERE `code` IN ($placeholders)")->execute($codes);

            (new Filesystem())->remove($this->moduleDir);
            $this->moduleDir = null;
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
     * A module ships active unless the distribution lists it as disabled by default:
     * the module table row follows that list, and a module the list does not name keeps
     * the historical behaviour, active on install.
     */
    public function testRegisteredModuleFollowsTheDistributionDefaults(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();

        $count = $setup->registerAndApplyModules([$this->writeSampleModules()], $this->distributionDefaults());

        self::assertSame(2, $count);
        self::assertSame(1, $this->activationOf($setup->getPdo(), self::SHIPPED_ACTIVE_CODE));
        self::assertSame(0, $this->activationOf($setup->getPdo(), self::SHIPPED_INACTIVE_CODE));
    }

    /**
     * Running the install again on a populated database (an update, a second
     * `bin/install` pass) must never rewrite the activation the merchant chose, in
     * either direction.
     */
    public function testRegisteringAgainKeepsTheActivationTheMerchantChose(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();
        $moduleDir = $this->writeSampleModules();
        $setup->registerAndApplyModules([$moduleDir], $this->distributionDefaults());

        $setup->getPdo()->prepare('UPDATE `module` SET `activate` = 1 WHERE `code` = ?')->execute([self::SHIPPED_INACTIVE_CODE]);
        $setup->getPdo()->prepare('UPDATE `module` SET `activate` = 0 WHERE `code` = ?')->execute([self::SHIPPED_ACTIVE_CODE]);

        $setup->registerAndApplyModules([$moduleDir], $this->distributionDefaults());

        self::assertSame(1, $this->activationOf($setup->getPdo(), self::SHIPPED_INACTIVE_CODE));
        self::assertSame(0, $this->activationOf($setup->getPdo(), self::SHIPPED_ACTIVE_CODE));
    }

    private function distributionDefaults(): DistributionModuleDefaults
    {
        return new DistributionModuleDefaults([self::SHIPPED_INACTIVE_CODE]);
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
     * Two plain descriptors in a throwaway module directory. Nothing in them says
     * anything about activation: that is the distribution's call.
     */
    private function writeSampleModules(): string
    {
        $this->moduleDir = sys_get_temp_dir().'/thelia-install-modules-'.bin2hex(random_bytes(4)).'/';
        $filesystem = new Filesystem();

        foreach ([self::SHIPPED_ACTIVE_CODE, self::SHIPPED_INACTIVE_CODE] as $code) {
            $filesystem->mkdir($this->moduleDir.$code.'/Config');
            $filesystem->dumpFile($this->moduleDir.$code.'/Config/module.xml', <<<XML
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
                </module>
                XML);
        }

        return $this->moduleDir;
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
