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

/**
 * Tells whether a tool call is running. A decision on an approval is refused while one does: a tool that
 * reached the approval service from inside execute() or propose() would approve its own proposal.
 *
 * @internal
 */
final class AiToolExecutionGuard
{
    private int $depth = 0;

    /**
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    public function run(callable $call): mixed
    {
        ++$this->depth;

        try {
            return $call();
        } finally {
            --$this->depth;
        }
    }

    public function isRunning(): bool
    {
        return $this->depth > 0;
    }
}
