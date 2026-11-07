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
use Thelia\Domain\Accounting\AccountingChart;
use Thelia\Model\ConfigI18nQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ConfigTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The chart of accounts of the accounting exports is six settings, seeded empty and labelled
 * on a fresh install and on an updated shop alike: the exports refuse to run until the
 * merchant fills the accounts, and nothing a merchant already set is overwritten.
 */
final class AccountingSettingsSeedTest extends IntegrationTestCase
{
    // Written out rather than read from AccountingChart: the seed is checked on its own.
    private const SETTINGS = [
        'accounting_journal_code',
        'accounting_journal_label',
        'accounting_customer_account',
        'accounting_shipping_account',
        'accounting_rate_accounts',
        'accounting_commercial_credit_account',
    ];

    public function testAFreshInstallSeedsTheSettingsEmptyAndLabelled(): void
    {
        $this->assertSeeded();
    }

    public function testASeededShopHasAChartThatSaysWhatIsMissing(): void
    {
        ConfigQuery::resetCache();

        $chart = AccountingChart::fromSettings();

        self::assertSame(AccountingChart::DEFAULT_JOURNAL_CODE, $chart->journalCode);
        self::assertSame(AccountingChart::DEFAULT_JOURNAL_LABEL, $chart->journalLabel);
        self::assertSame(
            [AccountingChart::MISSING_CUSTOMER_ACCOUNT, AccountingChart::MISSING_SHIPPING_ACCOUNT, AccountingChart::MISSING_RATE_ACCOUNTS],
            $chart->missing(),
        );
    }

    public function testTheUpdateSeedsThemOnAShopThatHasNoneAndCanBeReplayed(): void
    {
        ConfigQuery::create()->filterByName(self::SETTINGS)->delete($this->getPropelConnection());
        self::assertSame(0, ConfigQuery::create()->filterByName(self::SETTINGS)->count());

        $this->runAccountingStatementsOf3400();
        $this->runAccountingStatementsOf3400();

        $this->assertSeeded();
    }

    public function testTheUpdateKeepsTheValuesAMerchantAlreadySet(): void
    {
        ConfigQuery::write(AccountingChart::CUSTOMER_ACCOUNT_KEY, '411000');
        ConfigQuery::write(AccountingChart::RATE_ACCOUNTS_KEY, '20:706200:445720');

        $this->runAccountingStatementsOf3400();

        ConfigTableMap::clearInstancePool();
        self::assertSame('411000', ConfigQuery::create()->findOneByName(AccountingChart::CUSTOMER_ACCOUNT_KEY)?->getValue());
        self::assertSame('20:706200:445720', ConfigQuery::create()->findOneByName(AccountingChart::RATE_ACCOUNTS_KEY)?->getValue());
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    private function assertSeeded(): void
    {
        ConfigTableMap::clearInstancePool();

        foreach (self::SETTINGS as $name) {
            $config = ConfigQuery::create()->findOneByName($name);

            self::assertNotNull($config, $name.' is not seeded.');
            self::assertSame('', $config->getValue(), $name.' is not seeded empty.');

            $titles = [];
            foreach (ConfigI18nQuery::create()->filterById($config->getId())->find() as $translation) {
                $titles[$translation->getLocale()] = $translation->getTitle();
            }

            foreach (['en_US', 'fr_FR', 'es_ES', 'it_IT'] as $locale) {
                self::assertNotEmpty($titles[$locale] ?? null, $name.' has no title in '.$locale.'.');
            }
        }
    }

    private function runAccountingStatementsOf3400(): void
    {
        $sql = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.4.0.sql');
        $start = strpos($sql, '-- Accounting exports');
        self::assertNotFalse($start, '3.4.0.sql no longer holds the accounting exports block.');

        $headerEnd = strpos($sql, "\n-- ---", $start);
        $nextSection = false === $headerEnd ? false : strpos($sql, "\n-- ---", $headerEnd + 1);
        $end = false === $nextSection ? strpos($sql, 'SET FOREIGN_KEY_CHECKS = 1;', $start) : $nextSection;
        self::assertNotFalse($end);

        $connection = Propel::getWriteConnection(ConfigTableMap::DATABASE_NAME);
        $run = 0;

        foreach (explode(';', substr($sql, $start, $end - $start)) as $chunk) {
            $statement = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if ('' !== $statement) {
                $connection->exec($statement);
                ++$run;
            }
        }

        self::assertGreaterThan(8, $run);
    }
}
