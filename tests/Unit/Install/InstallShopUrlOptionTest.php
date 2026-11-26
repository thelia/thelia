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
 * `bin/install --url` gives the address of the shop. An address the links of the shop
 * cannot be built on stops the install before anything is created.
 *
 * The script runs on a stand-in tree with no database configured: should it go past the
 * option, it stops on the missing credentials instead of installing anything.
 */
final class InstallShopUrlOptionTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir().'/thelia-install-url-'.bin2hex(random_bytes(6));

        $filesystem = new Filesystem();
        $filesystem->mkdir([$this->projectRoot.'/bin', $this->projectRoot.'/vendor']);
        $filesystem->dumpFile($this->projectRoot.'/.env', "APP_ENV=dev\n");
        $filesystem->dumpFile(
            $this->projectRoot.'/vendor/autoload.php',
            '<?php return require '.var_export(THELIA_ROOT.'vendor/autoload.php', true).";\n",
        );
        $filesystem->copy(THELIA_ROOT.'bin/install', $this->projectRoot.'/bin/install');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectRoot);
    }

    public function testAnAddressThatIsNotAbsoluteStopsTheInstall(): void
    {
        $process = $this->install('--url=shop.example');

        self::assertSame(1, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        self::assertStringContainsString('ERROR: --url must be an absolute http or https address', $process->getErrorOutput());
    }

    public function testTheHelpNamesTheOption(): void
    {
        $process = $this->install('--help');

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('--url=URL', $process->getOutput());
    }

    private function install(string ...$arguments): Process
    {
        $process = new Process(
            [\PHP_BINARY, $this->projectRoot.'/bin/install', ...$arguments],
            $this->projectRoot,
            ['DATABASE_HOST' => false, 'DATABASE_NAME' => false, 'DATABASE_USER' => false, 'DATABASE_PASSWORD' => false, 'DATABASE_PORT' => false, 'APP_ENV' => 'dev'],
        );
        $process->run();

        return $process;
    }
}
