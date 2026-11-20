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
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * The update scripts write the `config` table in SQL (3.4.0.sql stores
 * thelia_version and last_float_line_rounding_order_id), and so does a restored
 * backup. Neither drops the shared configuration entry, which with
 * THELIA_CACHE_DSN lives in a remote cache that removing the cache directory does
 * not reach: every process would go on reading the settings of the previous
 * release until a setting is saved (#4364). `setup/update.php` drops it once the
 * scripts have run, and again once a backup is restored.
 *
 * The script runs on a stand-in tree whose update and configuration cache write,
 * in order, what they were asked to do.
 */
final class UpdateScriptConfigCacheTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir().'/thelia-update-config-cache-'.bin2hex(random_bytes(6));

        $filesystem = new Filesystem();
        $filesystem->mkdir([$this->projectRoot.'/vendor', $this->projectRoot.'/setup', $this->projectRoot.'/var/cache/prod']);
        $filesystem->dumpFile($this->projectRoot.'/.env', "APP_ENV=prod\n");
        $filesystem->copy(THELIA_SETUP_DIRECTORY.'update.php', $this->projectRoot.'/setup/update.php');
        $filesystem->copy(
            THELIA_SETUP_DIRECTORY.'CompiledDirectoryRemover.php',
            $this->projectRoot.'/setup/CompiledDirectoryRemover.php',
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->projectRoot)) {
            (new Filesystem())->remove($this->projectRoot);
        }
    }

    public function testTheSharedConfigurationIsDroppedOnceTheScriptsHaveRun(): void
    {
        (new Filesystem())->dumpFile($this->projectRoot.'/vendor/autoload.php', $this->autoloader(failingUpdate: false));

        $process = $this->runUpdate();

        self::assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        self::assertSame(['backup', 'process', 'drop'], $this->journal());
    }

    public function testTheSharedConfigurationIsDroppedAgainOnceABackupIsRestored(): void
    {
        (new Filesystem())->dumpFile($this->projectRoot.'/vendor/autoload.php', $this->autoloader(failingUpdate: true));

        $process = $this->runUpdate();

        self::assertSame(5, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        self::assertSame(['backup', 'process', 'drop', 'restore', 'drop'], $this->journal());
    }

    private function runUpdate(): Process
    {
        $process = new Process([\PHP_BINARY, $this->projectRoot.'/setup/update.php', '--no-interaction']);
        $process->run();

        return $process;
    }

    /**
     * @return list<string>
     */
    private function journal(): array
    {
        $file = $this->projectRoot.'/journal';

        return is_file($file) ? file($file, \FILE_IGNORE_NEW_LINES) : [];
    }

    /**
     * Stands in for the Composer autoloader: the path constants, the real
     * Filesystem and Finder components, a kernel whose container holds the
     * configuration cache, and an update that succeeds or fails.
     */
    private function autoloader(bool $failingUpdate): string
    {
        $filesystemSource = var_export(\dirname((new \ReflectionClass(Filesystem::class))->getFileName()).\DIRECTORY_SEPARATOR, true);
        $finderSource = var_export(\dirname((new \ReflectionClass(Finder::class))->getFileName()).\DIRECTORY_SEPARATOR, true);
        $process = $failingUpdate
            ? "throw new Exception\\UpdateException('3.4.0.sql failed');"
            : '';

        return <<<PHP
            <?php

            namespace {
                define('DS', DIRECTORY_SEPARATOR);
                define('THELIA_ROOT', dirname(__DIR__).DS);
                define('THELIA_CACHE_DIR', THELIA_ROOT.'var'.DS.'cache'.DS);

                function journal(string \$entry): void
                {
                    file_put_contents(THELIA_ROOT.'journal', \$entry."\\n", FILE_APPEND);
                }

                spl_autoload_register(static function (string \$class): void {
                    foreach (['Symfony\\\\Component\\\\Filesystem\\\\' => {$filesystemSource}, 'Symfony\\\\Component\\\\Finder\\\\' => {$finderSource}] as \$prefix => \$source) {
                        if (str_starts_with(\$class, \$prefix)) {
                            require \$source.str_replace('\\\\', DS, substr(\$class, strlen(\$prefix))).'.php';
                        }
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
                    public const THELIA_VERSION = '3.4.0';

                    public static function resolveEnv(string \$name, ?string \$default = null): ?string
                    {
                        return null;
                    }
                }
            }

            namespace Thelia\Core\Cache {
                class ConfigCacheService
                {
                    public const CACHE_KEY = 'thelia_config';

                    public function dropSharedEntry(): void
                    {
                        journal('drop');
                    }
                }
            }

            namespace Thelia\Core\Install\Exception {
                class UpdateException extends \Exception
                {
                }
            }

            namespace Thelia\Core\Install {
                class Update
                {
                    public function __construct(bool \$usePropel)
                    {
                    }

                    public function isLatestVersion(): bool
                    {
                        return false;
                    }

                    public function getCurrentVersion(): string
                    {
                        return '3.3.0';
                    }

                    public function getLatestVersion(): string
                    {
                        return '3.4.0';
                    }

                    public function getWebVersion(): ?string
                    {
                        return null;
                    }

                    public function backupDb(): void
                    {
                        journal('backup');
                    }

                    public function getBackupFile(): string
                    {
                        return 'update.sql';
                    }

                    public function process(bool \$backup): array
                    {
                        journal('process');
                        {$process}

                        return ['3.4.0'];
                    }

                    public function getMessages(): array
                    {
                        return [];
                    }

                    public function hasPostInstructions(): bool
                    {
                        return false;
                    }

                    public function getLogs(): array
                    {
                        return [];
                    }

                    public function getUpdatedVersions(): array
                    {
                        return [];
                    }

                    public function restoreDb(): bool
                    {
                        journal('restore');

                        return true;
                    }

                    public function getRestoreFailure(): ?string
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
                    }

                    public function getContainer(): object
                    {
                        return new class {
                            public function get(string \$id): object
                            {
                                return new \$id();
                            }
                        };
                    }
                }
            }
            PHP;
    }
}
