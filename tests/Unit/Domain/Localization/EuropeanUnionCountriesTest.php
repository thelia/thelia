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

namespace Thelia\Tests\Unit\Domain\Localization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Localization\Service\EuropeanUnionCountries;

final class EuropeanUnionCountriesTest extends TestCase
{
    public function testTheUnionHasTwentySevenMemberStates(): void
    {
        self::assertCount(27, EuropeanUnionCountries::MEMBERS);
        self::assertSame(EuropeanUnionCountries::MEMBERS, array_unique(EuropeanUnionCountries::MEMBERS));
    }

    #[DataProvider('memberships')]
    public function testMembership(string $isoAlpha2, bool $member): void
    {
        self::assertSame($member, (new EuropeanUnionCountries())->isMember($isoAlpha2));
    }

    /**
     * Greece files its VAT under EL, so both its ISO code and that prefix are accepted.
     */
    public function testGreeceCarriesTwoVatPrefixes(): void
    {
        self::assertSame(['GR', 'EL'], (new EuropeanUnionCountries())->vatPrefixesFor('GR'));
    }

    public function testAMemberStateCarriesItsOwnCodeAsVatPrefix(): void
    {
        self::assertSame(['BE'], (new EuropeanUnionCountries())->vatPrefixesFor('BE'));
        self::assertSame(['FR'], (new EuropeanUnionCountries())->vatPrefixesFor('FR'));
    }

    public function testANonMemberCarriesNoVatPrefix(): void
    {
        self::assertSame([], (new EuropeanUnionCountries())->vatPrefixesFor('CH'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function memberships(): iterable
    {
        yield 'France' => ['FR', true];
        yield 'Belgium' => ['BE', true];
        yield 'Greece' => ['GR', true];
        yield 'Croatia, the most recent member' => ['HR', true];
        yield 'Switzerland' => ['CH', false];
        yield 'United Kingdom, out since Brexit' => ['GB', false];
        yield 'United States' => ['US', false];
        yield 'lower case is accepted' => ['fr', true];
        yield 'unknown code' => ['ZZ', false];
    }
}
