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
 * The cart a quote is paid with no longer holds what was accepted: a line is gone or its quantity
 * is not the one agreed. The quote stays accepted, and the buyer accepts it again to get the cart
 * of the revision back.
 */
final class QuoteCartChangedException extends QuoteException
{
    public const string REASON = 'quote_cart_changed';

    public function __construct()
    {
        parent::__construct('The cart of this quote no longer holds what was accepted: accept the quote again.');
    }

    public function reason(): string
    {
        return self::REASON;
    }
}
