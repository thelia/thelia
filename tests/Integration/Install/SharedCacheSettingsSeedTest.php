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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

final class SharedCacheSettingsSeedTest extends IntegrationTestCase
{
    #[DataProvider('settings')]
    public function testAFreshInstallLabelsTheSettingInBothLanguages(string $name, string $locale): void
    {
        $config = ConfigQuery::create()->findOneByName($name);

        self::assertNotNull($config, $name);

        $title = $config->setLocale($locale)->getTitle();

        self::assertNotSame('', trim((string) $title), \sprintf('%s has no %s label', $name, $locale));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function settings(): iterable
    {
        foreach (['shared_cache_mode', 'shared_cache_ttl'] as $name) {
            foreach (['en_US', 'fr_FR'] as $locale) {
                yield $name.' '.$locale => [$name, $locale];
            }
        }
    }
}
