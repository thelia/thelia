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

namespace Thelia\Messenger;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Thelia\Log\Tlog;

/**
 * Asks the running workers to stop once their current job is done, as
 * messenger:stop-workers does.
 *
 * A worker keeps the container it booted with: the mail settings compiled into it,
 * the handlers of the modules active then, and lazy service files that a cache clear
 * deletes. Once the cache is cleared, its supervisor starts it again on the new one.
 * The signal lives in the application cache pools (var/pools), which a cache clear
 * leaves in place.
 */
final readonly class WorkerRestartSignal
{
    public function __construct(
        #[Autowire(service: 'cache.messenger.restart_workers_signal')]
        private CacheItemPoolInterface $pool,
        #[Autowire(param: 'kernel.cache_dir')]
        private string $containerCacheDir,
    ) {
    }

    /**
     * Sent when the cleared directory holds the container the workers run on: a clear of
     * the image or document cache leaves them alone.
     */
    public function sendIfItHeldTheContainer(string $clearedDir): void
    {
        $cleared = rtrim($clearedDir, '/\\').\DIRECTORY_SEPARATOR;

        if (str_starts_with(rtrim($this->containerCacheDir, '/\\').\DIRECTORY_SEPARATOR, $cleared)) {
            $this->send();
        }
    }

    private function send(): void
    {
        // The cache is cleared already: a signal that cannot be written leaves the
        // workers to their time limit, it does not undo the clear.
        try {
            $item = $this->pool->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY);
            $item->set(microtime(true));
            $this->pool->save($item);
        } catch (\Throwable $notSent) {
            Tlog::getInstance()->addWarning(\sprintf('The workers were not asked to restart after the cache clear: %s', JobFailureMessage::forLog($notSent)));
        }
    }
}
