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

namespace Thelia\Tests\Unit\Domain\Legal;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Legal\CompanyIdentifier;

final class CompanyIdentifierTest extends TestCase
{
    #[DataProvider('validFrenchSirets')]
    public function testValidFrenchSirets(string $siret): void
    {
        self::assertTrue(CompanyIdentifier::isValidFrenchSiret($siret));
    }

    #[DataProvider('invalidFrenchSirets')]
    public function testInvalidFrenchSirets(string $siret): void
    {
        self::assertFalse(CompanyIdentifier::isValidFrenchSiret($siret));
    }

    /**
     * The length and the checksum are reported as two distinct errors, so they are
     * two distinct checks and a wrong length must not be read as a wrong checksum.
     */
    public function testLengthIsCheckedApartFromTheChecksum(): void
    {
        self::assertFalse(CompanyIdentifier::hasFourteenDigits('1234567890001'));
        self::assertTrue(CompanyIdentifier::hasFourteenDigits('12345678900011'));
        self::assertFalse(CompanyIdentifier::hasValidLuhnChecksum('12345678900011'));
    }

    #[DataProvider('normalizedSirets')]
    public function testNormalizeSiret(?string $given, ?string $expected): void
    {
        self::assertSame($expected, CompanyIdentifier::normalizeSiret($given));
    }

    #[DataProvider('normalizedVatNumbers')]
    public function testNormalizeVatNumber(?string $given, ?string $expected): void
    {
        self::assertSame($expected, CompanyIdentifier::normalizeVatNumber($given));
    }

    #[DataProvider('frenchVatKeys')]
    public function testFrenchVatKey(string $vatNumber, bool $valid): void
    {
        self::assertSame($valid, CompanyIdentifier::hasValidFrenchVatKey($vatNumber));
    }

    public function testSirenIsExtractedFromBothNumbers(): void
    {
        self::assertSame('303265045', CompanyIdentifier::sirenOfSiret('30326504500003'));
        self::assertSame('303265045', CompanyIdentifier::sirenOfVatNumber('FR40303265045'));
        self::assertNull(CompanyIdentifier::sirenOfSiret('3032650450000'));
    }

    /**
     * Only a French number carries a SIREN: the national part of another member state
     * follows its own rules and must never be compared to a SIRET.
     */
    public function testOnlyAFrenchVatNumberCarriesASiren(): void
    {
        self::assertNull(CompanyIdentifier::sirenOfVatNumber('BE0123456789'));
        self::assertNull(CompanyIdentifier::sirenOfVatNumber('DE123456789'));
    }

    #[DataProvider('companyDependentValues')]
    public function testIdentifiersAreDroppedWithoutACompanyName(
        ?string $company,
        ?string $identifier,
        ?string $expected,
    ): void {
        self::assertSame($expected, CompanyIdentifier::forCompany($company, $identifier));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validFrenchSirets(): iterable
    {
        yield 'checksum valid' => ['73282932000074'];
        yield 'another checksum valid' => ['30326504500003'];
        yield 'La Poste, numbered outside the Luhn scheme' => ['35600000000048'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidFrenchSirets(): iterable
    {
        yield 'checksum invalid' => ['12345678900011'];
        yield 'checksum invalid on a real SIREN' => ['30326504500049'];
        yield 'thirteen digits' => ['1234567890001'];
        yield 'fifteen digits' => ['123456789000110'];
        yield 'carries a letter' => ['1234567890001A'];
        yield 'empty' => [''];
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function normalizedSirets(): iterable
    {
        yield 'spaces stripped' => ['732 829 320 00074', '73282932000074'];
        yield 'dots and hyphens stripped' => ['732.829.320-00074', '73282932000074'];
        yield 'already canonical' => ['73282932000074', '73282932000074'];
        yield 'empty becomes null' => ['', null];
        yield 'blank becomes null' => ['   ', null];
        yield 'null stays null' => [null, null];
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function normalizedVatNumbers(): iterable
    {
        yield 'upper cased and spaces stripped' => ['fr 40 303 265 045', 'FR40303265045'];
        yield 'already canonical' => ['BE0123456789', 'BE0123456789'];
        yield 'dots are kept, they belong to some national formats' => ['CHE-123.456.789', 'CHE-123.456.789'];
        yield 'empty becomes null' => ['', null];
        yield 'null stays null' => [null, null];
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function frenchVatKeys(): iterable
    {
        yield 'key matches the SIREN' => ['FR40303265045', true];
        yield 'key does not match the SIREN' => ['FR00123456789', false];
        yield 'key made of letters cannot be computed, so it is accepted' => ['FRAB123456789', true];
        yield 'not a French number, nothing to check' => ['BE0123456789', true];
    }

    /**
     * @return iterable<string, array{?string, ?string, ?string}>
     */
    public static function companyDependentValues(): iterable
    {
        yield 'company given' => ['Acme', '73282932000074', '73282932000074'];
        yield 'no company at all' => [null, '73282932000074', null];
        yield 'blank company' => ['   ', '73282932000074', null];
        yield 'company given but no identifier' => ['Acme', '', null];
        yield 'neither' => [null, null, null];
    }
}
