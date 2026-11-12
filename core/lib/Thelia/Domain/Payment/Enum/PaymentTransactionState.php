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

namespace Thelia\Domain\Payment\Enum;

/**
 * The outcome of a payment journal line.
 *
 * PENDING is the only state a line leaves: it is written before the provider is
 * called, so that a call that never comes back still leaves its trace, and settled to
 * SUCCEEDED or FAILED when the answer arrives.
 */
enum PaymentTransactionState: string
{
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';

    public function isSettled(): bool
    {
        return self::PENDING !== $this;
    }
}
