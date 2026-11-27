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
 * The currency the quote was written in is no longer one of the shop: nothing can be charged in it.
 */
final class QuoteCurrencyUnavailableException extends QuoteException
{
    public const string REASON = 'quote_currency_unavailable';

    public function __construct()
    {
        parent::__construct('The currency of this quote is no longer available, so it cannot be accepted.');
    }

    public function reason(): string
    {
        return self::REASON;
    }
}
