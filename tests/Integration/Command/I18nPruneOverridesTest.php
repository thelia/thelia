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

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Core\Translation\Translator;
use Thelia\Test\IntegrationTestCase;

/**
 * i18n:prune-overrides reports (dry-run) and, with --force, removes local translation overrides
 * whose base source string no longer exists, without ever touching the versioned base files.
 */
final class I18nPruneOverridesTest extends IntegrationTestCase
{
    private const LOCALE = 'zz_ZZ';
    private const DOMAIN = 'i18nPruneTestDomain';

    private string $overrideFile;
    private ?string $backup = null;
    private string $baseFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->overrideFile = THELIA_LOCAL_DIR.'I18n'.\DIRECTORY_SEPARATOR.self::LOCALE.'.php';

        if (is_file($this->overrideFile)) {
            $this->backup = file_get_contents($this->overrideFile) ?: null;
        }

        // A deterministic base catalogue: only "Kept string" exists as a base source string.
        $this->baseFile = tempnam(sys_get_temp_dir(), 'i18n_base_').'.php';
        file_put_contents($this->baseFile, "<?php\n\nreturn ['Kept string' => 'Kept'];\n");
        Translator::getInstance()->addResource('php', $this->baseFile, self::LOCALE, self::DOMAIN);

        file_put_contents(
            $this->overrideFile,
            <<<'PHP'
                <?php

                return [
                    'i18nPruneTestDomain' => [
                        'Kept string' => 'A merchant redefinition',
                        'Removed string' => 'Points at a base string that no longer exists',
                    ],
                    'Unknown global string' => 'A blanket fallback with no base string',
                ];
                PHP,
        );
    }

    protected function tearDown(): void
    {
        if (null !== $this->backup) {
            file_put_contents($this->overrideFile, $this->backup);
        } elseif (is_file($this->overrideFile)) {
            unlink($this->overrideFile);
        }

        if (is_file($this->baseFile)) {
            unlink($this->baseFile);
        }

        parent::tearDown();
    }

    public function testDryRunReportsOrphansWithoutTouchingTheFile(): void
    {
        $before = file_get_contents($this->overrideFile);

        $tester = $this->commandTester();
        $tester->execute(['--locale' => self::LOCALE]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        self::assertStringContainsString('Removed string', $display);
        self::assertStringContainsString('Unknown global string', $display);
        self::assertStringNotContainsString('Kept string', $display);

        self::assertSame($before, file_get_contents($this->overrideFile), 'Dry-run must not modify the override file.');
    }

    public function testForceRemovesOrphansAndKeepsValidOverrides(): void
    {
        $tester = $this->commandTester();
        $tester->execute(['--locale' => self::LOCALE, '--force' => true]);

        $tester->assertCommandIsSuccessful();

        $result = require $this->overrideFile;

        self::assertArrayHasKey(self::DOMAIN, $result);
        self::assertArrayHasKey('Kept string', $result[self::DOMAIN]);
        self::assertArrayNotHasKey('Removed string', $result[self::DOMAIN]);
        self::assertArrayNotHasKey('Unknown global string', $result);
    }

    private function commandTester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('i18n:prune-overrides'));
    }
}
