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

/**
 * What the shop does with the country it detects for a visitor: nothing, offer it,
 * or take it.
 */
enum VisitorCountryMode: string
{
    case Off = 'off';
    case Suggest = 'suggest';
    case Apply = 'apply';

    public const CONFIG_KEY = 'visitor_country_mode';

    public static function fromShopConfiguration(): self
    {
        return self::tryFrom((string) ConfigQuery::read(self::CONFIG_KEY, self::Off->value))
            ?? self::Off;
    }
}
