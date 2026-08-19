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

/**
 * Normalization and format checks for the legal identifiers of a business address.
 *
 * Every method is pure and free of any database or network access: the rules are the
 * same for a form, an API payload and a fixture, and none of them may reach out to an
 * external service while a customer waits for a form to submit.
 */
final class CompanyIdentifier
{
    private const FRENCH_VAT_PREFIX = 'FR';

    /**
     * La Poste establishments are numbered outside the Luhn scheme, only their length holds.
     */
    private const LA_POSTE_SIREN = '356000000';

    /**
     * The identifiers belong to the company name: with no company name there is no company
     * to identify, so a value posted alongside an empty one is dropped rather than stored.
     * This is the single place that rule is applied, so that a form, an API write and a
     * module all behave the same.
     */
    public static function forCompany(?string $company, ?string $identifier): ?string
    {
        if (!self::hasCompany($company)) {
            return null;
        }

        return ($identifier ?? '') === '' ? null : $identifier;
    }

    public static function hasCompany(?string $company): bool
    {
        return trim($company ?? '') !== '';
    }

    /**
     * Spaces, dots and hyphens are stripped so that a number copied from an official
     * document is accepted as typed, and stored in a single canonical form.
     */
    public static function normalizeSiret(?string $siret): ?string
    {
        $normalized = (string) preg_replace('/[\s.-]/', '', $siret ?? '');

        return $normalized === '' ? null : $normalized;
    }

    public static function normalizeVatNumber(?string $vatNumber): ?string
    {
        $normalized = strtoupper((string) preg_replace('/\s/', '', $vatNumber ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    public static function hasFourteenDigits(string $siret): bool
    {
        return preg_match('/^[0-9]{14}$/', $siret) === 1;
    }

    /**
     * Checksum of a French SIRET, over the 14 digits. Assumes the length was checked first,
     * so that a wrong length and a wrong checksum can be reported as two distinct errors.
     */
    public static function hasValidLuhnChecksum(string $siret): bool
    {
        if (str_starts_with($siret, self::LA_POSTE_SIREN)) {
            return true;
        }

        $checksum = 0;
        foreach (str_split(strrev($siret)) as $rank => $character) {
            $digit = (int) $character;

            if ($rank % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $checksum += $digit;
        }

        return $checksum % 10 === 0;
    }

    public static function isValidFrenchSiret(string $siret): bool
    {
        return self::hasFourteenDigits($siret) && self::hasValidLuhnChecksum($siret);
    }

    public static function matchesVatPattern(string $vatNumber): bool
    {
        return preg_match('/^[A-Z]{2}[0-9A-Z]{2,13}$/', $vatNumber) === 1;
    }

    /**
     * A French VAT number is FR, a two character key, then the 9 digits of the SIREN.
     * Numbers issued before the key went numeric carry letters instead, and no key can
     * be computed for those - they are left alone rather than rejected.
     */
    public static function hasValidFrenchVatKey(string $vatNumber): bool
    {
        $siren = self::sirenOfVatNumber($vatNumber);
        $key = substr($vatNumber, 2, 2);

        if (null === $siren || preg_match('/^[0-9]{2}$/', $key) !== 1) {
            return true;
        }

        return (int) $key === (12 + 3 * ((int) $siren % 97)) % 97;
    }

    public static function sirenOfSiret(string $siret): ?string
    {
        return self::hasFourteenDigits($siret) ? substr($siret, 0, 9) : null;
    }

    /**
     * Only a French number carries a SIREN: the national part of the other member
     * states follows its own rules and must not be compared to a SIRET.
     */
    public static function sirenOfVatNumber(string $vatNumber): ?string
    {
        if (!str_starts_with($vatNumber, self::FRENCH_VAT_PREFIX)) {
            return null;
        }

        $nationalPart = substr($vatNumber, 2);

        return preg_match('/^[0-9A-Z]{2}([0-9]{9})$/', $nationalPart, $matches) === 1
            ? $matches[1]
            : null;
    }
}
