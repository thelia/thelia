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
 * The combined Propel schema depends on the active modules and on their
 * schema.xml. A clear that follows something else — a hook reordering, a
 * translation — has to leave it in place, or the boot that follows recombines
 * the schema and resets the opcode cache for nothing.
 */
final class CacheTest extends TestCase
{
    private const ENVIRONMENT = 'cache-action-test';

    private Filesystem $filesystem;
    private string $propelSchemaDir;
    private string $clearedDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->propelSchemaDir = THELIA_ROOT.'var'.\DIRECTORY_SEPARATOR.'propel'
            .\DIRECTORY_SEPARATOR.self::ENVIRONMENT.\DIRECTORY_SEPARATOR.'schema';
        $this->clearedDir = sys_get_temp_dir().'/thelia_cache_action_'.uniqid();

        $this->filesystem->dumpFile(
            $this->propelSchemaDir.\DIRECTORY_SEPARATOR.'TheliaMain.schema.xml',
            '<database/>',
        );
        $this->filesystem->mkdir($this->clearedDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove([
            THELIA_ROOT.'var'.\DIRECTORY_SEPARATOR.'propel'.\DIRECTORY_SEPARATOR.self::ENVIRONMENT,
            $this->clearedDir,
        ]);
    }

    public function testClearInvalidatesThePropelSchemaByDefault(): void
    {
        $this->action()->cacheClear(new CacheEvent($this->clearedDir, false));

        self::assertDirectoryDoesNotExist($this->clearedDir);
        self::assertDirectoryDoesNotExist($this->propelSchemaDir);
    }

    public function testClearKeepsThePropelSchemaWhenTheCallerRulesItOut(): void
    {
        $this->action()->cacheClear(new CacheEvent($this->clearedDir, false, false));

        self::assertDirectoryDoesNotExist($this->clearedDir);
        self::assertDirectoryExists($this->propelSchemaDir);
    }

    public function testDeferredClearsKeepTheSchemaInvalidationOfAnyDeduplicatedEvent(): void
    {
        $action = $this->action();

        // Same directory, so the second event is dropped: the invalidation it
        // carries has to survive on the event that is kept.
        $action->cacheClear(new CacheEvent($this->clearedDir, true, false));
        $action->cacheClear(new CacheEvent($this->clearedDir, true, true));

        self::assertDirectoryExists($this->propelSchemaDir);

        $action->onTerminate();

        self::assertDirectoryDoesNotExist($this->clearedDir);
        self::assertDirectoryDoesNotExist($this->propelSchemaDir);
    }

    private function action(): Cache
    {
        return new Cache(new NullAdapter(), self::ENVIRONMENT);
    }
}
