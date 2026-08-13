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
use Thelia\Model\StateQuery;
use Thelia\Test\IntegrationTestCase;

final class StateSeedTest extends IntegrationTestCase
{
    /**
     * The 32 federal entities of Mexico with the code ISO 3166-2:MX assigns them.
     *
     * Aguascalientes was seeded as 'AGS' and Ciudad de Mexico as 'DIF', two codes
     * the standard does not define, so State::getIsoCode3166_2() returned 'MX-AGS'
     * and 'MX-DIF'. See issue #3168.
     */
    private const MEXICO_ISO_3166_2_CODES = [
        'AGU', 'BCN', 'BCS', 'CAM', 'CHH', 'CHP', 'CMX', 'COA', 'COL', 'DUR', 'GRO',
        'GUA', 'HID', 'JAL', 'MEX', 'MIC', 'MOR', 'NAY', 'NLE', 'OAX', 'PUE', 'QUE',
        'ROO', 'SIN', 'SLP', 'SON', 'TAB', 'TAM', 'TLA', 'VER', 'YUC', 'ZAC',
    ];

    /**
     * The four Sardinian provinces merged into Sud Sardegna and Sassari in 2016.
     *
     * ISO 3166-2:IT withdrew their codes, so they are seeded hidden instead of
     * deleted: an address already pointing at one of them stays readable.
     */
    private const WITHDRAWN_ITALIAN_CODES = ['CI', 'OG', 'OT', 'VS'];

    public function testTheMexicanStatesUseTheirIso31662Code(): void
    {
        $seeded = [];
        foreach (self::statesOf('MEX') as $state) {
            $seeded[] = $state->getIsocode();
        }

        sort($seeded);

        self::assertSame(self::MEXICO_ISO_3166_2_CODES, $seeded);
    }

    public function testTheDissolvedSardinianProvincesAreHiddenAndReplaced(): void
    {
        $visible = [];
        $hidden = [];
        foreach (self::statesOf('ITA') as $state) {
            if (1 === $state->getVisible()) {
                $visible[] = $state->getIsocode();
            } else {
                $hidden[] = $state->getIsocode();
            }
        }

        sort($hidden);

        self::assertSame(self::WITHDRAWN_ITALIAN_CODES, $hidden);
        self::assertContains('SU', $visible);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function renamedStateProvider(): iterable
    {
        // country alpha-3 code, English title, expected ISO 3166-2 code
        yield 'Aguascalientes' => ['MEX', 'Aguascalientes', 'MX-AGU'];
        yield 'Ciudad de Mexico' => ['MEX', 'Ciudad de México', 'MX-CMX'];
    }

    #[DataProvider('renamedStateProvider')]
    public function testGetIsoCode31662ReturnsACodeDefinedByTheStandard(string $alpha3, string $title, string $expected): void
    {
        $match = null;
        foreach (self::statesOf($alpha3) as $state) {
            if ($title === $state->setLocale('en_US')->getTitle()) {
                $match = $state;
                break;
            }
        }

        self::assertNotNull($match, \sprintf('state "%s" is missing from the %s seed', $title, $alpha3));
        self::assertSame($expected, $match->getIsoCode3166_2());
    }

    /**
     * @return \Thelia\Model\State[]
     */
    private static function statesOf(string $alpha3): array
    {
        $country = CountryQuery::create()->filterByIsoalpha3($alpha3)->findOne();

        self::assertNotNull($country, \sprintf('country %s is missing from the seed', $alpha3));

        return iterator_to_array(StateQuery::create()->filterByCountryId($country->getId())->find());
    }
}
