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

namespace Thelia\Tests\Integration\Install;

use Thelia\Model\CountryI18nQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * A country with no title in a language of the shop shows up as an empty option in the
 * address forms of that language. The seed has to name every visible country in every
 * active language, and a country the seed leaves in French in the English list is a
 * title nobody translated.
 */
final class SeededCountryTitlesTest extends IntegrationTestCase
{
    public function testNoVisibleCountryIsWithoutATitleInAnActiveLanguage(): void
    {
        $connection = $this->getPropelConnection();
        $locales = LangQuery::create()->filterByActive(1)->select('Locale')->find($connection)->toArray();

        self::assertNotSame([], $locales);

        $missing = [];

        foreach (CountryQuery::create()->filterByVisible(1)->find($connection) as $country) {
            foreach ($locales as $locale) {
                $title = CountryI18nQuery::create()
                    ->filterById($country->getId())
                    ->filterByLocale($locale)
                    ->findOne($connection)
                    ?->getTitle();

                if (null === $title || '' === trim($title)) {
                    $missing[] = $country->getIsoalpha2().' '.$locale;
                }
            }
        }

        self::assertSame([], $missing, 'Countries with no title: '.implode(', ', $missing));
    }

    public function testTheOverseasTerritoriesAreNamedInEnglishAndSpanish(): void
    {
        $connection = $this->getPropelConnection();

        $expected = [
            'GF' => ['en_US' => 'French Guiana'],
            'RE' => ['en_US' => 'Réunion', 'es_ES' => 'Reunión'],
            'NC' => ['en_US' => 'New Caledonia', 'es_ES' => 'Nueva Caledonia'],
            'PF' => ['en_US' => 'French Polynesia'],
        ];

        foreach ($expected as $isoCode => $titles) {
            $country = CountryQuery::create()->findOneByIsoalpha2($isoCode, $connection);
            self::assertNotNull($country, $isoCode);

            foreach ($titles as $locale => $title) {
                self::assertSame($title, $country->setLocale($locale)->getTitle(), $isoCode.' '.$locale);
            }
        }
    }
}
