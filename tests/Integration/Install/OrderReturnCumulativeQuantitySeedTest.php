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
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\ConfigI18nQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ConfigTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The setting of the cumulative count of the returned quantity is a configuration
 * variable the merchant finds, on and labelled, on a fresh install and on an
 * updated shop alike.
 */
final class OrderReturnCumulativeQuantitySeedTest extends IntegrationTestCase
{
    public function testAFreshInstallSeedsTheSettingOnAndLabelled(): void
    {
        $this->assertSeeded();
    }

    public function testTheUpdateSeedsItOnAShopThatHasNoneAndCanBeReplayed(): void
    {
        ConfigQuery::create()->filterByName(ReturnEligibilityChecker::CUMULATIVE_QUANTITY_CONFIG_KEY)->delete($this->getPropelConnection());
        self::assertNull(ConfigQuery::create()->findOneByName(ReturnEligibilityChecker::CUMULATIVE_QUANTITY_CONFIG_KEY));

        $this->runSettingStatementsOf('3.3.0.sql');
        $this->runSettingStatementsOf('3.3.0.sql');

        $this->assertSeeded();
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    private function assertSeeded(): void
    {
        ConfigTableMap::clearInstancePool();
        $config = ConfigQuery::create()->findOneByName(ReturnEligibilityChecker::CUMULATIVE_QUANTITY_CONFIG_KEY);

        self::assertNotNull($config);
        self::assertSame('1', $config->getValue());

        $titles = [];
        foreach (ConfigI18nQuery::create()->filterById($config->getId())->find() as $translation) {
            $titles[$translation->getLocale()] = $translation->getTitle();
        }

        self::assertNotEmpty($titles['en_US'] ?? null);
        self::assertNotEmpty($titles['fr_FR'] ?? null);
    }

    private function runSettingStatementsOf(string $script): void
    {
        $sql = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.$script);
        $connection = Propel::getWriteConnection(ConfigTableMap::DATABASE_NAME);
        $run = 0;

        foreach (explode(';', $sql) as $chunk) {
            $statement = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (1 === preg_match('/^INSERT\s+IGNORE\s+INTO\s+`config(_i18n)?`\s/i', $statement)
                && str_contains($statement, ReturnEligibilityChecker::CUMULATIVE_QUANTITY_CONFIG_KEY)) {
                $connection->exec($statement);
                ++$run;
            }
        }

        self::assertSame(2, $run, \sprintf('%s no longer seeds the setting and its labels.', $script));
    }
}
