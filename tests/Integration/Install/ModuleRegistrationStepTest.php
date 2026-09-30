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

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Install\Standalone\DatabaseSetup;
use Thelia\Install\Standalone\ModuleRegistrationStep;
use Thelia\Test\IntegrationTestCase;

/**
 * The two module steps of thelia:install: the descriptors are checked before anything is
 * created, the modules registered once the core schema exists. A descriptor the install
 * refuses gives an `ERROR:` line and a false result, which stops the command.
 */
final class ModuleRegistrationStepTest extends IntegrationTestCase
{
    // The registration writes through its own PDO connection, outside the transaction the
    // base class would roll back: the rows are removed by hand.
    protected bool $useTransaction = false;

    private const string REFUSED_CODE = 'InstallStepSampleRefused';

    private const string MANDATORY_CODE = 'InstallStepSampleMandatory';

    private const string VALID_CODE = 'InstallStepSampleValid';

    private ?string $moduleDir = null;

    protected function tearDown(): void
    {
        $setup = $this->connectedSetup();
        $setup->getPdo()->prepare('DELETE FROM `module_i18n` WHERE `id` IN (SELECT `id` FROM `module` WHERE `code` IN (?, ?))')->execute([self::REFUSED_CODE, self::MANDATORY_CODE]);
        $setup->getPdo()->prepare('DELETE FROM `module` WHERE `code` IN (?, ?)')->execute([self::REFUSED_CODE, self::MANDATORY_CODE]);

        if (null !== $this->moduleDir) {
            (new Filesystem())->remove($this->moduleDir);
        }

        parent::tearDown();
    }

    public function testARefusedDescriptorFailsTheCheck(): void
    {
        $output = new BufferedOutput();

        self::assertFalse((new ModuleRegistrationStep([$this->writeModule(self::REFUSED_CODE, '<enabled-by-default>maybe</enabled-by-default>')]))->check($output));
        self::assertStringContainsString('ERROR: The descriptor '.$this->moduleDir.self::REFUSED_CODE, $output->fetch());
    }

    public function testValidDescriptorsPassTheCheck(): void
    {
        $output = new BufferedOutput();

        self::assertTrue((new ModuleRegistrationStep([$this->writeModule(self::VALID_CODE, '<enabled-by-default>0</enabled-by-default>')]))->check($output));
        self::assertSame('', $output->fetch());
    }

    public function testARefusedDescriptorStopsTheRegistrationWithNothingWritten(): void
    {
        $output = new BufferedOutput();
        $setup = $this->connectedSetup();

        self::assertFalse((new ModuleRegistrationStep([$this->writeModule(self::REFUSED_CODE, '<enabled-by-default>maybe</enabled-by-default>')]))->register($setup, $output));
        self::assertStringContainsString('ERROR: The descriptor '.$this->moduleDir.self::REFUSED_CODE, $output->fetch());
        self::assertSame(0, $this->rowCount(self::REFUSED_CODE));
    }

    public function testTheRegistrationPrintsWhatItRegisteredAndWarnsAbout(): void
    {
        $output = new BufferedOutput();
        $setup = $this->connectedSetup();

        self::assertTrue((new ModuleRegistrationStep([$this->writeModule(self::MANDATORY_CODE, '<mandatory>1</mandatory><enabled-by-default>0</enabled-by-default>')]))->register($setup, $output));
        $written = $output->fetch();
        self::assertStringContainsString('1 module(s) registered', $written);
        self::assertStringContainsString('WARN '.self::MANDATORY_CODE.' is mandatory but is registered inactive: activate it from the back-office.', $written);
    }

    /**
     * One module in a throwaway directory, with the given elements after `<stability>`.
     */
    private function writeModule(string $code, string $tail): string
    {
        $this->moduleDir = sys_get_temp_dir().'/thelia-install-step-'.bin2hex(random_bytes(4)).'/';
        (new Filesystem())->dumpFile($this->moduleDir.$code.'/Config/module.xml', <<<XML
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
                {$tail}
            </module>
            XML);

        return $this->moduleDir;
    }

    private function rowCount(string $code): int
    {
        $statement = $this->connectedSetup()->getPdo()->prepare('SELECT COUNT(*) FROM `module` WHERE `code` = ?');
        $statement->execute([$code]);

        return (int) $statement->fetchColumn();
    }

    private function connectedSetup(): DatabaseSetup
    {
        $setup = new DatabaseSetup(
            (string) ($_SERVER['DATABASE_HOST'] ?? 'db'),
            (string) ($_SERVER['DATABASE_PORT'] ?? '3306'),
            (string) ($_SERVER['DATABASE_NAME'] ?? 'test'),
            (string) ($_SERVER['DATABASE_USER'] ?? 'db'),
            (string) ($_SERVER['DATABASE_PASSWORD'] ?? 'db'),
        );
        $setup->connect();

        return $setup;
    }
}
