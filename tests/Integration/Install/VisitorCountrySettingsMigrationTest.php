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
use Thelia\Model\ConfigI18nQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ConfigTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * A shop that upgrades gets the four settings of the visitor country detection, switched
 * off and labelled, and a value the merchant already chose is left alone.
 */
final class VisitorCountrySettingsMigrationTest extends IntegrationTestCase
{
    private const DEFAULTS = [
        'visitor_country_mode' => 'off',
        'visitor_country_source' => 'header',
        'visitor_country_header' => 'CF-IPCountry',
        'visitor_country_database' => '',
    ];

    public function testTheUpdateSeedsTheFourSettingsSwitchedOffAndLabelled(): void
    {
        $this->deleteSettings();

        $this->runStatementsOf('3.3.0.sql');

        $this->assertSeeded(self::DEFAULTS);
    }

    public function testReplayingTheUpdateLeavesAValueTheShopChoseAlone(): void
    {
        $this->runStatementsOf('3.3.0.sql');
        ConfigQuery::write('visitor_country_mode', 'suggest');

        $this->runStatementsOf('3.3.0.sql');

        $this->assertSeeded(['visitor_country_mode' => 'suggest'] + self::DEFAULTS);
    }

    protected function tearDown(): void
    {
        $this->deleteSettings();
        $this->runStatementsOf('3.3.0.sql');
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    private function deleteSettings(): void
    {
        ConfigQuery::create()->filterByName(array_keys(self::DEFAULTS))->delete($this->getPropelConnection());
        ConfigTableMap::clearInstancePool();
    }

    /**
     * @param array<string, string> $expected
     */
    private function assertSeeded(array $expected): void
    {
        ConfigTableMap::clearInstancePool();

        foreach ($expected as $name => $value) {
            $config = ConfigQuery::create()->findOneByName($name);

            self::assertNotNull($config, $name);
            self::assertSame($value, $config->getValue(), $name);

            $titles = [];
            foreach (ConfigI18nQuery::create()->filterById($config->getId())->find() as $translation) {
                $titles[$translation->getLocale()] = $translation->getTitle();
            }

            foreach (['en_US', 'fr_FR', 'de_DE', 'es_ES', 'nl_NL', 'ru_RU'] as $locale) {
                self::assertNotEmpty($titles[$locale] ?? null, \sprintf('%s has no label in %s.', $name, $locale));
            }
        }
    }

    private function runStatementsOf(string $script): void
    {
        // Comments first: the one above the settings has a semicolon in its text.
        $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.$script)) ?? '';
        $connection = Propel::getWriteConnection(ConfigTableMap::DATABASE_NAME);
        $run = 0;

        foreach (explode(';', $sql) as $chunk) {
            $statement = trim($chunk);

            if (1 === preg_match('/^INSERT\s+IGNORE\s+INTO\s+`config(_i18n)?`\s/i', $statement)
                && str_contains($statement, 'visitor_country_')) {
                $connection->exec($statement);
                ++$run;
            }
        }

        self::assertSame(5, $run, \sprintf('%s no longer seeds the four settings and their labels.', $script));
    }
}
