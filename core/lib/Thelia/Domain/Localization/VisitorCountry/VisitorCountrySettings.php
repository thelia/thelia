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
 * The shop variables of the visitor country detection that are not an enumeration.
 * The mode and the source have their own enum, which holds their key.
 */
final readonly class VisitorCountrySettings
{
    public const HEADER_CONFIG_KEY = 'visitor_country_header';
    public const DATABASE_CONFIG_KEY = 'visitor_country_database';

    public const DEFAULT_HEADER = 'CF-IPCountry';

    public function mode(): VisitorCountryMode
    {
        return VisitorCountryMode::fromShopConfiguration();
    }

    public function source(): VisitorCountrySource
    {
        return VisitorCountrySource::fromShopConfiguration();
    }

    public function header(): string
    {
        $header = trim((string) ConfigQuery::read(self::HEADER_CONFIG_KEY, self::DEFAULT_HEADER));

        return '' === $header ? self::DEFAULT_HEADER : $header;
    }

    /**
     * As the shop wrote it: only the lookup that opens the file decides whether it is a
     * file name it accepts.
     */
    public function databaseFileName(): string
    {
        return trim((string) ConfigQuery::read(self::DATABASE_CONFIG_KEY, ''));
    }
}
