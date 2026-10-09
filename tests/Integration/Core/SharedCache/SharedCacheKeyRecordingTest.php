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

namespace Thelia\Tests\Integration\Core\SharedCache;

use Thelia\Api\Service\DataAccess\DataAccessService;
use Thelia\Core\SharedCache\SharedCacheKeyCollector;
use Thelia\Test\IntegrationTestCase;

final class SharedCacheKeyRecordingTest extends IntegrationTestCase
{
    private DataAccessService $dataAccess;

    private SharedCacheKeyCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataAccess = static::getContainer()->get(DataAccessService::class);
        $this->collector = static::getContainer()->get(SharedCacheKeyCollector::class);
        $this->collector->reset();
    }

    protected function tearDown(): void
    {
        $this->collector->reset();

        parent::tearDown();
    }

    public function testAPageReadingTheCatalogueIsTaggedWithWhatItRead(): void
    {
        $this->dataAccess->resources('/api/front/products');
        $this->dataAccess->resources('/api/front/categories');

        self::assertSame(['products', 'categories'], $this->collector->keys());
        self::assertTrue($this->collector->isComplete());
    }

    public function testAReadServedByTheMemoizerIsTaggedToo(): void
    {
        $this->dataAccess->resources('/api/front/brands');
        $this->collector->reset();

        $this->dataAccess->resources('/api/front/brands');

        self::assertSame(['brands'], $this->collector->keys());
    }

    public function testReadingAnAccountMakesThePageIncomplete(): void
    {
        $this->dataAccess->resources('/api/front/products');
        $this->dataAccess->resources('/api/front/account/addresses');

        self::assertFalse($this->collector->isComplete());
    }
}
