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

namespace Thelia\Domain\Webhook;

final readonly class WebhookRetryPolicy
{
    private const array DELAYS_IN_SECONDS = [60, 300, 1800, 7200];

    public function maxAttempts(): int
    {
        return \count(self::DELAYS_IN_SECONDS) + 1;
    }

    public function shouldRetry(int $attempt): bool
    {
        return $attempt < $this->maxAttempts();
    }

    public function delayAfter(int $attempt): ?int
    {
        if ($attempt < 1) {
            throw new \InvalidArgumentException('An attempt number starts at 1.');
        }

        return self::DELAYS_IN_SECONDS[$attempt - 1] ?? null;
    }
}
