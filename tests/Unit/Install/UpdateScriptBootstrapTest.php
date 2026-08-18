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

namespace Thelia\Tests\Unit\Install;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * `setup/update.php` is shipped as the `thelia/setup` package, so the same file has
 * to boot on the two layouts it lands in: a thelia/thelia checkout, where it sits at
 * the root next to vendor/, and a thelia-project install, where Composer puts it in
 * local/setup/ and the autoloader is two levels up.
 *
 * A thelia-project install is reproduced here as the file tree the script reads,
 * because both halves of the bootstrap are ordering-sensitive: the project
 * bootstrap.php holds no autoloader, and the Composer autoloader pulls in the core
 * bootstrap.php, which reads THELIA_ROOT off its own location under vendor/.
 */
final class UpdateScriptBootstrapTest extends TestCase
{
    private const MARKER = 'BOOTSTRAPPED THELIA_ROOT=';

    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir().'/thelia-update-bootstrap-'.bin2hex(random_bytes(6));

        $filesystem = new Filesystem();
        $filesystem->mkdir([$this->projectRoot.'/vendor', $this->projectRoot.'/local/setup']);
        $filesystem->dumpFile($this->projectRoot.'/.env', "APP_ENV=dev\n");
        $filesystem->dumpFile($this->projectRoot.'/bootstrap.php', $this->projectBootstrap());
        $filesystem->dumpFile($this->projectRoot.'/vendor/autoload.php', $this->composerAutoloader());
        $filesystem->copy(
            THELIA_SETUP_DIRECTORY.'update.php',
            $this->projectRoot.'/local/setup/update.php',
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectRoot);
    }

    public function testTheScriptBootsOnAProjectInstall(): void
    {
        $output = $this->runUpdateScript();

        self::assertStringContainsString(self::MARKER, $output);
    }

    public function testTheProjectRootWinsOverTheOneTheCoreBootstrapDerives(): void
    {
        $output = $this->runUpdateScript();

        self::assertStringContainsString(
            self::MARKER.$this->projectRoot.\DIRECTORY_SEPARATOR."\n",
            $output,
            'THELIA_ROOT points inside vendor/: the autoloader was loaded before bootstrap.php.',
        );
    }

    private function runUpdateScript(): string
    {
        $process = new Process([\PHP_BINARY, $this->projectRoot.'/local/setup/update.php']);
        $process->run();

        return $process->getOutput().$process->getErrorOutput();
    }

    /**
     * What thelia-project ships: the path constants, and no autoloader. Requiring
     * vendor/autoload.php here would break the Symfony Runtime, which uses the fact
     * that it has not been loaded yet as its own guard.
     */
    private function projectBootstrap(): string
    {
        return <<<'PHP'
            <?php

            if (!defined('DS')) {
                define('DS', DIRECTORY_SEPARATOR);
            }
            if (!defined('THELIA_ROOT')) {
                define('THELIA_ROOT', __DIR__.DS);
            }
            PHP;
    }

    /**
     * Stands in for the Composer autoloader of a project install. It defines
     * THELIA_ROOT the way vendor/thelia/core/bootstrap.php does, as an autoload
     * "files" entry, so that loading it first is visible in the output; and it
     * defines the first class the script reaches, which reports and stops there
     * rather than booting a kernel this tree does not hold.
     */
    private function composerAutoloader(): string
    {
        $marker = self::MARKER;

        return <<<PHP
            <?php

            namespace {
                if (!defined('THELIA_ROOT')) {
                    define('THELIA_ROOT', __DIR__.DIRECTORY_SEPARATOR.'thelia'.DIRECTORY_SEPARATOR);
                }
            }

            namespace Symfony\Component\Dotenv {
                class Dotenv
                {
                    public function bootEnv(string \$path): void
                    {
                        echo '{$marker}'.\THELIA_ROOT."\\n";

                        exit(0);
                    }
                }
            }
            PHP;
    }
}
