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

namespace Thelia\Tests\Integration\Domain\Taxation;

use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Country;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A product listing asks {@see TaxEngine::getDeliveryCountry()} for every
 * card on the page. With no cart address and no customer to fall back to
 * (an anonymous visitor, the common case), that used to re-read the
 * `country` row holding `by_default=1` on every single call.
 */
final class TaxEngineDefaultCountryQueryCountTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    public function testRepeatedFallbackToTheDefaultCountryCostsAtMostOneQuery(): void
    {
        Country::resetDefaultCountryCache();

        $taxEngine = $this->getService(TaxEngine::class);

        $statements = $this->recordSqlQueries(static function () use ($taxEngine): void {
            $first = $taxEngine->getDeliveryCountry();
            $second = $taxEngine->getDeliveryCountry();
            $third = $taxEngine->getDeliveryCountry();

            self::assertSame($first->getId(), $second->getId());
            self::assertSame($first->getId(), $third->getId());
        });

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'country'),
            'Three calls with no cart address and no customer must still cost a single country lookup.',
        );
    }

    /**
     * The memo must not survive a change of which country is the default:
     * an admin flipping it must be visible to the rest of the very same
     * request, not just to the next one.
     */
    public function testChangingTheDefaultCountryInvalidatesTheMemoImmediately(): void
    {
        $originalDefault = Country::getDefaultCountry();

        $newDefault = $this->createFixtureFactory()->country([
            'isocode' => 'ZZ',
            'isoalpha2' => 'ZZ',
            'isoalpha3' => 'ZZZ',
        ]);
        $newDefault->toggleDefault();

        self::assertNotSame($originalDefault->getId(), $newDefault->getId());
        self::assertSame(
            $newDefault->getId(),
            Country::getDefaultCountry()->getId(),
            'A write to by_default must invalidate the memoized default country within the same request.',
        );
    }
}
