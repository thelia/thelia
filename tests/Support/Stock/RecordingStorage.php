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

namespace Thelia\Tests\Support\Stock;

use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * A budget in memory that tells a callback each time a token is spent: the moment of the spending
 * can then be compared with what the code under test has written by then.
 */
final class RecordingStorage implements StorageInterface
{
    private readonly InMemoryStorage $inner;

    public function __construct(private readonly \Closure $onSave)
    {
        $this->inner = new InMemoryStorage();
    }

    public function save(LimiterStateInterface $limiterState): void
    {
        ($this->onSave)();
        $this->inner->save($limiterState);
    }

    public function fetch(string $limiterStateId): ?LimiterStateInterface
    {
        return $this->inner->fetch($limiterStateId);
    }

    public function delete(string $limiterStateId): void
    {
        $this->inner->delete($limiterStateId);
    }
}
