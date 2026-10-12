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
use Thelia\Model\Hook;
use Thelia\Model\HookI18nQuery;
use Thelia\Model\HookQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\HookI18nTableMap;
use Thelia\Model\Map\HookTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The statements 3.3.0.sql runs to give a shop installed before it the back-office hooks the
 * Twig templates call. They are read from the shipped script, so the test fails if the script
 * stops doing what it claims.
 */
final class BackOfficeHookMigrationTest extends IntegrationTestCase
{
    private const int BACK_OFFICE = 2;

    /**
     * Hooks of the update, removed to stand for a shop installed before it.
     */
    private const array MISSING = ['customer.tab', 'customer.tab-content', 'customer-edit.actions'];

    public function testAShopWithoutTheHooksGetsThemWithTheirEnglishTitle(): void
    {
        $this->deleteHooks(self::MISSING);

        $this->runMigration();

        foreach (self::MISSING as $code) {
            $hook = HookQuery::create()->filterByCode($code)->filterByType(self::BACK_OFFICE)->findOne();

            self::assertNotNull($hook, $code);
            self::assertTrue((bool) $hook->getActivate(), $code);
            self::assertSame(LangQuery::create()->count(), HookI18nQuery::create()->filterById($hook->getId())->count(), $code);
            self::assertSame($code === 'customer.tab', (bool) $hook->getBlock(), $code);
        }

        self::assertSame('Customer - tab', $this->titleOf('customer.tab', 'en_US'));
    }

    /**
     * A module that created one of these hooks before the update keeps it as it is: its id,
     * and the title the merchant gave it.
     */
    public function testAHookAModuleAlreadyCreatedKeepsItsIdAndItsTitles(): void
    {
        $this->deleteHooks(self::MISSING);

        $own = (new Hook())
            ->setCode('customer-edit.actions')
            ->setType(self::BACK_OFFICE)
            ->setByModule(true)
            ->setBlock(false)
            ->setNative(false)
            ->setActivate(true)
            ->setPosition(1)
            ->setLocale('fr_FR')
            ->setTitle('Actions du module');
        $own->save($this->getPropelConnection());

        $this->runMigration();

        self::assertSame(1, HookQuery::create()->filterByCode('customer-edit.actions')->filterByType(self::BACK_OFFICE)->count());
        self::assertSame($own->getId(), HookQuery::create()->filterByCode('customer-edit.actions')->filterByType(self::BACK_OFFICE)->findOne()?->getId());
        self::assertSame('Actions du module', $this->titleOf('customer-edit.actions', 'fr_FR'));
        self::assertSame('Customer edit - actions', $this->titleOf('customer-edit.actions', 'en_US'));
    }

    public function testTheStatementsCanBeReplayed(): void
    {
        $this->deleteHooks(self::MISSING);

        $this->runMigration();
        $hooks = HookQuery::create()->count();
        $titles = HookI18nQuery::create()->count();

        $this->runMigration();

        self::assertSame($hooks, HookQuery::create()->count());
        self::assertSame($titles, HookI18nQuery::create()->count());

        foreach (self::MISSING as $code) {
            self::assertSame(1, HookQuery::create()->filterByCode($code)->filterByType(self::BACK_OFFICE)->count(), $code);
        }
    }

    /**
     * @param list<string> $codes
     */
    private function deleteHooks(array $codes): void
    {
        HookQuery::create()->filterByCode($codes)->filterByType(self::BACK_OFFICE)->delete($this->getPropelConnection());

        $this->clearInstancePools();

        self::assertSame(0, HookQuery::create()->filterByCode($codes)->filterByType(self::BACK_OFFICE)->count());
    }

    private function runMigration(): void
    {
        $connection = Propel::getWriteConnection(HookTableMap::DATABASE_NAME);

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

            if (1 === preg_match('/^INSERT\s+INTO\s+`hook(?:_i18n)?`\s/i', $sql)) {
                $statements[] = $sql;
            }
        }

        self::assertCount(2, $statements, 'The 3.3.0 script does not add the back-office hooks and their titles.');

        return $statements;
    }

    private function titleOf(string $code, string $locale): ?string
    {
        $hook = HookQuery::create()->filterByCode($code)->filterByType(self::BACK_OFFICE)->findOne();

        return null === $hook ? null : HookI18nQuery::create()->filterById($hook->getId())->filterByLocale($locale)->findOne()?->getTitle();
    }

    private function clearInstancePools(): void
    {
        HookTableMap::clearInstancePool();
        HookI18nTableMap::clearInstancePool();
    }
}
