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
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Command\Install;
use Thelia\Model\ModuleQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * thelia:install applies the chosen templates through template:set, in a kernel of its
 * own, then runs the remaining steps. A template that cannot be applied has to reach the
 * exit code of the install, as it does in bin/install. The whole command writes the
 * environment file and rebuilds the database, so the template step is driven alone.
 */
final class InstallTemplatesTest extends IntegrationTestCase
{
    // The template step boots a kernel of its own, which reinitializes Propel and drops the
    // transaction the base class would roll back. Nothing is written here.
    protected bool $useTransaction = false;

    private const string MISSING_THEME = 'InstallSampleMissingTheme';

    private const string REFUSED_MODULE = 'InstallSampleRefusedModule';

    private const array PROCESS_VARIABLES = ['DATABASE_HOST', 'DATABASE_PORT', 'DATABASE_NAME', 'DATABASE_USER', 'DATABASE_PASSWORD', 'SHELL_VERBOSITY'];

    /** @var array<string, mixed> */
    private array $server = [];

    /** @var array<string, mixed> */
    private array $environment = [];

    /** @var array<string, false|string> */
    private array $processVariables = [];

    private ?string $moduleDir = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $_SERVER;
        $this->environment = $_ENV;
        foreach (self::PROCESS_VARIABLES as $name) {
            $this->processVariables[$name] = getenv($name);
        }
        // phpunit.xml.dist runs the suites quiet, and the console application that runs
        // template:set applies that verbosity to the output it is handed: the output the
        // install writes afterwards would be dropped.
        $_SERVER['SHELL_VERBOSITY'] = $_ENV['SHELL_VERBOSITY'] = 0;
    }

    protected function tearDown(): void
    {
        // The step publishes the connection it is given into the process environment, and
        // the console application its verbosity.
        $_SERVER = $this->server;
        $_ENV = $this->environment;
        foreach ($this->processVariables as $name => $value) {
            putenv(false === $value ? $name : $name.'='.$value);
        }

        if (null !== $this->moduleDir) {
            (new Filesystem())->remove($this->moduleDir);
        }

        parent::tearDown();
    }

    public function testATemplateThatCannotBeAppliedIsReportedToTheInstall(): void
    {
        $output = new BufferedOutput();

        $applied = $this->applyTemplates($output, ['backOffice' => self::MISSING_THEME]);

        self::assertFalse($applied, 'A template:set that fails makes the template step fail.');
        self::assertStringContainsString(\sprintf('Post-install step failed while applying template "%s" for type "backOffice".', self::MISSING_THEME), $output->fetch());
    }

    public function testNoTemplateToApplyIsNotAFailure(): void
    {
        self::assertTrue($this->applyTemplates(new BufferedOutput(), ['backOffice' => ' ']));
    }

    public function testATemplateThatCouldNotBeAppliedMakesTheInstallFail(): void
    {
        $output = new BufferedOutput();

        self::assertSame(Command::FAILURE, $this->installResult(false, $output));
        self::assertStringContainsString('Thelia installed with errors: a template could not be applied. Check messages above.', $output->fetch());
        self::assertSame(Command::SUCCESS, $this->installResult(true, new BufferedOutput()));
    }

    /**
     * A descriptor the module schema refuses stops the registration with a readable line and
     * makes the install fail, with nothing written.
     */
    public function testARefusedDescriptorStopsTheModuleRegistration(): void
    {
        $this->moduleDir = sys_get_temp_dir().'/thelia-install-refused-'.bin2hex(random_bytes(4)).'/';
        $code = self::REFUSED_MODULE;
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
        $output = new BufferedOutput();

        $registerModules = new \ReflectionMethod(Install::class, 'registerModules');
        $registered = $registerModules->invoke(new Install('test'), $output, $this->connectionInfo(), [$this->moduleDir]);

        self::assertFalse($registered);
        self::assertStringContainsString('ERROR: <enabled-by-default> in '.$this->moduleDir.$code, $output->fetch());
        self::assertNull(ModuleQuery::create()->findOneByCode($code));
    }

    private function installResult(bool $templatesApplied, BufferedOutput $output): int
    {
        return (new \ReflectionMethod(Install::class, 'installResult'))->invoke(new Install('test'), $templatesApplied, $output);
    }

    /**
     * @return array{host: string, port: string, dbName: string, username: string, password: string}
     */
    private function connectionInfo(): array
    {
        return [
            'host' => (string) ($_SERVER['DATABASE_HOST'] ?? 'db'),
            'port' => (string) ($_SERVER['DATABASE_PORT'] ?? '3306'),
            'dbName' => (string) ($_SERVER['DATABASE_NAME'] ?? 'test'),
            'username' => (string) ($_SERVER['DATABASE_USER'] ?? 'db'),
            'password' => (string) ($_SERVER['DATABASE_PASSWORD'] ?? 'db'),
        ];
    }

    /**
     * @param array<string, string> $themes
     */
    private function applyTemplates(BufferedOutput $output, array $themes): bool
    {
        $applyTemplates = new \ReflectionMethod(Install::class, 'applyTemplatesInSameCommandProcess');

        return $applyTemplates->invoke(new Install('test'), $output, $this->connectionInfo(), $themes);
    }
}
