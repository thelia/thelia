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
use Thelia\Model\CheckoutStepI18n;
use Thelia\Model\CheckoutStepI18nQuery;
use Thelia\Model\CheckoutStepQuery;
use Thelia\Model\Map\CheckoutStepI18nTableMap;
use Thelia\Model\Map\CheckoutStepTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The statement 3.3.0.sql runs to give the checkout steps of a shop installed before it their
 * German titles. It is read from the shipped script, so the test fails if the script stops
 * doing what it claims.
 */
final class CheckoutStepGermanTitlesMigrationTest extends IntegrationTestCase
{
    private const array GERMAN = [
        'cart' => 'Ihr Warenkorb',
        'delivery' => 'Lieferung',
        'payment' => 'Zahlung',
        'confirmation' => 'Bestätigung',
    ];

    public function testAShopWithoutGermanTitlesGetsThemAndKeepsTheOthers(): void
    {
        $this->deleteGermanTitles();
        $others = $this->titlesOtherThanGerman();

        $this->runMigration();

        self::assertSame(self::sorted(self::GERMAN), $this->germanTitles());
        self::assertSame($others, $this->titlesOtherThanGerman());
    }

    /**
     * A table emptied by hand, or never filled, gets the German titles alone: the other
     * languages are the business of the script that created the table.
     */
    public function testATableWithoutAnyTitleGetsTheGermanOnes(): void
    {
        CheckoutStepI18nQuery::create()->deleteAll($this->getPropelConnection());
        $this->clearInstancePools();

        $this->runMigration();

        self::assertSame(self::sorted(self::GERMAN), $this->germanTitles());
        self::assertSame([], $this->titlesOtherThanGerman());
    }

    public function testAGermanTitleTheMerchantWroteIsKept(): void
    {
        $this->deleteGermanTitles();
        $payment = CheckoutStepQuery::create()->findOneByCode('payment');
        self::assertNotNull($payment);
        (new CheckoutStepI18n())
            ->setId($payment->getId())
            ->setLocale('de_DE')
            ->setTitle('Bezahlen')
            ->save($this->getPropelConnection());
        $this->clearInstancePools();

        $this->runMigration();

        self::assertSame(self::sorted(['payment' => 'Bezahlen'] + self::GERMAN), $this->germanTitles());
    }

    public function testATitleInAnotherLanguageTheMerchantChangedIsKept(): void
    {
        $this->deleteGermanTitles();
        $delivery = CheckoutStepQuery::create()->findOneByCode('delivery');
        self::assertNotNull($delivery);
        CheckoutStepI18nQuery::create()->filterById($delivery->getId())->filterByLocale('fr_FR')->update(['Title' => 'Expédition'], $this->getPropelConnection());
        $this->clearInstancePools();
        $others = $this->titlesOtherThanGerman();

        $this->runMigration();

        self::assertSame($others, $this->titlesOtherThanGerman());
        self::assertContains($delivery->getId().'|fr_FR|Expédition', $others);
    }

    public function testTheStatementCanBeReplayed(): void
    {
        $this->deleteGermanTitles();

        $this->runMigration();
        $titles = $this->allTitles();

        $this->runMigration();

        self::assertSame($titles, $this->allTitles());
    }

    private function deleteGermanTitles(): void
    {
        CheckoutStepI18nQuery::create()->filterByLocale('de_DE')->delete($this->getPropelConnection());
        $this->clearInstancePools();

        self::assertSame([], $this->germanTitles());
    }

    private function runMigration(): void
    {
        Propel::getWriteConnection(CheckoutStepTableMap::DATABASE_NAME)->exec($this->migrationStatement());

        $this->clearInstancePools();
    }

    private function migrationStatement(): string
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.3.0.sql');

        $statements = [];

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (1 === preg_match('/^INSERT\s+IGNORE\s+INTO\s+`checkout_step_i18n`\s/i', $sql)) {
                $statements[] = $sql;
            }
        }

        self::assertCount(1, $statements, 'The 3.3.0 script does not add the German titles of the checkout steps.');

        return $statements[0];
    }

    /**
     * @return array<string, string|null> step code => German title, by code
     */
    private function germanTitles(): array
    {
        $titles = [];

        foreach (array_keys(self::GERMAN) as $code) {
            $step = CheckoutStepQuery::create()->findOneByCode($code);
            self::assertNotNull($step, $code);
            $german = CheckoutStepI18nQuery::create()->filterById($step->getId())->filterByLocale('de_DE')->findOne();

            if (null !== $german) {
                $titles[$code] = $german->getTitle();
            }
        }

        ksort($titles);

        return $titles;
    }

    /**
     * @return list<string> "id|locale|title" of every title in another language than German
     */
    private function titlesOtherThanGerman(): array
    {
        return array_values(array_filter($this->allTitles(), static fn (string $row): bool => !str_contains($row, '|de_DE|')));
    }

    /**
     * @return list<string> "id|locale|title" of every title
     */
    private function allTitles(): array
    {
        $rows = [];

        foreach (CheckoutStepI18nQuery::create()->orderById()->orderByLocale()->find() as $title) {
            $rows[] = $title->getId().'|'.$title->getLocale().'|'.$title->getTitle();
        }

        return $rows;
    }

    /**
     * @param array<string, string> $titles
     *
     * @return array<string, string>
     */
    private static function sorted(array $titles): array
    {
        ksort($titles);

        return $titles;
    }

    private function clearInstancePools(): void
    {
        CheckoutStepTableMap::clearInstancePool();
        CheckoutStepI18nTableMap::clearInstancePool();
    }
}
