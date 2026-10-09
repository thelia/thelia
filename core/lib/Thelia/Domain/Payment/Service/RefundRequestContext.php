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

namespace Thelia\Domain\Payment\Service;

use Thelia\Domain\Payment\Enum\RefundReason;

/**
 * What the administrator said of the refund being made, kept while PaymentRefundService
 * works on an order: the line it writes is announced from the journal, which only knows
 * amounts and references. A refund settled later, or made at the provider, finds nothing.
 *
 * @internal shared between PaymentRefundService and AnnounceRefundListener
 */
final class RefundRequestContext
{
    /** @var array<int, array{reason: RefundReason, comment: ?string, offline: bool}> */
    private array $requests = [];

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function during(int $orderId, RefundReason $reason, ?string $comment, bool $offline, callable $work): mixed
    {
        $previous = $this->requests[$orderId] ?? null;
        $this->requests[$orderId] = ['reason' => $reason, 'comment' => $comment, 'offline' => $offline];

        try {
            return $work();
        } finally {
            if (null === $previous) {
                unset($this->requests[$orderId]);
            } else {
                $this->requests[$orderId] = $previous;
            }
        }
    }

    /**
     * @return array{reason: RefundReason, comment: ?string, offline: bool}|null
     */
    public function of(int $orderId): ?array
    {
        return $this->requests[$orderId] ?? null;
    }
}
