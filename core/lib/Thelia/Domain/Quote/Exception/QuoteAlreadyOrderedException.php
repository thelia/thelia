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

namespace Thelia\Domain\Quote\Exception;

/**
 * A quote gives one order at most, and this one already has it.
 */
final class QuoteAlreadyOrderedException extends QuoteException
{
    public const string REASON = 'quote_already_ordered';

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('An order was already placed for this quote.', 0, $previous);
    }

    public function reason(): string
    {
        return self::REASON;
    }
}
