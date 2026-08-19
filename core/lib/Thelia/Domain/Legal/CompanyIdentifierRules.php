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
 * The single statement of what makes the legal identifiers of an address acceptable.
 *
 * Address forms and the API resource both answer to it, so that a payload cannot be written
 * through one door under rules the other door would have refused. Returning violations rather
 * than raising them keeps this free of any framework: the caller decides how to report them.
 */
final class CompanyIdentifierRules
{
    private const MAX_LENGTH = 20;

    private const FREE_FORM_PATTERN = '/^[0-9A-Za-z]{1,20}$/';

    /**
     * @param string|null $countryCode alpha 2 code of the country of the address, null when unknown
     *
     * @return list<CompanyIdentifierViolation> empty when the identifiers are acceptable,
     *                                          and always empty when no company name is given
     */
    public static function violationsFor(
        ?string $company,
        ?string $siret,
        ?string $vatNumber,
        ?string $countryCode,
    ): array {
        if (!CompanyIdentifier::hasCompany($company)) {
            return [];
        }

        $countryCode = null === $countryCode ? null : strtoupper($countryCode);

        return array_values(array_filter([
            self::siretViolation(CompanyIdentifier::normalizeSiret($siret), $countryCode),
            self::vatNumberViolation(
                CompanyIdentifier::normalizeVatNumber($vatNumber),
                CompanyIdentifier::normalizeSiret($siret),
                $countryCode,
            ),
        ]));
    }

    private static function siretViolation(?string $siret, ?string $countryCode): ?CompanyIdentifierViolation
    {
        // Neither identifier is mandatory: an association, a VAT-exempt business or a company
        // outside the Union legitimately has a name and no registration number to give. Only
        // its format is checked, and only once a value is actually typed.
        if (null === $siret) {
            return null;
        }

        // Outside France there is no shared registration format to check, so only the length
        // of the column is enforced and the label speaks of a registration number instead.
        if ('FR' !== $countryCode) {
            return preg_match(self::FREE_FORM_PATTERN, $siret) === 1 ? null : new CompanyIdentifierViolation(
                CompanyIdentifierViolation::FIELD_SIRET,
                'A company registration number may only contain up to 20 letters and digits.',
            );
        }

        if (!CompanyIdentifier::hasFourteenDigits($siret)) {
            return new CompanyIdentifierViolation(
                CompanyIdentifierViolation::FIELD_SIRET,
                'A SIRET number must contain exactly 14 digits.',
            );
        }

        if (!CompanyIdentifier::hasValidLuhnChecksum($siret)) {
            return new CompanyIdentifierViolation(
                CompanyIdentifierViolation::FIELD_SIRET,
                'The checksum of this SIRET number is invalid, please check the number you typed.',
            );
        }

        return null;
    }

    private static function vatNumberViolation(
        ?string $vatNumber,
        ?string $siret,
        ?string $countryCode,
    ): ?CompanyIdentifierViolation {
        // Same reasoning as the SIRET above: a company legitimately without a VAT number is
        // not a violation, only an invalid one typed in is.
        if (null === $vatNumber) {
            return null;
        }

        $europeanUnion = new EuropeanUnionCountries();

        // A number issued outside the Union stays free text: refusing a valid foreign tax
        // identifier would be worse than storing one Thelia cannot check.
        if (null === $countryCode || !$europeanUnion->isMember($countryCode)) {
            return mb_strlen($vatNumber) <= self::MAX_LENGTH ? null : new CompanyIdentifierViolation(
                CompanyIdentifierViolation::FIELD_VAT_NUMBER,
                'A VAT number may not exceed 20 characters.',
            );
        }

        if (!CompanyIdentifier::matchesVatPattern($vatNumber)) {
            return new CompanyIdentifierViolation(
                CompanyIdentifierViolation::FIELD_VAT_NUMBER,
                'A VAT number must start with a two letter country code followed by 2 to 13 alphanumeric characters.',
            );
        }

        $prefixes = $europeanUnion->vatPrefixesFor($countryCode);

        if (!\in_array(substr($vatNumber, 0, 2), $prefixes, true)) {
            return new CompanyIdentifierViolation(
                CompanyIdentifierViolation::FIELD_VAT_NUMBER,
                'This VAT number does not start with the country code of the address, which is %prefix.',
                ['%prefix' => implode(' / ', $prefixes)],
            );
        }

        if ('FR' !== $countryCode) {
            return null;
        }

        if (!CompanyIdentifier::hasValidFrenchVatKey($vatNumber)) {
            return new CompanyIdentifierViolation(
                CompanyIdentifierViolation::FIELD_VAT_NUMBER,
                'The checksum of this VAT number is invalid, please check the number you typed.',
            );
        }

        // Both numbers are built on the same SIREN: a mismatch means one of the two belongs to
        // another company, which no checksum on its own would catch. Only compared against a
        // SIRET that is itself valid, otherwise a mistyped SIRET would be reported twice.
        $sirenOfSiret = null !== $siret && CompanyIdentifier::isValidFrenchSiret($siret)
            ? CompanyIdentifier::sirenOfSiret($siret)
            : null;
        $sirenOfVatNumber = CompanyIdentifier::sirenOfVatNumber($vatNumber);

        if (null !== $sirenOfSiret && null !== $sirenOfVatNumber && $sirenOfSiret !== $sirenOfVatNumber) {
            return new CompanyIdentifierViolation(
                CompanyIdentifierViolation::FIELD_VAT_NUMBER,
                'The SIREN of this VAT number does not match the first 9 digits of the SIRET number.',
            );
        }

        return null;
    }
}
