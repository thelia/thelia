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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Service\ResetInterface;

final class SharedCachePurgeQueue implements ResetInterface
{
    /** @var array<string, true> */
    private array $keys = [];

    private bool $everything = false;

    public function __construct(
        private readonly SharedCachePurgerInterface $purger,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param list<string> $keys
     */
    public function add(array $keys): void
    {
        foreach ($keys as $key) {
            if (SharedCacheKey::ALL === $key) {
                $this->everything = true;

                continue;
            }

            $this->keys[$key] = true;
        }
    }

    public function flush(): void
    {
        $keys = $this->everything ? [SharedCacheKey::ALL] : array_keys($this->keys);
        $this->reset();

        if ([] === $keys) {
            return;
        }

        try {
            $this->purger->purge($keys);
        } catch (\Throwable $exception) {
            $this->logger->error(\sprintf('Shared cache purge of %d key(s) failed: %s', \count($keys), $exception->getMessage()));
        }
    }

    public function reset(): void
    {
        $this->keys = [];
        $this->everything = false;
    }
}
