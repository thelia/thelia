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
use Thelia\Domain\Report\Customer\CustomerDormancy;
use Thelia\Model\ConfigI18nQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ConfigTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The dormancy threshold of the customer reports is seeded at 180 and labelled, on a fresh
 * install and on a shop updated from 3.4.0 alike. 3.4.0 is published: a shop already on it
 * never replays 3.4.0.sql, so the setting arrives with 3.5.0.sql.
 */
final class CustomerDormancySeedTest extends IntegrationTestCase
{
    public function testAFreshInstallSeedsTheThresholdAtOneHundredAndEightyAndLabelled(): void
    {
        $this->assertSeeded();
    }

    public function testTheUpdateFromThePublished340SeedsItOnAShopThatHasNoneAndCanBeReplayed(): void
    {
        self::assertStringNotContainsString(
            CustomerDormancy::CONFIG_KEY,
            $this->updateScript('3.4.0.sql'),
            '3.4.0.sql is published: a shop already on 3.4.0 never runs it again.',
        );

        ConfigQuery::create()->filterByName(CustomerDormancy::CONFIG_KEY)->delete($this->getPropelConnection());
        ConfigTableMap::clearInstancePool();
        self::assertNull(ConfigQuery::create()->findOneByName(CustomerDormancy::CONFIG_KEY));

        $this->runDormancyStatementsOf350();
        $this->runDormancyStatementsOf350();

        $this->assertSeeded();
    }

    public function testTheUpdateKeepsTheThresholdAMerchantAlreadySet(): void
    {
        ConfigQuery::write(CustomerDormancy::CONFIG_KEY, '90');

        $this->runDormancyStatementsOf350();

        ConfigTableMap::clearInstancePool();
        self::assertSame('90', ConfigQuery::create()->findOneByName(CustomerDormancy::CONFIG_KEY)?->getValue());
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    private function assertSeeded(): void
    {
        ConfigTableMap::clearInstancePool();
        ConfigQuery::resetCache();
        $config = ConfigQuery::create()->findOneByName(CustomerDormancy::CONFIG_KEY);

        self::assertNotNull($config);
        self::assertSame('180', $config->getValue());
        self::assertSame(180, CustomerDormancy::days());

        $titles = [];
        foreach (ConfigI18nQuery::create()->filterById($config->getId())->find() as $translation) {
            $titles[$translation->getLocale()] = $translation->getTitle();
        }

        self::assertNotEmpty($titles['en_US'] ?? null);
        self::assertNotEmpty($titles['fr_FR'] ?? null);
    }

    private function updateScript(string $name): string
    {
        return (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.$name);
    }

    private function runDormancyStatementsOf350(): void
    {
        $sql = $this->updateScript('3.5.0.sql');
        $start = strpos($sql, '-- Dormancy threshold of the customer reports');
        self::assertNotFalse($start, '3.5.0.sql does not hold the dormancy threshold block.');

        // The statements follow the comments of the header; the block ends where the next
        // section of the script starts, or at the end of the script when it is the last one.
        $firstStatement = strpos($sql, "\nINSERT", $start);
        self::assertNotFalse($firstStatement);
        $nextSection = strpos($sql, "\n-- ---", $firstStatement);
        $end = false === $nextSection ? strpos($sql, 'SET FOREIGN_KEY_CHECKS = 1;', $firstStatement) : $nextSection;
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

        self::assertSame(2, $run);
    }
}
