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
 * The statements 3.3.0.sql runs to give an upgraded shop the availability, rating and price
 * rows of choice_filter_other, which the back office lists to configure those facets.
 *
 * The statements are read from the shipped script rather than repeated here, so the test
 * fails if the script stops doing what it claims.
 */
final class ChoiceFilterOtherListingFacetRowsMigrationTest extends IntegrationTestCase
{
    private const array TYPES = ['availability', 'rating', 'price'];

    private const array FRENCH_TITLES = ['availability' => 'Disponibilité', 'rating' => 'Note client', 'price' => 'Prix'];

    public function testAShopWithoutTheRowsGetsThemWithTheirTitles(): void
    {
        $this->deleteRows();

        $this->runMigration();

        foreach (self::TYPES as $type) {
            $row = ChoiceFilterOtherQuery::create()->findOneByType($type);

            self::assertNotNull($row, $type);
            self::assertSame(1, ChoiceFilterOtherQuery::create()->filterByType($type)->count(), $type);
            self::assertTrue((bool) $row->getVisible(), $type);
            self::assertSame(8, ChoiceFilterOtherI18nQuery::create()->filterById($row->getId())->count(), $type);
            self::assertSame(self::FRENCH_TITLES[$type], $this->titleOf((int) $row->getId(), 'fr_FR'));
        }
    }

    public function testARowOfTheShopKeepsItsIdAndTheNewRowsTakeTheNextOnes(): void
    {
        $this->deleteRows();

        $own = new ChoiceFilterOther();
        $own->setType('shop-specific');
        $own->setVisible(true);
        $own->save($this->getPropelConnection());

        $this->runMigration();

        self::assertSame('shop-specific', ChoiceFilterOtherQuery::create()->findPk($own->getId())?->getType());

        foreach (self::TYPES as $type) {
            self::assertGreaterThan($own->getId(), ChoiceFilterOtherQuery::create()->findOneByType($type)?->getId(), $type);
        }
    }

    public function testATitleTheMerchantTypedIsKept(): void
    {
        $priceId = (int) ChoiceFilterOtherQuery::create()->findOneByType('price')?->getId();
        Propel::getWriteConnection(ChoiceFilterOtherTableMap::DATABASE_NAME)->exec(
            \sprintf("UPDATE `choice_filter_other_i18n` SET `title` = 'Budget' WHERE `id` = %d AND `locale` = 'fr_FR'", $priceId),
        );

        $this->runMigration();

        self::assertSame('Budget', $this->titleOf($priceId, 'fr_FR'));
    }

    public function testTheStatementsCanBeReplayed(): void
    {
        $this->deleteRows();

        $this->runMigration();
        $this->runMigration();

        foreach (self::TYPES as $type) {
            self::assertSame(1, ChoiceFilterOtherQuery::create()->filterByType($type)->count(), $type);
        }
    }

    private function deleteRows(): void
    {
        Propel::getWriteConnection(ChoiceFilterOtherTableMap::DATABASE_NAME)->exec(
            "DELETE FROM `choice_filter_other` WHERE `type` IN ('availability', 'rating', 'price')",
        );

        $this->clearInstancePools();

        self::assertSame(0, ChoiceFilterOtherQuery::create()->filterByType(self::TYPES)->count());
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
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.3.0.sql');

        $statements = [];

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (str_contains($sql, '`choice_filter_other')) {
                $statements[] = $sql;
            }
        }

        self::assertCount(9, $statements, 'The 3.3.0 script does not seed the availability, rating and price rows of choice_filter_other.');

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
