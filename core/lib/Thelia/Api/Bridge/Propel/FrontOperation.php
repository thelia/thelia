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

namespace Thelia\Api\Bridge\Propel;

use ApiPlatform\Metadata\Operation;

/**
 * Whether an operation is one of the storefront ("/front/..."), as opposed to the admin ones:
 * what the query extensions and the filters that hide the catalog from a visitor ask first.
 */
final class FrontOperation
{
    private const URI_PREFIX = '/front/';

    public static function isFront(?Operation $operation): bool
    {
        return str_starts_with((string) $operation?->getUriTemplate(), self::URI_PREFIX);
    }
}
