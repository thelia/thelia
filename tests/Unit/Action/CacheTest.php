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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Worker;
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

        $dispatcher->dispatch(self::ended(new Command('thelia:cache:clear')), ConsoleEvents::TERMINATE);

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
     * A worker ends no command between two jobs: a clear a job asked for stops the
     * worker once the job is acknowledged, and runs when the command ends. Clearing
     * right after the job would delete the lazy listener files the worker has not
     * loaded yet, before the transport even acknowledges the job.
     */
    public function testAClearAJobAskedForStopsTheWorkerAndRunsOnceTheCommandEnds(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->action());
        $this->addALazyWorkerListenerStoredIn($dispatcher, $this->clearedDir);
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new \stdClass()));
        $transport->send(new Envelope(new \stdClass()));

        $this->worker($transport, $dispatcher, function () use ($dispatcher): void {
            $dispatcher->dispatch(new CacheEvent($this->clearedDir, true, false), TheliaEvents::CACHE_CLEAR);
        })->run();

        self::assertCount(1, $transport->getAcknowledged(), 'The job that asked for the clear is acknowledged.');
        self::assertCount(1, iterator_to_array($transport->get()), 'The worker stops before the next job.');
        self::assertDirectoryExists($this->clearedDir);

        $dispatcher->dispatch(self::ended(new Command('thelia:cache:clear')), ConsoleEvents::TERMINATE);

        self::assertDirectoryDoesNotExist($this->clearedDir);
    }

    /**
     * A recurring task runs its command inside the worker, and that command ends with a
     * console terminate of its own: the clear it asked for still waits for the command
     * of the worker to end, not for the nested one.
     */
    public function testAClearAScheduledCommandAskedForWaitsForTheWorkerToStop(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->action());
        $this->addALazyWorkerListenerStoredIn($dispatcher, $this->clearedDir);
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new \stdClass()));

        $worker = new Command('messenger:consume');
        $task = new Command('sale:check-activation');
        $dispatcher->dispatch(self::started($worker), ConsoleEvents::COMMAND);
        $this->worker($transport, $dispatcher, function () use ($dispatcher, $task): void {
            $dispatcher->dispatch(self::started($task), ConsoleEvents::COMMAND);
            $dispatcher->dispatch(new CacheEvent($this->clearedDir, true, false), TheliaEvents::CACHE_CLEAR);
            $dispatcher->dispatch(self::ended($task), ConsoleEvents::TERMINATE);
        })->run();

        self::assertCount(1, $transport->getAcknowledged());
        self::assertDirectoryExists($this->clearedDir);

        $dispatcher->dispatch(self::ended($worker), ConsoleEvents::TERMINATE);

        self::assertDirectoryDoesNotExist($this->clearedDir);
    }

    /**
     * A worker may die on an exception (its queue gone as it acknowledges a job), and then
     * never says it stopped: the command that ran it still ends, and the clear runs then.
     */
    public function testAClearAJobAskedForRunsEvenWhenTheWorkerDies(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->action());
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event): void {
            if (!$event->isWorkerIdle()) {
                throw new \RuntimeException('The queue is gone.');
            }
        }, 1024);
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new \stdClass()));

        $worker = new Command('messenger:consume');
        $dispatcher->dispatch(self::started($worker), ConsoleEvents::COMMAND);

        try {
            $this->worker($transport, $dispatcher, function () use ($dispatcher): void {
                $dispatcher->dispatch(new CacheEvent($this->clearedDir, true, false), TheliaEvents::CACHE_CLEAR);
            })->run();
            self::fail('The worker dies.');
        } catch (\RuntimeException) {
        }

        $dispatcher->dispatch(self::ended($worker), ConsoleEvents::TERMINATE);

        self::assertDirectoryDoesNotExist($this->clearedDir);
    }

    /**
     * A listener of the start of a command that fails before the cache action counted it
     * still ends with that command: the end of a command never counted leaves the
     * worker's clear waiting for the worker.
     */
    public function testACommandNeverCountedNeverClearsUnderTheWorker(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->action());
        $this->addALazyWorkerListenerStoredIn($dispatcher, $this->clearedDir);
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new \stdClass()));
        $worker = new Command('messenger:consume');
        $task = new Command('sale:check-activation');

        $dispatcher->dispatch(self::started($worker), ConsoleEvents::COMMAND);
        $this->worker($transport, $dispatcher, function () use ($dispatcher, $task): void {
            $dispatcher->dispatch(new CacheEvent($this->clearedDir, true, false), TheliaEvents::CACHE_CLEAR);
            $dispatcher->dispatch(self::ended($task), ConsoleEvents::TERMINATE);
        })->run();

        self::assertDirectoryExists($this->clearedDir);

        $dispatcher->dispatch(self::ended($worker), ConsoleEvents::TERMINATE);

        self::assertDirectoryDoesNotExist($this->clearedDir);
    }

    public function testAClearThatFailsNeverLeavesTheJobUnacknowledged(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new Cache($this->failingAdapter(), self::ENVIRONMENT));
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new \stdClass()));

        $this->worker($transport, $dispatcher, function () use ($dispatcher): void {
            $dispatcher->dispatch(new CacheEvent($this->clearedDir, true, false), TheliaEvents::CACHE_CLEAR);
        })->run();

        self::assertCount(1, $transport->getAcknowledged());
    }

    public function testAClearAFailedJobAskedForStopsTheWorkerToo(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->action());
        $this->addALazyWorkerListenerStoredIn($dispatcher, $this->clearedDir);
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new \stdClass()));
        $transport->send(new Envelope(new \stdClass()));

        $this->worker($transport, $dispatcher, function () use ($dispatcher): void {
            $dispatcher->dispatch(new CacheEvent($this->clearedDir, true, false), TheliaEvents::CACHE_CLEAR);

            throw new \RuntimeException('The job failed after asking for a clear.');
        })->run();

        self::assertCount(1, $transport->getRejected());
        self::assertCount(1, iterator_to_array($transport->get()));
        self::assertDirectoryExists($this->clearedDir);
    }

    private static function started(Command $command): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput());
    }

    private static function ended(Command $command): ConsoleTerminateEvent
    {
        return new ConsoleTerminateEvent($command, new ArrayInput([]), new NullOutput(), Command::SUCCESS);
    }

    private function worker(InMemoryTransport $transport, EventDispatcher $dispatcher, \Closure $handler): Worker
    {
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([\stdClass::class => [$handler]]))]);
        // Stops an idle worker, as nothing else would in a test.
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event): void {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        });

        return new Worker(['async' => $transport], $bus, $dispatcher);
    }

    /**
     * Outside debug mode, the container loads a worker listener from its own file the
     * first time the event it listens to is dispatched.
     */
    private function addALazyWorkerListenerStoredIn(EventDispatcher $dispatcher, string $containerDir): void
    {
        $listenerFile = $containerDir.'/getWorkerListenerService.php';
        $this->filesystem->dumpFile($listenerFile, '<?php return static function (): void {};');

        $dispatcher->addListener(WorkerRunningEvent::class, [
            static fn (): \Closure => is_file($listenerFile)
                ? require $listenerFile
                : throw new \LogicException('The worker listener was loaded after its container file was deleted.'),
            '__invoke',
        ]);
    }

    private function failingAdapter(): NullAdapter
    {
        return new class extends NullAdapter {
            public function clear(string $prefix = ''): bool
            {
                throw new \RuntimeException('The cache could not be cleared.');
            }
        };
    }

    private function action(): Cache
    {
        return new Cache(new NullAdapter(), self::ENVIRONMENT);
    }
}
