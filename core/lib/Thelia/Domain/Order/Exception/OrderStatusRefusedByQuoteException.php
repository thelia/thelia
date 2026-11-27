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

namespace Thelia\Domain\Order\Exception;

/**
 * An order that was cancelled cannot come back to life: the quote it was placed from belongs to
 * another order now, and a quote gives one order at most.
 *
 * Raised as the refusal of a transition the graph would allow, so that every caller that answers
 * a refused transition answers this one.
 */
final class OrderStatusRefusedByQuoteException extends OrderStatusTransitionRefusedException
{
    public const string MESSAGE = 'This order cannot leave its cancelled status: the quote it was placed from has another order now.';

    public function __construct(string $orderRef, string $fromStatusCode, string $toStatusCode)
    {
        parent::__construct($orderRef, $fromStatusCode, $toStatusCode, self::MESSAGE);
    }
}
