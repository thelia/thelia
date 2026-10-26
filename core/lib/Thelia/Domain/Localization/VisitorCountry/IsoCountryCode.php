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

namespace Thelia\Domain\Localization\VisitorCountry;

/**
 * The header and the database are outside the shop, and a header is whatever the client
 * sent when no proxy rewrites it: only two letters ever reach a query.
 */
final readonly class IsoCountryCode
{
    public static function normalize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $code = strtoupper(trim($value));

        return 1 === preg_match('/^[A-Z]{2}$/', $code) ? $code : null;
    }
}
