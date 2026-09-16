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

namespace Thelia\Tests\Integration\Domain\Localization;

use Thelia\Domain\Localization\Service\CountryService;
use Thelia\Test\IntegrationTestCase;

/**
 * The address form keys its country list on the translated title.
 *
 * A country with no title in the requested locale used to key the list on
 * null, so every untranslated country collapsed under the same empty key and
 * the form offered one blank entry instead of them all.
 */
final class CountryChoicesTest extends IntegrationTestCase
{
    public function testUntranslatedCountriesStaySeparateChoicesLabelledByTheirIsoCode(): void
    {
        $factory = $this->createFixtureFactory();

        $first = $factory->country([
            'isocode' => '902',
            'isoalpha2' => 'YA',
            'isoalpha3' => 'YAA',
        ]);
        $second = $factory->country([
            'isocode' => '903',
            'isoalpha2' => 'YB',
            'isoalpha3' => 'YBB',
        ]);

        $choices = $this->getService(CountryService::class)->getAllCountriesChoiceType();

        self::assertArrayNotHasKey('', $choices, 'no country may be offered under an empty label');
        self::assertSame($first->getId(), $choices['YA'] ?? null, 'an untranslated country falls back to its ISO code');
        self::assertSame($second->getId(), $choices['YB'] ?? null, 'a second untranslated country must not replace the first');
    }
}
