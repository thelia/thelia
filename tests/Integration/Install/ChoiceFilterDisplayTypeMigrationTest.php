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
use Thelia\Model\ChoiceFilter;
use Thelia\Model\ChoiceFilterQuery;
use Thelia\Model\Map\ChoiceFilterTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The statement 3.2.0.sql runs to give a display type to the choice_filter rows a shop
 * upgraded from Thelia 2 carries without one.
 *
 * The statement is read from the shipped script rather than repeated here, so the test
 * fails if the script stops doing what it claims.
 */
final class ChoiceFilterDisplayTypeMigrationTest extends IntegrationTestCase
{
    public function testARowWithoutDisplayTypeGetsTheCheckboxList(): void
    {
        $rowId = $this->createRow(null);

        $this->runMigration();

        self::assertSame('checkbox', $this->displayTypeOf($rowId));
    }

    public function testARowWithAnEmptyDisplayTypeGetsTheCheckboxList(): void
    {
        $rowId = $this->createRow('');

        $this->runMigration();

        self::assertSame('checkbox', $this->displayTypeOf($rowId));
    }

    public function testARowWithADisplayTypeKeepsIt(): void
    {
        $rowId = $this->createRow('radio');

        $this->runMigration();

        self::assertSame('radio', $this->displayTypeOf($rowId));
    }

    public function testTheStatementCanBeReplayed(): void
    {
        $rowId = $this->createRow(null);

        $this->runMigration();
        $this->runMigration();

        self::assertSame('checkbox', $this->displayTypeOf($rowId));
    }

    private function runMigration(): void
    {
        $connection = Propel::getWriteConnection(ChoiceFilterTableMap::DATABASE_NAME);

        foreach ($this->migrationStatements() as $statement) {
            $connection->exec($statement);
        }

        ChoiceFilterTableMap::clearInstancePool();
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

            if (preg_match('/^UPDATE\s+`choice_filter`/i', $sql)) {
                $statements[] = $sql;
            }
        }

        self::assertCount(1, $statements, 'The 3.2.0 script does not give a display type to the choice_filter rows without one.');

        return $statements;
    }

    private function createRow(?string $displayType): int
    {
        $feature = $this->createFixtureFactory()->feature();

        $row = new ChoiceFilter();
        $row->setCategoryId($this->createFixtureFactory()->category()->getId());
        $row->setFeatureId($feature->getId());
        $row->setPosition(1);
        $row->setVisible(true);
        $row->setType($displayType);
        $row->save($this->getPropelConnection());

        return (int) $row->getId();
    }

    private function displayTypeOf(int $rowId): ?string
    {
        return ChoiceFilterQuery::create()->findPk($rowId)?->getType();
    }
}
