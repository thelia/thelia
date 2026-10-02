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

use Propel\Runtime\Propel;
use Thelia\Model\ChoiceFilterOther;
use Thelia\Model\ChoiceFilterOtherI18nQuery;
use Thelia\Model\ChoiceFilterOtherQuery;
use Thelia\Model\Map\ChoiceFilterOtherI18nTableMap;
use Thelia\Model\Map\ChoiceFilterOtherTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The statements 3.2.0.sql runs to give a shop upgraded from 3.1 the promo and newness
 * rows of choice_filter_other, which the back office lists to configure those facets.
 *
 * The statements are read from the shipped script rather than repeated here, so the test
 * fails if the script stops doing what it claims.
 */
final class ChoiceFilterOtherFlagRowsMigrationTest extends IntegrationTestCase
{
    private const array FLAG_TYPES = ['promo', 'new'];

    public function testAShopWithoutTheRowsGetsBothWithTheirTitles(): void
    {
        $this->deleteFlagRows();

        $this->runMigration();

        foreach (self::FLAG_TYPES as $type) {
            self::assertSame(1, ChoiceFilterOtherQuery::create()->filterByType($type)->count(), $type);
        }

        $promo = ChoiceFilterOtherQuery::create()->findOneByType('promo');
        $newness = ChoiceFilterOtherQuery::create()->findOneByType('new');

        self::assertNotNull($promo);
        self::assertNotNull($newness);
        self::assertTrue((bool) $promo->getVisible());
        self::assertTrue((bool) $newness->getVisible());
        self::assertSame(8, ChoiceFilterOtherI18nQuery::create()->filterById($promo->getId())->count());
        self::assertSame(8, ChoiceFilterOtherI18nQuery::create()->filterById($newness->getId())->count());
        self::assertSame('Promotion', $this->titleOf((int) $promo->getId(), 'fr_FR'));
        self::assertSame('Nouveauté', $this->titleOf((int) $newness->getId(), 'fr_FR'));
    }

    public function testARowOfTheShopKeepsItsIdAndTheFlagRowsTakeTheNextOnes(): void
    {
        $this->deleteFlagRows();

        $own = new ChoiceFilterOther();
        $own->setType('shop-specific');
        $own->setVisible(true);
        $own->save($this->getPropelConnection());

        $this->runMigration();

        self::assertSame('shop-specific', ChoiceFilterOtherQuery::create()->findPk($own->getId())?->getType());
        self::assertGreaterThan($own->getId(), ChoiceFilterOtherQuery::create()->findOneByType('promo')?->getId());
        self::assertGreaterThan($own->getId(), ChoiceFilterOtherQuery::create()->findOneByType('new')?->getId());
    }

    public function testATitleTheMerchantTypedIsKept(): void
    {
        $promoId = (int) ChoiceFilterOtherQuery::create()->findOneByType('promo')?->getId();
        Propel::getWriteConnection(ChoiceFilterOtherTableMap::DATABASE_NAME)->exec(
            \sprintf("UPDATE `choice_filter_other_i18n` SET `title` = 'Soldes' WHERE `id` = %d AND `locale` = 'fr_FR'", $promoId),
        );

        $this->runMigration();

        self::assertSame('Soldes', $this->titleOf($promoId, 'fr_FR'));
    }

    public function testTheStatementsCanBeReplayed(): void
    {
        $this->deleteFlagRows();

        $this->runMigration();
        $this->runMigration();

        foreach (self::FLAG_TYPES as $type) {
            self::assertSame(1, ChoiceFilterOtherQuery::create()->filterByType($type)->count(), $type);
        }
    }

    private function deleteFlagRows(): void
    {
        Propel::getWriteConnection(ChoiceFilterOtherTableMap::DATABASE_NAME)->exec(
            "DELETE FROM `choice_filter_other` WHERE `type` IN ('promo', 'new')",
        );

        $this->clearInstancePools();

        self::assertSame(0, ChoiceFilterOtherQuery::create()->filterByType(self::FLAG_TYPES)->count());
    }

    private function runMigration(): void
    {
        $connection = Propel::getWriteConnection(ChoiceFilterOtherTableMap::DATABASE_NAME);

        foreach ($this->migrationStatements() as $statement) {
            $connection->exec($statement);
        }

        $this->clearInstancePools();
    }

    /**
     * @return list<string>
     */
    private function migrationStatements(): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.2.0.sql');

        $statements = [];

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (str_contains($sql, '`choice_filter_other')) {
                $statements[] = $sql;
            }
        }

        self::assertCount(5, $statements, 'The 3.2.0 script does not seed the promo and newness rows of choice_filter_other.');

        return $statements;
    }

    private function titleOf(int $id, string $locale): ?string
    {
        return ChoiceFilterOtherI18nQuery::create()->filterById($id)->filterByLocale($locale)->findOne()?->getTitle();
    }

    private function clearInstancePools(): void
    {
        ChoiceFilterOtherTableMap::clearInstancePool();
        ChoiceFilterOtherI18nTableMap::clearInstancePool();
    }
}
