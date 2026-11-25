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

namespace Thelia\Domain\Quote\Enum;

/**
 * How far a shop lets a buyer ask for a quote instead of ordering at once.
 */
enum QuoteMode: string
{
    /** No quote: carts are ordered as they are. This is what a shop gets until it says otherwise. */
    case Disabled = 'disabled';

    /** A buyer may ask for a quote or order the cart straight away. */
    case Alongside = 'alongside';

    /** A cart is only ordered through a quote. */
    case Only = 'only';

    /**
     * The mode a stored value names, falling back to the one a shop starts with.
     *
     * An unreadable setting must not silently force every buyer through a quote: a typo or a
     * half-finished edit means the shop did not ask for it.
     */
    public static function fromConfig(string $value): self
    {
        return self::tryFrom($value) ?? self::Disabled;
    }
}
