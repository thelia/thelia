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
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Action\Cache;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Messenger\WorkerRestartSignal;

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

    /**
     * Removing the cache directory takes the compiled container's lazy service
     * files with it. Outside debug mode listeners are loaded from the container
     * one at a time, as they are called, so a listener that has not run yet
     * would fail to load.
     */
    public function testTheDeferredClearRunsAfterTheOtherTerminateListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->action());

        $clearedDirWasStillThere = null;
        $dispatcher->addListener(
            ConsoleEvents::TERMINATE,
            function () use (&$clearedDirWasStillThere): void {
                $clearedDirWasStillThere = is_dir($this->clearedDir);
            },
            // Symfony's own lowest priority on that event (the console profiler).
            -4096,
        );

        $dispatcher->dispatch(new CacheEvent($this->clearedDir, true, false), TheliaEvents::CACHE_CLEAR);

        self::assertDirectoryExists($this->clearedDir);

        $dispatcher->dispatch(new Event(), ConsoleEvents::TERMINATE);

        self::assertTrue($clearedDirWasStillThere, 'The cache directory must outlive every other terminate listener.');
        self::assertDirectoryDoesNotExist($this->clearedDir);
    }

    /**
     * A worker keeps the container it booted with: its mail settings, the handlers
     * of the modules active then, and lazy service files the clear deletes.
     */
    public function testAClearAsksTheWorkersToRestartOnceTheDirectoryIsGone(): void
    {
        $clearedDir = $this->clearedDir;
        $signals = new class($clearedDir) extends ArrayAdapter {
            public ?bool $directoryWasGone = null;

            public function __construct(private readonly string $clearedDir)
            {
                parent::__construct();
            }

            public function save(CacheItemInterface $item): bool
            {
                $this->directoryWasGone = !is_dir($this->clearedDir);

                return parent::save($item);
            }
        };

        (new Cache(new NullAdapter(), self::ENVIRONMENT, new WorkerRestartSignal($signals, $this->clearedDir)))
            ->cacheClear(new CacheEvent($this->clearedDir, false));

        self::assertTrue($signals->hasItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY));
        self::assertTrue($signals->directoryWasGone, 'A worker started again before the clear would boot on the old container.');
    }

    public function testADeferredClearAsksTheWorkersToRestartOnlyWhenItRuns(): void
    {
        $signals = new ArrayAdapter();
        $action = new Cache(new NullAdapter(), self::ENVIRONMENT, new WorkerRestartSignal($signals, $this->clearedDir));

        $action->cacheClear(new CacheEvent($this->clearedDir, true));
        self::assertFalse($signals->hasItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY));

        $action->onTerminate();
        self::assertTrue($signals->hasItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY));
    }

    public function testADeferredClearRunsOnceInAProcessThatEndsSeveralCommands(): void
    {
        $signals = new ArrayAdapter();
        $action = new Cache(new NullAdapter(), self::ENVIRONMENT, new WorkerRestartSignal($signals, $this->clearedDir));

        $action->cacheClear(new CacheEvent($this->clearedDir, true));
        $action->onTerminate();
        $signals->clear();

        // The next command a worker runs ends too.
        $action->onTerminate();

        self::assertFalse($signals->hasItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY));
    }

    /**
     * The image and document caches hold nothing a worker runs on.
     */
    public function testAClearOfAnotherCacheLeavesTheWorkersAlone(): void
    {
        $signals = new ArrayAdapter();
        $containerDir = $this->clearedDir.'-container';

        (new Cache(new NullAdapter(), self::ENVIRONMENT, new WorkerRestartSignal($signals, $containerDir)))
            ->cacheClear(new CacheEvent($this->clearedDir, false));

        self::assertFalse($signals->hasItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY));
    }

    public function testAClearOfTheDirectoryAboveTheContainerAsksTheWorkersToRestart(): void
    {
        $signals = new ArrayAdapter();

        (new Cache(new NullAdapter(), self::ENVIRONMENT, new WorkerRestartSignal($signals, $this->clearedDir.'/dev')))
            ->cacheClear(new CacheEvent($this->clearedDir.'/', false));

        self::assertTrue($signals->hasItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY));
    }

    /**
     * A worker ends no command between two jobs: a clear a job asked for runs once
     * that job is over, not when the worker exits.
     */
    public function testAClearAJobAskedForRunsOnceTheJobIsOver(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->action());

        $dispatcher->dispatch(new CacheEvent($this->clearedDir, true, false), TheliaEvents::CACHE_CLEAR);
        self::assertDirectoryExists($this->clearedDir);

        $dispatcher->dispatch(new WorkerMessageHandledEvent(new Envelope(new \stdClass()), 'async'));

        self::assertDirectoryDoesNotExist($this->clearedDir);
    }

    private function action(): Cache
    {
        return new Cache(new NullAdapter(), self::ENVIRONMENT);
    }
}
