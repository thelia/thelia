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

use Symfony\Component\Console\Output\BufferedOutput;
use Thelia\Command\Install;
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

    /** @var array<string, mixed> */
    private array $server = [];

    /** @var array<string, mixed> */
    private array $environment = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $_SERVER;
        $this->environment = $_ENV;
        // phpunit.xml.dist runs the suites quiet, and the console application that runs
        // template:set applies that verbosity to the output it is handed: the output the
        // install writes afterwards would be dropped.
        $_SERVER['SHELL_VERBOSITY'] = $_ENV['SHELL_VERBOSITY'] = 0;
    }

    protected function tearDown(): void
    {
        // The step publishes the connection it is given into the process environment.
        $_SERVER = $this->server;
        $_ENV = $this->environment;

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

    /**
     * @param array<string, string> $themes
     */
    private function applyTemplates(BufferedOutput $output, array $themes): bool
    {
        $connectionInfo = [
            'host' => (string) ($_SERVER['DATABASE_HOST'] ?? 'db'),
            'port' => (string) ($_SERVER['DATABASE_PORT'] ?? '3306'),
            'dbName' => (string) ($_SERVER['DATABASE_NAME'] ?? 'test'),
            'username' => (string) ($_SERVER['DATABASE_USER'] ?? 'db'),
            'password' => (string) ($_SERVER['DATABASE_PASSWORD'] ?? 'db'),
        ];

        $applyTemplates = new \ReflectionMethod(Install::class, 'applyTemplatesInSameCommandProcess');

        return $applyTemplates->invoke(new Install('test'), $output, $connectionInfo, $themes);
    }
}
