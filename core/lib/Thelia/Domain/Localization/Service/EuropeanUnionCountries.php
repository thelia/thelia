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

namespace Thelia\Domain\Localization\Service;

/**
 * Membership of the European Union, which drives how a VAT number is validated.
 *
 * Kept as a list rather than a column on `country`: membership is a political fact
 * a shop has no business editing, and a new member state ships with a Thelia update
 * the same way a new currency does.
 */
final class EuropeanUnionCountries
{
    /**
     * @var list<string>
     */
    public const MEMBERS = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU',
        'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    /**
     * The VAT prefix of a member state is its alpha 2 code, except for Greece which
     * files under EL. Both are accepted, because either one appears on real invoices.
     *
     * @var array<string, list<string>>
     */
    private const VAT_PREFIX_EXCEPTIONS = [
        'GR' => ['GR', 'EL'],
    ];

    public function isMember(string $isoAlpha2): bool
    {
        return \in_array(strtoupper($isoAlpha2), self::MEMBERS, true);
    }

    /**
     * @return list<string> the VAT prefixes a number from this country may carry,
     *                      empty when the country is not a member state
     */
    public function vatPrefixesFor(string $isoAlpha2): array
    {
        $isoAlpha2 = strtoupper($isoAlpha2);

        if (!$this->isMember($isoAlpha2)) {
            return [];
        }

        return self::VAT_PREFIX_EXCEPTIONS[$isoAlpha2] ?? [$isoAlpha2];
    }
}
