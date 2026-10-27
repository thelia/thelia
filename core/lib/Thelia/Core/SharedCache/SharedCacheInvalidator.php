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

namespace Thelia\Core\SharedCache;

use Thelia\Api\Service\API\ResourceCacheInvalidationListener;

/**
 * Purges the pages tagged with some keys. The keys are sent to the proxy when the request
 * ends, and the data access cache pool is flushed then too, through the same marking as
 * every other write: the write that asks for the purge is still in its transaction, and a
 * pool flushed before the commit is refilled by a concurrent reader with the rows as they
 * were. A page rebuilt after the purge cannot read a stale answer.
 */
final readonly class SharedCacheInvalidator
{
    public function __construct(
        private SharedCachePurgeQueue $queue,
        private ResourceCacheInvalidationListener $resourceCacheInvalidation,
    ) {
    }

    /**
     * @param list<string> $keys
     */
    public function purge(array $keys): void
    {
        if ([] === $keys) {
            return;
        }

        if (SharedCacheMode::DISABLED !== SharedCacheMode::fromShopConfiguration()) {
            $this->resourceCacheInvalidation->clear();
        }

        $this->queue->add($keys);
    }
}
