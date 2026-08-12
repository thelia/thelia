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

namespace Thelia\Tests\Unit\Action;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Action\Cache;
use Thelia\Core\Event\Cache\CacheEvent;

/**
 * PropelInitService keeps its cache inside the kernel cache, so clearing the
 * kernel cache used to take the generated Propel models with it and rebuild all
 * of them on the next boot. What has to survive is exactly what the schema hash
 * guards; everything else, the combined schema included, still goes.
 */
final class CacheTest extends TestCase
{
    private Filesystem $filesystem;
    private string $kernelCacheDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->kernelCacheDir = sys_get_temp_dir().'/thelia_cache_action_'.uniqid();

        foreach ([
            'ContainerAbc123/Thelia_KernelDevDebugContainer.php',
            'propel/model/Thelia/Model/Base/Product.php',
            'propel/model/hash',
            'propel/database/TheliaMain/TheliaMainDatabase.php',
            'propel/loader/loadDatabase.php',
            'propel/hash',
            'propel/config/propel.yml',
            'propel/schema/TheliaMain.schema.xml',
        ] as $file) {
            $this->filesystem->dumpFile($this->kernelCacheDir.'/'.$file, 'x');
        }
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->kernelCacheDir);
    }

    public function testClearingTheKernelCacheKeepsTheGeneratedPropelModels(): void
    {
        $this->action()->cacheClear(new CacheEvent($this->kernelCacheDir, false));

        self::assertDirectoryDoesNotExist($this->kernelCacheDir.'/ContainerAbc123');
        self::assertFileExists($this->kernelCacheDir.'/propel/model/Thelia/Model/Base/Product.php');
        self::assertFileExists($this->kernelCacheDir.'/propel/model/hash');
        self::assertFileExists($this->kernelCacheDir.'/propel/database/TheliaMain/TheliaMainDatabase.php');
        self::assertFileExists($this->kernelCacheDir.'/propel/loader/loadDatabase.php');
        self::assertFileExists($this->kernelCacheDir.'/propel/hash');
    }

    public function testClearingTheKernelCacheStillInvalidatesTheCombinedSchema(): void
    {
        $this->action()->cacheClear(new CacheEvent($this->kernelCacheDir, false));

        // Recombined on the next boot, which is what makes keeping the models
        // safe: a schema that really moved no longer matches their hash.
        self::assertDirectoryDoesNotExist($this->kernelCacheDir.'/propel/schema');
        self::assertDirectoryDoesNotExist($this->kernelCacheDir.'/propel/config');
    }

    public function testClearingAnyOtherDirectoryRemovesItEntirely(): void
    {
        $assetsDir = sys_get_temp_dir().'/thelia_cache_action_assets_'.uniqid();
        $this->filesystem->dumpFile($assetsDir.'/propel/model/keep-nothing', 'x');

        $this->action()->cacheClear(new CacheEvent($assetsDir, false));

        self::assertDirectoryDoesNotExist($assetsDir);
    }

    private function action(): Cache
    {
        return new Cache(new NullAdapter(), $this->kernelCacheDir);
    }
}
