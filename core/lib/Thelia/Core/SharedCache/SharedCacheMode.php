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

namespace Thelia\Core\SharedCache;

use Thelia\Model\ConfigQuery;

enum SharedCacheMode: string
{
    case DISABLED = 'disabled';
    case ANNOUNCE = 'announce';
    case ENABLED = 'enabled';

    public const CONFIG_KEY = 'shared_cache_mode';

    public static function fromShopConfiguration(): self
    {
        return self::tryFrom((string) ConfigQuery::read(self::CONFIG_KEY, self::DISABLED->value))
            ?? self::DISABLED;
    }
}
