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

namespace Thelia\Domain\Quote\Cart;

use Thelia\Domain\Quote\Exception\QuoteException;

/**
 * The cart of a quote holds what the shop and the buyer agreed on: it is not the buyer's to
 * rework, the prices and the quantities are settled and the discount is the one of the quote.
 */
final class QuoteCartLockedException extends QuoteException
{
    public const string LINES_FROZEN = 'quote_cart_locked';
    public const string COUPON_REFUSED = 'quote_cart_coupon';

    private function __construct(private readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function linesAreFrozen(): self
    {
        return new self(self::LINES_FROZEN, 'The lines of a quote cannot be changed: ask the shop for another quote.');
    }

    public static function couponIsRefused(): self
    {
        return new self(self::COUPON_REFUSED, 'A promo code cannot be used on a quote.');
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
