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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Test\ActionIntegrationTestCase;

final class CacheActionTest extends ActionIntegrationTestCase
{
    public function testCacheClearRemovesTargetDirectory(): void
    {
        $tmpDir = sys_get_temp_dir().'/thelia_test_cache_'.uniqid();
        mkdir($tmpDir, 0o777, true);
        file_put_contents($tmpDir.'/dummy.txt', 'data');

        self::assertDirectoryExists($tmpDir);

        // onKernelTerminate = false → immediate clear.
        $event = new CacheEvent($tmpDir, false);
        $this->dispatch($event, TheliaEvents::CACHE_CLEAR);

        self::assertDirectoryDoesNotExist($tmpDir);
    }

    public function testCacheClearWithKernelTerminateDefersClear(): void
    {
        $tmpDir = sys_get_temp_dir().'/thelia_test_cache_deferred_'.uniqid();
        mkdir($tmpDir, 0o777, true);

        // onKernelTerminate = true → deferred, directory should still exist.
        $event = new CacheEvent($tmpDir, true);
        $this->dispatch($event, TheliaEvents::CACHE_CLEAR);

        self::assertDirectoryExists($tmpDir);

        // Clean up.
        rmdir($tmpDir);
    }
}
