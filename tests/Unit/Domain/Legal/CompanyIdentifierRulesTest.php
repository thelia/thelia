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
use Thelia\Domain\Legal\CompanyIdentifierRules;
use Thelia\Domain\Legal\CompanyIdentifierViolation;

final class CompanyIdentifierRulesTest extends TestCase
{
    /**
     * Without a company name there is nothing to identify, so a private buyer is never
     * blocked - even when the browser posted values anyway.
     */
    #[DataProvider('addressesWithoutACompanyName')]
    public function testNothingIsRequiredWithoutACompanyName(?string $company): void
    {
        self::assertSame([], CompanyIdentifierRules::violationsFor($company, null, null, 'FR'));
        self::assertSame([], CompanyIdentifierRules::violationsFor($company, '99', 'nonsense', 'FR'));
    }

    /**
     * Neither identifier is mandatory: an association, a VAT-exempt business or a company
     * outside the Union legitimately has a name and no registration number to give, and an
     * address on file before these columns existed must stay editable without retroactively
     * demanding either of them.
     */
    public function testNeitherIdentifierIsRequiredEvenWithACompanyName(): void
    {
        self::assertSame([], CompanyIdentifierRules::violationsFor('Acme', null, null, 'FR'));
    }

    public function testACompanyRegistrationNumberAloneIsAcceptedWithoutAVatNumber(): void
    {
        self::assertSame(
            [],
            CompanyIdentifierRules::violationsFor('Acme', '30326504500003', null, 'FR'),
        );
    }

    public function testAVatNumberAloneIsAcceptedWithoutACompanyRegistrationNumber(): void
    {
        self::assertSame(
            [],
            CompanyIdentifierRules::violationsFor('Acme', null, 'FR40303265045', 'FR'),
        );
    }

    public function testAFrenchAddressAcceptsAValidPair(): void
    {
        self::assertSame(
            [],
            CompanyIdentifierRules::violationsFor('Acme', '30326504500003', 'FR40303265045', 'FR'),
        );
    }

    #[DataProvider('rejectedFrenchPairs')]
    public function testAFrenchAddressRejects(string $siret, string $vatNumber, string $field): void
    {
        $violations = CompanyIdentifierRules::violationsFor('Acme', $siret, $vatNumber, 'FR');

        self::assertCount(1, $violations);
        self::assertSame($field, $violations[0]->field);
    }

    /**
     * A mistyped SIRET would otherwise also fail the SIREN cross-check and be reported twice,
     * leaving the customer to guess which of the two fields to fix.
     */
    public function testAnInvalidSiretIsReportedOnceAndNotAlsoAsASirenMismatch(): void
    {
        $violations = CompanyIdentifierRules::violationsFor('Acme', '12345678900011', 'FR40303265045', 'FR');

        self::assertCount(1, $violations);
        self::assertSame(CompanyIdentifierViolation::FIELD_SIRET, $violations[0]->field);
    }

    /**
     * A member state other than France gets no checksum, but its VAT prefix must be its own.
     */
    public function testABelgianAddressAcceptsAFreeFormRegistrationNumber(): void
    {
        self::assertSame(
            [],
            CompanyIdentifierRules::violationsFor('Acme SA', '0123456789', 'BE0123456789', 'BE'),
        );
    }

    public function testABelgianAddressRejectsAFrenchVatPrefix(): void
    {
        $violations = CompanyIdentifierRules::violationsFor('Acme SA', '0123456789', 'FR40303265045', 'BE');

        self::assertCount(1, $violations);
        self::assertSame(CompanyIdentifierViolation::FIELD_VAT_NUMBER, $violations[0]->field);
        self::assertSame(['%prefix' => 'BE'], $violations[0]->parameters);
    }

    public function testGreeceAcceptsBothItsPrefixes(): void
    {
        self::assertSame([], CompanyIdentifierRules::violationsFor('Acme AE', '0123456789', 'EL123456789', 'GR'));
        self::assertSame([], CompanyIdentifierRules::violationsFor('Acme AE', '0123456789', 'GR123456789', 'GR'));
    }

    /**
     * Outside the Union a tax identifier follows rules Thelia does not know, so it is stored
     * as typed rather than refused.
     */
    public function testASwissAddressAcceptsALocalVatFormat(): void
    {
        self::assertSame(
            [],
            CompanyIdentifierRules::violationsFor('Acme AG', 'CH12345678', 'CHE-123.456.789', 'CH'),
        );
    }

    public function testAnIdentifierLongerThanTheColumnIsRefused(): void
    {
        $violations = CompanyIdentifierRules::violationsFor('Acme AG', 'CH12345678', str_repeat('X', 21), 'CH');

        self::assertCount(1, $violations);
        self::assertSame(CompanyIdentifierViolation::FIELD_VAT_NUMBER, $violations[0]->field);
    }

    public function testAnUnknownCountryFallsBackToFreeText(): void
    {
        self::assertSame(
            [],
            CompanyIdentifierRules::violationsFor('Acme', 'ABC123', 'SOMETHING99', null),
        );
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function addressesWithoutACompanyName(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'blank' => ['   '];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function rejectedFrenchPairs(): iterable
    {
        yield 'SIRET checksum invalid' => [
            '12345678900011',
            'FR40303265045',
            CompanyIdentifierViolation::FIELD_SIRET,
        ];
        yield 'SIRET too short' => [
            '1234567890001',
            'FR40303265045',
            CompanyIdentifierViolation::FIELD_SIRET,
        ];
        yield 'VAT key does not match its own SIREN' => [
            '30326504500003',
            'FR00303265045',
            CompanyIdentifierViolation::FIELD_VAT_NUMBER,
        ];
        yield 'VAT SIREN belongs to another company than the SIRET' => [
            '30326504500003',
            'FR44732829320',
            CompanyIdentifierViolation::FIELD_VAT_NUMBER,
        ];
    }
}
