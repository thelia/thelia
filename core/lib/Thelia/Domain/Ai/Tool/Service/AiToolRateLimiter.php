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

namespace Thelia\Domain\Ai\Tool\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Thelia\Domain\Ai\Service\AiActorHasher;
use Thelia\Domain\Ai\Tool\AiToolContext;

/**
 * At most 50 tool calls per task in a sliding window of 15 minutes. A task is the correlation identifier of
 * the context; without one, the limit holds for the actor and the usage. The key is a HMAC: the identifier of
 * a task never reaches the cache in clear.
 *
 * @internal
 */
final readonly class AiToolRateLimiter
{
    public const int CALLS_PER_TASK = 50;

    public const string WINDOW = '15 minutes';

    public function __construct(
        #[Autowire(service: 'cache.rate_limiter')]
        private CacheItemPoolInterface $pool,
        private AiActorHasher $actors,
        #[\SensitiveParameter]
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    /**
     * Counts one call.
     *
     * @phpstan-impure
     *
     * @return bool false when the task made too many calls
     */
    public function consume(AiToolContext $context): bool
    {
        $key = null !== $context->correlationId
            ? hash_hmac('sha256', 'tool-task:'.$context->correlationId, $this->secret)
            : hash_hmac('sha256', 'tool-actor:'.$this->actors->hash($context->actor).':'.$context->usageId, $this->secret);

        $factory = new RateLimiterFactory(['id' => 'ai_tool_task', 'policy' => 'sliding_window', 'limit' => self::CALLS_PER_TASK, 'interval' => self::WINDOW], new CacheStorage($this->pool));

        return $factory->create($key)->consume()->isAccepted();
    }
}
