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

namespace Thelia\Domain\Ai\Provider;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\Stream\StreamEvent;

/**
 * An answer read as the provider produces it: iterate it for the TextDelta and ToolCallReceived events,
 * then read result() for the whole answer (tokens, stop reason, state) to build the StreamCompleted event.
 *
 * It is read once. The provider is called, and the errors thrown, when the iteration starts. result() can be read
 * before the end, when the caller stops iterating: it holds what is known then and says so (`complete`).
 *
 * @internal
 *
 * @implements \IteratorAggregate<int, StreamEvent>
 */
#[Exclude]
final class AiProviderStream implements \IteratorAggregate
{
    private bool $started = false;

    /**
     * @param \Closure(AiStreamProgress): \Generator<int, StreamEvent> $events
     */
    public function __construct(
        private readonly \Closure $events,
        private readonly AiStreamProgress $progress,
    ) {
    }

    /**
     * @return \Generator<int, StreamEvent>
     */
    public function getIterator(): \Generator
    {
        if ($this->started) {
            throw new \LogicException('A stream of the AI layer is read once.');
        }

        $this->started = true;

        yield from ($this->events)($this->progress);
    }

    public function result(): AiProviderResult
    {
        return $this->progress->result();
    }
}
