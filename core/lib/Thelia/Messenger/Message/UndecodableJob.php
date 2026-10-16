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

namespace Thelia\Messenger\Message;

/**
 * Stands for a queued job that can no longer be read back.
 *
 * Its class belongs to a module that was turned off or removed, or is not one the
 * shop queues, or its content no longer fits its class. Symfony deletes a message it
 * cannot decode, so such a job would vanish without a trace, and the screen listing
 * the failures would fail on it. It is read as this instead: it keeps the class it
 * was, why it cannot be read and what it held, it runs into a failure on purpose,
 * and an administrator sees it among the failed jobs and deletes it.
 */
final readonly class UndecodableJob implements DescribedJob
{
    public function __construct(
        public string $originalType = '',
        public string $reason = '',
        public string $originalBody = '',
    ) {
    }

    public function describe(): string
    {
        return \sprintf('Unreadable job: %s', $this->reason);
    }
}
