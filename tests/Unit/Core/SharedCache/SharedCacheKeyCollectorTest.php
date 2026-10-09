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

namespace Thelia\Tests\Unit\Core\SharedCache;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Core\SharedCache\SharedCacheKey;
use Thelia\Core\SharedCache\SharedCacheKeyCollector;

final class SharedCacheKeyCollectorTest extends TestCase
{
    #[DataProvider('paths')]
    public function testAPathBecomesTheKeysOfWhatItReads(string $path, array $expected): void
    {
        self::assertSame($expected, SharedCacheKey::forPath($path));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function paths(): iterable
    {
        yield 'a product' => ['/api/front/products/42', ['product-42']];
        yield 'a category' => ['/api/front/categories/7', ['category-7']];
        yield 'a brand' => ['/api/front/brands/3', ['brand-3']];
        yield 'a content' => ['/api/front/contents/9', ['content-9']];
        yield 'a folder' => ['/api/front/folders/2', ['folder-2']];
        yield 'a list of products' => ['/api/front/products', ['products']];
        yield 'a list of categories' => ['/api/front/categories', ['categories']];
        yield 'what hangs under a product' => ['/api/front/products/42/product_images', ['product-42']];
        yield 'a resource without a name of its own' => ['/api/front/currencies/1', ['currencies-1']];
        yield 'a list of such resources' => ['/api/front/product_images', ['product_images']];
        yield 'not a front path' => ['/api/admin/products/42', []];
    }

    public function testEveryReadIsKeptOnceInTheOrderItWasMade(): void
    {
        $collector = new SharedCacheKeyCollector(SharedCacheKeyCollector::READABLE_PREFIXES);

        $collector->record('/api/front/products/42');
        $collector->record('/api/front/categories?locale=fr_FR');
        $collector->record('/api/front/products/42');

        self::assertSame(['product-42', 'categories'], $collector->keys());
        self::assertTrue($collector->isComplete());
    }

    #[DataProvider('unlistedPaths')]
    public function testReadingWhatIsNotListedMakesThePageIncomplete(string $path): void
    {
        $collector = new SharedCacheKeyCollector(SharedCacheKeyCollector::READABLE_PREFIXES);

        $collector->record($path);

        self::assertFalse($collector->isComplete());
        self::assertSame([], $collector->keys());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unlistedPaths(): iterable
    {
        yield 'an account' => ['/api/front/account/customers/7'];
        yield 'a cart' => ['/api/front/carts/1'];
        yield 'a prefix of a longer name' => ['/api/front/products_to_check/1'];
        yield 'the sales' => ['/api/front/sales/1'];
    }

    public function testMoreKeysThanTheLimitMakeThePageIncomplete(): void
    {
        $collector = new SharedCacheKeyCollector(SharedCacheKeyCollector::READABLE_PREFIXES);

        for ($id = 1; $id <= SharedCacheKeyCollector::MAX_KEYS; ++$id) {
            $collector->record('/api/front/products/'.$id);
        }
        self::assertTrue($collector->isComplete());

        $collector->record('/api/front/products/'.(SharedCacheKeyCollector::MAX_KEYS + 1));

        self::assertFalse($collector->isComplete());
        self::assertCount(SharedCacheKeyCollector::MAX_KEYS, $collector->keys());
    }

    public function testResettingForgetsEverything(): void
    {
        $collector = new SharedCacheKeyCollector(SharedCacheKeyCollector::READABLE_PREFIXES);
        $collector->record('/api/front/products/42');
        $collector->record('/api/front/account/customers/7');

        $collector->reset();

        self::assertSame([], $collector->keys());
        self::assertTrue($collector->isComplete());
    }
}
