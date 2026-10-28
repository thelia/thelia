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

namespace Thelia\Tests\Integration\Domain\Ai;

use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

final class AiGlobalSettingsSeedTest extends IntegrationTestCase
{
    public function testAFreshInstallStartsWithTheAiLayerOnWithoutCapAndWithoutEuOnly(): void
    {
        self::assertSame('1', ConfigQuery::create()->findOneByName('ai_enabled')?->getValue());
        self::assertSame('0', ConfigQuery::create()->findOneByName('ai_monthly_cap')?->getValue());
        self::assertSame('0', ConfigQuery::create()->findOneByName('ai_eu_only')?->getValue());
        self::assertSame('400', ConfigQuery::create()->findOneByName('ai_log_retention_days')?->getValue());
    }

    public function testTheEuOnlyOptionIsWrittenAndReadBack(): void
    {
        $settings = new AiSettings();
        $original = ConfigQuery::read('ai_eu_only', '0');

        try {
            $settings->setEuropeanUnionOnly(true);
            self::assertTrue($settings->isEuropeanUnionOnly());
            $settings->setEuropeanUnionOnly(false);
            self::assertFalse($settings->isEuropeanUnionOnly());
            self::assertSame('0', ConfigQuery::create()->findOneByName('ai_eu_only')?->getValue());
        } finally {
            ConfigQuery::write('ai_eu_only', $original);
        }
    }

    public function testTheMasterSwitchAndTheMonthlyCapAreWrittenAndReadBack(): void
    {
        $settings = new AiSettings();
        $enabled = ConfigQuery::read('ai_enabled', '1');
        $cap = ConfigQuery::read('ai_monthly_cap', '0');

        try {
            $settings->setEnabled(false);
            self::assertFalse($settings->isEnabled());
            $settings->setEnabled(true);
            self::assertTrue($settings->isEnabled());

            $settings->setMonthlyCap(' 25.50 ');
            self::assertSame('25.50', $settings->monthlyCap());

            $this->expectException(\Thelia\Domain\Ai\Exception\InvalidAiArgumentException::class);
            $settings->setMonthlyCap('-1');
        } finally {
            ConfigQuery::write('ai_enabled', $enabled);
            ConfigQuery::write('ai_monthly_cap', $cap);
        }
    }
}
