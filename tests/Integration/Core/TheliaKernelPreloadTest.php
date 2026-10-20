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

namespace Thelia\Tests\Integration\Core;

use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * opcache.preload runs the preload file Symfony writes next to the container before
 * any kernel exists. The file lists the core loops, which extend the Propel models
 * generated in var/propel/<env>/model: it has to make them autoloadable itself, or
 * PHP-FPM dies on the first model it meets.
 *
 * The file is run in a subprocess, which has only the Composer autoloader, as
 * opcache.preload has.
 */
final class TheliaKernelPreloadTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/thelia-preload-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testThePreloadFileMakesThePropelModelsAutoloadable(): void
    {
        $kernel = new class('test', false) extends Kernel {
            public function dumpEmptyContainer(string $directory): string
            {
                $container = new ContainerBuilder();
                $container->compile();
                $this->dumpContainer(new ConfigCache($directory.'/PreloadProbeContainer.php', false), $container, 'PreloadProbeContainer', 'Container');

                return $directory.'/PreloadProbeContainer.preload.php';
            }
        };

        $preloadFile = $kernel->dumpEmptyContainer($this->directory);

        $process = new Process([
            \PHP_BINARY,
            '-r',
            \sprintf(
                'require %s; require %s; echo class_exists(%s) ? "autoloadable" : "missing";',
                var_export(THELIA_ROOT.'vendor/autoload.php', true),
                var_export($preloadFile, true),
                var_export('Thelia\Model\Base\ProductQuery', true),
            ),
        ]);
        $process->run();

        self::assertSame('autoloadable', trim($process->getOutput()), $process->getErrorOutput());
    }
}
