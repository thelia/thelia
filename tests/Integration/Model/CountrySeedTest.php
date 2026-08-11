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

namespace Thelia\Tests\Integration\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Model\CountryQuery;
use Thelia\Test\IntegrationTestCase;

final class CountrySeedTest extends IntegrationTestCase
{
    /**
     * The ISO 3166-1 entries the seed used to be missing, see issue #3528.
     */
    private const ADDED_ALPHA_2_CODES = [
        'AI', 'AQ', 'AS', 'AW', 'AX', 'BM', 'BQ', 'BV', 'CC', 'CW', 'CX', 'EH', 'FK', 'FO', 'GG',
        'GI', 'GL', 'GS', 'GU', 'HM', 'IM', 'IO', 'JE', 'KY', 'ME', 'MO', 'MP', 'MS', 'NF', 'PN',
        'PR', 'PS', 'RS', 'SH', 'SJ', 'SS', 'SX', 'TC', 'TK', 'TL', 'UM', 'VG', 'VI',
    ];

    public function testTheSeedShipsTheCountriesMissingFromTheOriginalList(): void
    {
        $seeded = [];
        foreach (CountryQuery::create()->find() as $country) {
            $seeded[] = $country->getIsoalpha2();
        }

        self::assertSame(
            [],
            array_values(array_diff(self::ADDED_ALPHA_2_CODES, $seeded)),
            'the seed does not cover every officially assigned ISO 3166-1 country',
        );
    }

    public function testEveryAddedCountryHasAnEnglishAndFrenchTitle(): void
    {
        $untranslated = [];
        foreach (self::ADDED_ALPHA_2_CODES as $alpha2) {
            $country = CountryQuery::create()->filterByIsoalpha2($alpha2)->findOne();
            self::assertNotNull($country, \sprintf('country %s is missing from the seed', $alpha2));

            foreach (['en_US', 'fr_FR'] as $locale) {
                if (null === $country->setLocale($locale)->getTitle()) {
                    $untranslated[] = $alpha2.' '.$locale;
                }
            }
        }

        self::assertSame([], $untranslated);
    }

    /**
     * Belize used to be seeded with 'BL', the code of Saint-Barthelemy, see issue #3559.
     */
    public function testNoTwoCountriesShareTheSameAlpha2Code(): void
    {
        $countriesByAlpha2 = [];
        foreach (CountryQuery::create()->find() as $country) {
            $countriesByAlpha2[$country->getIsoalpha2()][] = $country->getIsoalpha3();
        }

        $duplicated = [];
        foreach ($countriesByAlpha2 as $alpha2 => $alpha3Codes) {
            if (\count($alpha3Codes) > 1) {
                $duplicated[] = $alpha2.' ('.implode(', ', $alpha3Codes).')';
            }
        }

        self::assertSame([], $duplicated, 'two countries claim the same ISO 3166-1 alpha-2 code');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function correctedCountryProvider(): iterable
    {
        // alpha-3 code, expected alpha-2 code, expected numeric code, see issue #3559
        yield 'Belize' => ['BLZ', 'BZ', '84'];
        yield 'Saint-Barthelemy' => ['BLM', 'BL', '652'];
        yield 'Libya' => ['LBY', 'LY', '434'];
    }

    #[DataProvider('correctedCountryProvider')]
    public function testTheSeedUsesTheOfficialCodes(string $alpha3, string $alpha2, string $numeric): void
    {
        $country = CountryQuery::create()->filterByIsoalpha3($alpha3)->findOne();

        self::assertNotNull($country, \sprintf('country %s is missing from the seed', $alpha3));
        self::assertSame($alpha2, $country->getIsoalpha2());
        self::assertSame($numeric, $country->getIsocode());
    }
}
