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

namespace Thelia\Core\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Thelia\Core\Cache\ConfigCacheService;
use Thelia\Model\Event\ConfigEvent;

/**
 * Drops the shared entry of the configuration whenever a variable is written or deleted.
 *
 * A variable written in a transaction (the state of the catalog that Thelia\Model\Product
 * keeps, a module saving its settings with the rest of a form) drops the entry before its
 * commit: a request served in between rebuilds it from what it can see, the value before the
 * write, and that entry would be kept until the next write of the configuration. The entry is
 * therefore dropped once more when the request that wrote ends, after its commit, the way
 * SaleUnitActivityChecker does for its own entry. A worker serves neither a page nor a command:
 * the end of a job, handled or failed, is the end of its request.
 */
class ModelConfigListener
{
    private bool $writtenDuringTheRequest = false;

    public function __construct(
        private readonly ConfigCacheService $configCacheService,
    ) {
    }

    #[AsEventListener(event: ConfigEvent::POST_SAVE, priority: 128)]
    #[AsEventListener(event: ConfigEvent::POST_DELETE, priority: 128)]
    public function resetCache(): void
    {
        $this->configCacheService->initCacheConfigs(true);
        $this->writtenDuringTheRequest = true;
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    #[AsEventListener(event: WorkerMessageHandledEvent::class)]
    #[AsEventListener(event: WorkerMessageFailedEvent::class)]
    public function resetCacheAfterTheRequest(): void
    {
        if (!$this->writtenDuringTheRequest) {
            return;
        }

        $this->writtenDuringTheRequest = false;
        $this->configCacheService->dropSharedEntry();
    }
}
