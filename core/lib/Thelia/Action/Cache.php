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
    /** @var AdapterInterface */
    protected $adapter;

    /**
     * @var CacheEvent[]
     */
    protected $onTerminateCacheClearEvents = [];

    /**
     * CacheListener constructor.
     */
    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
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

        $fs = new Filesystem();
        $fs->remove($dir);
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
