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

namespace Thelia\Domain\Checkout\Enum;

/**
 * How far a shop lets a visitor go without creating an account.
 */
enum GuestCheckoutMode: string
{
    /** Ordering requires an account. This is what a shop gets until it says otherwise. */
    case Disabled = 'disabled';

    /** Any cart may be ordered without an account. */
    case Enabled = 'enabled';

    /**
     * Ordering without an account is allowed, except for a cart holding a product the
     * shop marked as needing one.
     */
    case EnabledUnlessProductForbids = 'enabled_unless_product_forbids';

    /**
     * The mode a stored value names, falling back to the strictest one.
     *
     * An unreadable setting must not silently open the checkout: a typo, a value left
     * by an older version or a half-finished edit all mean "the shop did not ask for
     * this", and the answer to that is the default a shop starts with.
     */
    public static function fromStoredValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Disabled;
    }
}
