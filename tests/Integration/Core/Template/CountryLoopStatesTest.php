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

namespace Thelia\Tests\Integration\Core\Template;

use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Model\CountryQuery;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * France carries its 101 departments while leaving the choice optional, so a
 * template asking HAS_STATES to decide whether to show the state field would
 * hide a list the shop does have. HAS_SELECTABLE_STATES answers that question.
 */
final class CountryLoopStatesTest extends IntegrationTestCase
{
    public function testACountryCarriesStatesWithoutRequiringOne(): void
    {
        $france = CountryQuery::create()->findOneByIsoalpha3('FRA');
        self::assertNotNull($france, 'the seeded countries are missing from the test database');
        self::assertFalse((bool) $france->getHasStates(), 'France leaves the department optional');

        $row = $this->countryRow($france->getId());

        self::assertSame('0', $row['HAS_STATES']);
        self::assertSame('1', $row['HAS_SELECTABLE_STATES']);
    }

    public function testACountryWithoutStatesOffersNone(): void
    {
        $country = $this->createFixtureFactory()->country([
            'isocode' => '902',
            'isoalpha2' => 'ZY',
            'isoalpha3' => 'ZYY',
            'shopCountry' => false,
        ]);

        // The loop joins the i18n title of the language it runs for.
        foreach (LangQuery::create()->find() as $lang) {
            $country->setLocale($lang->getLocale())->setTitle('Stateless country')->save();
        }

        $row = $this->countryRow($country->getId());

        self::assertSame('0', $row['HAS_SELECTABLE_STATES']);
    }

    /**
     * @return array<string, mixed>
     */
    private function countryRow(int $countryId): array
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne() ?? LangQuery::create()->findOne();

        $result = $this->getService(LoopExecutor::class)->execute('country', [
            'id' => $countryId,
            'lang' => $lang->getId(),
        ]);

        $rows = [];
        foreach ($result as $row) {
            $rows[] = $row->getVarVal();
        }

        self::assertCount(1, $rows);

        return $rows[0];
    }
}
