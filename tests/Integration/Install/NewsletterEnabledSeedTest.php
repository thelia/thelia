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
 * The newsletter switch is a configuration variable seeded at 1 and labelled, on a fresh
 * install and on an updated shop alike, so no shop changes behaviour by being updated.
 */
final class NewsletterEnabledSeedTest extends IntegrationTestCase
{
    public function testAFreshInstallSeedsTheSettingAtOneAndLabelled(): void
    {
        $this->assertSeeded();
    }

    public function testTheUpdateSeedsItOnAShopThatHasNoneAndCanBeReplayed(): void
    {
        ConfigQuery::create()->filterByName('newsletter_enabled')->delete($this->getPropelConnection());
        self::assertNull(ConfigQuery::create()->findOneByName('newsletter_enabled'));

        $this->runNewsletterStatementsOf3400();
        $this->runNewsletterStatementsOf3400();

        $this->assertSeeded();
    }

    public function testTheUpdateKeepsTheValueAMerchantAlreadySet(): void
    {
        ConfigQuery::write('newsletter_enabled', '0');

        $this->runNewsletterStatementsOf3400();

        ConfigTableMap::clearInstancePool();
        self::assertSame('0', ConfigQuery::create()->findOneByName('newsletter_enabled')?->getValue());
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    private function assertSeeded(): void
    {
        ConfigTableMap::clearInstancePool();
        $config = ConfigQuery::create()->findOneByName('newsletter_enabled');

        self::assertNotNull($config);
        self::assertSame('1', $config->getValue());
        self::assertTrue(ConfigQuery::isNewsletterEnabled());

        $titles = [];
        foreach (ConfigI18nQuery::create()->filterById($config->getId())->find() as $translation) {
            $titles[$translation->getLocale()] = $translation->getTitle();
        }

        self::assertNotEmpty($titles['en_US'] ?? null);
        self::assertNotEmpty($titles['fr_FR'] ?? null);
    }

    private function runNewsletterStatementsOf3400(): void
    {
        $sql = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.4.0.sql');
        $start = strpos($sql, '-- Newsletter switch');
        self::assertNotFalse($start, '3.4.0.sql no longer holds the newsletter switch block.');

        // The block ends where the next section of the script starts (the rule under its own
        // header comes first), or at the end of the script when it is the last section.
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

        self::assertGreaterThan(4, $run);
    }
}
