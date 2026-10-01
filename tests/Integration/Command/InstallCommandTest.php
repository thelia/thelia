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

namespace Thelia\Tests\Integration\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Command\Install;
use Thelia\Install\Standalone\ModuleRegistrationStep;
use Thelia\Test\IntegrationTestCase;

/**
 * thelia:install checks every module descriptor right after the permissions: a descriptor
 * the install refuses stops the command before any question is asked and before the
 * database or the environment file exist.
 */
final class InstallCommandTest extends IntegrationTestCase
{
    private const string REFUSED_CODE = 'InstallCommandSampleRefused';

    private ?string $moduleDir = null;

    protected function tearDown(): void
    {
        if (null !== $this->moduleDir) {
            (new Filesystem())->remove($this->moduleDir);
        }

        parent::tearDown();
    }

    public function testARefusedDescriptorStopsTheInstallBeforeAnythingIsCreated(): void
    {
        $this->moduleDir = sys_get_temp_dir().'/thelia-install-command-'.bin2hex(random_bytes(4)).'/';
        $code = self::REFUSED_CODE;
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
                <enabled-by-default>maybe</enabled-by-default>
            </module>
            XML);

        // The test shop is installed: the permission check refuses a second install, and it is
        // not what this test is about.
        $install = new class('test', new ModuleRegistrationStep([$this->moduleDir])) extends Install {
            protected function checkPermission(OutputInterface $output): void
            {
            }
        };
        $tester = new CommandTester($install);
        $tester->execute([], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('ERROR: The descriptor '.$this->moduleDir.$code, $tester->getDisplay());
        self::assertStringNotContainsString('Creating Thelia database', $tester->getDisplay(), 'Nothing is created after a refused descriptor.');
        self::assertStringNotContainsString('Config file created', $tester->getDisplay());
    }

    /**
     * A template thelia:install cannot apply makes it end on a failure: each theme it applies
     * when it is asked none has to be one the project installs.
     */
    public function testEachDefaultThemeIsOneTheShopHas(): void
    {
        foreach (Install::DEFAULT_THEMES as $type => $name) {
            self::assertDirectoryExists(THELIA_TEMPLATE_DIR.$type.DS.$name, \sprintf('The default %s theme "%s" is not installed.', $type, $name));
        }
    }
}
