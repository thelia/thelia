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

interface IpCountryLookupInterface
{
    /**
     * @return string|null the ISO 3166-1 alpha-2 code of the address, null when the database
     *                     cannot be read or knows nothing about it
     */
    public function lookup(string $ipAddress, string $databaseFileName): ?string;
}
