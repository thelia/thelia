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

namespace Thelia\Domain\Customer;

/**
 * The one form an address is compared and counted in.
 *
 * Two places have to agree on it or the guest checkout leaks: the rate limiter keys its
 * per-address budget on it, and the registration decides from it whether an address
 * already has a row. Normalise on one side only and " Jane@Example.COM " buys a fresh
 * budget, or lands on a row the other side thinks is a different address.
 */
final class EmailAddress
{
    public static function normalize(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }
}
