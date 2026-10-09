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

namespace Thelia\Action;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Messenger\WorkerRestartSignal;

/**
 * Class Cache.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 */
class Cache extends BaseAction implements EventSubscriberInterface
{
    /**
     * Removing the cache directory takes the compiled container's lazy service
     * files with it, so any listener still waiting to be loaded from the
     * container would fail to load. The deferred clear therefore runs after
     * every other terminate listener.
     */
    private const TERMINATE_PRIORITY = \PHP_INT_MIN;

    /** @var CacheEvent[] */
    protected array $onTerminateCacheClearEvents = [];

    /** A worker runs in this process: a command it runs ends inside it. */
    private bool $workerRunning = false;

    /**
     * CacheListener constructor.
     */
    public function __construct(
        protected AdapterInterface $adapter,
        protected string $environment,
        protected ?WorkerRestartSignal $workerRestartSignal = null,
    ) {
    }

    public function cacheClear(CacheEvent $event): void
    {
        if (!$event->isOnKernelTerminate()) {
            $this->execCacheClear($event);

            return;
        }

        $findDir = false;

        foreach ($this->onTerminateCacheClearEvents as $cacheEvent) {
            if ($cacheEvent->getDir() === $event->getDir()) {
                // Events are deduplicated per directory, so the one that is kept must
                // carry the schema invalidation as soon as any of them asks for it.
                if ($event->invalidatesPropelSchema()) {
                    $cacheEvent->setInvalidatePropelSchema(true);
                }

                $findDir = true;
                break;
            }
        }

        if (!$findDir) {
            $this->onTerminateCacheClearEvents[] = $event;
        }
    }

    /**
     * A recurring task runs its command inside the worker, and that command ends with a
     * console terminate of its own: the clear waits for the worker to stop.
     */
    public function onConsoleTerminate(): void
    {
        if ($this->workerRunning) {
            return;
        }

        $this->onTerminate();
    }

    public function onWorkerStarted(): void
    {
        $this->workerRunning = true;
    }

    public function onWorkerStopped(): void
    {
        $this->workerRunning = false;
    }

    public function onTerminate(): void
    {
        // A worker runs one command after another in the same process: a clear is done
        // once, not again at the end of every command that follows.
        $cacheEvents = $this->onTerminateCacheClearEvents;
        $this->onTerminateCacheClearEvents = [];

        foreach ($cacheEvents as $cacheEvent) {
            $this->execCacheClear($cacheEvent);
        }
    }

    /**
     * A worker ends no command between two jobs: a clear a job asked for stops the
     * worker, and runs when its command ends. It cannot run as the job ends: the job is
     * not acknowledged yet, and the worker still loads its own listeners from the
     * container files the clear deletes.
     */
    public function stopTheWorkerOnAPendingClear(WorkerRunningEvent $event): void
    {
        if ([] !== $this->onTerminateCacheClearEvents) {
            $event->getWorker()->stop();
        }
    }

    protected function execCacheClear(CacheEvent $event): void
    {
        $this->adapter->clear();

        $fs = new Filesystem();
        $fs->remove($event->getDir());

        // Only once the directory is gone: a worker started again before would boot
        // on the container being deleted.
        $this->workerRestartSignal?->sendIfItHeldTheContainer($event->getDir());

        if (!$event->invalidatesPropelSchema()) {
            return;
        }

        // Invalidate the Propel combined schema so it is recombined on next boot
        // (picks up activated/deactivated modules). Models are only rebuilt if the
        // recombined schema hash actually changes.
        $fs->remove(THELIA_ROOT.'var'.DS.'propel'.DS.$this->environment.DS.'schema');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CACHE_CLEAR => ['cacheClear', 128],
            KernelEvents::TERMINATE => ['onTerminate', self::TERMINATE_PRIORITY],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate', self::TERMINATE_PRIORITY],
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => ['stopTheWorkerOnAPendingClear', self::TERMINATE_PRIORITY],
            WorkerStoppedEvent::class => 'onWorkerStopped',
        ];
    }
}
