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

namespace Thelia\Domain\Legal;

use Thelia\Domain\Localization\Service\EuropeanUnionCountries;

/**
 * What to call the legal identifiers of an address, which depends on where it is.
 *
 * A French buyer reads "SIRET", anyone else reads "registration number"; inside the Union a
 * VAT number is intra-community, outside it is just a VAT number. Returns the source strings,
 * so every caller translates them through its own catalogue.
 */
final class CompanyIdentifierLabels
{
    public const SIRET_FRANCE = 'SIRET number';
    public const SIRET_ELSEWHERE = 'Company registration number';
    public const VAT_EUROPEAN_UNION = 'Intra-community VAT number';
    public const VAT_ELSEWHERE = 'VAT number';

    public static function siret(?string $isoAlpha2): string
    {
        return 'FR' === strtoupper($isoAlpha2 ?? '') ? self::SIRET_FRANCE : self::SIRET_ELSEWHERE;
    }

    public static function vatNumber(?string $isoAlpha2): string
    {
        if (null === $isoAlpha2) {
            return self::VAT_ELSEWHERE;
        }

        return (new EuropeanUnionCountries())->isMember($isoAlpha2)
            ? self::VAT_EUROPEAN_UNION
            : self::VAT_ELSEWHERE;
    }
}
