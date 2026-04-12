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

namespace Thelia\Tests\Integration\Core\EventListener;

use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

final class ModelConfigListenerTest extends IntegrationTestCase
{
    public function testConfigChangeInvalidatesCache(): void
    {
        // Write a config value — the ModelConfigListener should
        // reset the cache automatically on POST_SAVE.
        ConfigQuery::write('test_cache_invalidation', 'initial');
        self::assertSame('initial', ConfigQuery::read('test_cache_invalidation'));

        // Update it.
        ConfigQuery::write('test_cache_invalidation', 'updated');
        self::assertSame('updated', ConfigQuery::read('test_cache_invalidation'));
    }

    public function testConfigDeleteInvalidatesCache(): void
    {
        ConfigQuery::write('test_delete_cache', 'to_delete');
        self::assertSame('to_delete', ConfigQuery::read('test_delete_cache'));

        $config = ConfigQuery::create()
            ->findOneByName('test_delete_cache');
        self::assertNotNull($config);

        $config->delete();

        self::assertNull(ConfigQuery::read('test_delete_cache'));
    }
}
