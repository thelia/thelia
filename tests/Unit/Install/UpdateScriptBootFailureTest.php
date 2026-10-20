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
 * On a live shop the web server can recreate the cache directory, under its own
 * user, between the moment `setup/update.php` removes it and the moment the kernel
 * boots. The script then fails to boot twice, and the second failure has to end
 * like the other failures that leave the database untouched: exit code 8 and the
 * command that clears the way, not a fatal error (#4055).
 *
 * The script runs on a stand-in tree whose kernel recreates the cache directory
 * read-only, then fails to write to it, which is what the web server's directory
 * does to the user running the script.
 */
final class UpdateScriptBootFailureTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        if (\function_exists('posix_geteuid') && 0 === posix_geteuid()) {
            self::markTestSkipped('root ignores the permissions this test relies on.');
        }

        $this->projectRoot = sys_get_temp_dir().'/thelia-update-boot-failure-'.bin2hex(random_bytes(6));

        $filesystem = new Filesystem();
        $filesystem->mkdir([$this->projectRoot.'/vendor', $this->projectRoot.'/setup', $this->projectRoot.'/var/cache/prod']);
        $filesystem->dumpFile($this->projectRoot.'/.env', "APP_ENV=prod\n");
        $filesystem->dumpFile($this->projectRoot.'/vendor/autoload.php', $this->autoloader());
        $filesystem->copy(THELIA_SETUP_DIRECTORY.'update.php', $this->projectRoot.'/setup/update.php');
        $filesystem->copy(
            THELIA_SETUP_DIRECTORY.'CompiledDirectoryRemover.php',
            $this->projectRoot.'/setup/CompiledDirectoryRemover.php',
        );
    }

    protected function tearDown(): void
    {
        if (!isset($this->projectRoot)) {
            return;
        }

        if (is_dir($this->projectRoot.'/var/cache/prod')) {
            chmod($this->projectRoot.'/var/cache/prod', 0o755);
        }

        (new Filesystem())->remove($this->projectRoot);
    }

    public function testASecondBootFailureStopsWithTheUpdateExitCodeAndTheCommandToRun(): void
    {
        $process = new Process([\PHP_BINARY, $this->projectRoot.'/setup/update.php', '--no-interaction']);
        $process->run();
        $output = $process->getOutput().$process->getErrorOutput();

        self::assertSame(8, $process->getExitCode(), $output);
        self::assertStringContainsString('the database was not changed', $output);
        self::assertStringContainsString('Unable to write in the "cache" directory', $output);
        self::assertStringContainsString(
            'rm -rf '.escapeshellarg($this->projectRoot.'/var/cache/prod'),
            $output,
        );
    }

    /**
     * Stands in for the Composer autoloader: the path constants, the classes the
     * script reaches before the update itself, and the real Filesystem component.
     */
    private function autoloader(): string
    {
        $filesystemSource = var_export(\dirname((new \ReflectionClass(Filesystem::class))->getFileName()).\DIRECTORY_SEPARATOR, true);

        return <<<PHP
            <?php

            namespace {
                define('DS', DIRECTORY_SEPARATOR);
                define('THELIA_ROOT', dirname(__DIR__).DS);
                define('THELIA_CACHE_DIR', THELIA_ROOT.'var'.DS.'cache'.DS);

                spl_autoload_register(static function (string \$class): void {
                    \$prefix = 'Symfony\\\\Component\\\\Filesystem\\\\';

                    if (str_starts_with(\$class, \$prefix)) {
                        require {$filesystemSource}.str_replace('\\\\', DS, substr(\$class, strlen(\$prefix))).'.php';
                    }
                });
            }

            namespace Symfony\Component\Dotenv {
                class Dotenv
                {
                    public function bootEnv(string \$path): void
                    {
                        \$_ENV['APP_ENV'] = 'prod';
                    }
                }
            }

            namespace Thelia\Core {
                class TheliaKernel
                {
                    public const THELIA_VERSION = '0.0.0';

                    public static function resolveEnv(string \$name, ?string \$default = null): ?string
                    {
                        return null;
                    }
                }
            }

            namespace App {
                class Kernel
                {
                    public function __construct(private string \$environment, bool \$debug)
                    {
                    }

                    public function boot(): void
                    {
                        \$cacheDirectory = THELIA_CACHE_DIR.\$this->environment;

                        if (!is_dir(\$cacheDirectory)) {
                            mkdir(\$cacheDirectory, 0o555);
                        }

                        throw new \RuntimeException('Unable to write in the "cache" directory');
                    }
                }
            }
            PHP;
    }
}
