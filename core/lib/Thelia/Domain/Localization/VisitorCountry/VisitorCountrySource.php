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

use Thelia\Model\ConfigQuery;

enum VisitorCountrySource: string
{
    case Header = 'header';
    case IpDatabase = 'ip_database';

    public const CONFIG_KEY = 'visitor_country_source';

    public static function fromShopConfiguration(): self
    {
        return self::tryFrom((string) ConfigQuery::read(self::CONFIG_KEY, self::Header->value))
            ?? self::Header;
    }
}
