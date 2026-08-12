<?php

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
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Class Cache.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 */
class Cache extends BaseAction implements EventSubscriberInterface
{
    /**
     * Entries of the Propel cache a kernel cache clear keeps: the generated
     * models, the database map they rely on, its loader script, and the schema
     * hash they were built from. The hash is what makes keeping them safe —
     * PropelInitService::buildPropelModels() rebuilds everything as soon as the
     * schema recombined on the next boot no longer matches it.
     */
    private const PRESERVED_PROPEL_ENTRIES = ['model', 'database', 'loader', 'hash'];

    /** @var AdapterInterface */
    protected $adapter;

    /** @var string */
    protected $kernelCacheDir;

    /**
     * @var CacheEvent[]
     */
    protected $onTerminateCacheClearEvents = [];

    /**
     * CacheListener constructor.
     */
    public function __construct(AdapterInterface $adapter, string $kernelCacheDir)
    {
        $this->adapter = $adapter;
        $this->kernelCacheDir = $kernelCacheDir;
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
                $findDir = true;
                break;
            }
        }

        if (!$findDir) {
            $this->onTerminateCacheClearEvents[] = $event;
        }
    }

    public function onTerminate(): void
    {
        foreach ($this->onTerminateCacheClearEvents as $cacheEvent) {
            $this->execCacheClear($cacheEvent);
        }
    }

    protected function execCacheClear(CacheEvent $event): void
    {
        $this->adapter->clear();

        $dir = $event->getDir();
        $propelDir = rtrim($dir, DS).DS.'propel';

        $fs = new Filesystem();

        if (!$this->isKernelCacheDir($dir) || !is_dir($propelDir)) {
            $fs->remove($dir);

            return;
        }

        // PropelInitService keeps its cache inside the kernel cache, so removing
        // the whole directory used to take the generated models with it and
        // rebuild all of them on the next boot. Everything else still goes,
        // including the combined schema: it is recombined on the next boot, and
        // the models are rebuilt only if that changes the hash.
        foreach ($this->childrenOf($dir) as $entry) {
            if ('propel' !== $entry) {
                $fs->remove(rtrim($dir, DS).DS.$entry);

                continue;
            }

            foreach ($this->childrenOf($propelDir) as $propelEntry) {
                if (\in_array($propelEntry, self::PRESERVED_PROPEL_ENTRIES, true)) {
                    continue;
                }

                $fs->remove($propelDir.DS.$propelEntry);
            }
        }
    }

    private function isKernelCacheDir(string $dir): bool
    {
        return rtrim($dir, DS) === rtrim($this->kernelCacheDir, DS);
    }

    /**
     * @return string[]
     */
    private function childrenOf(string $dir): array
    {
        return array_diff((array) scandir($dir), ['.', '..']);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CACHE_CLEAR => ['cacheClear', 128],
            KernelEvents::TERMINATE => ['onTerminate', 128],
            ConsoleEvents::TERMINATE => ['onTerminate', 128],
        ];
    }
}
