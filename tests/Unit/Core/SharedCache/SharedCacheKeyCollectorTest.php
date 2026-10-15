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
use Thelia\Core\SharedCache\SharedCacheContributorInterface;
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
        yield 'a resource without a name of its own' => ['/api/front/currencies/1', ['currencies']];
        yield 'a combination, which a product change purges by its list' => ['/api/front/product_sale_elements/12', ['product_sale_elements']];
        yield 'an image of a product' => ['/api/front/product_images/5', ['product_images']];
        yield 'a list of such resources' => ['/api/front/product_images', ['product_images']];
        yield 'the languages' => ['/api/front/languages', ['languages']];
        yield 'the sales' => ['/api/front/sales', ['sales']];
        yield 'a sale' => ['/api/front/sales/1', ['sales']];
        yield 'the videos of products' => ['/api/front/product_videos', ['product_videos']];
        yield 'the associations of products' => ['/api/front/product_associations', ['product_associations']];
        yield 'the filters of a listing' => ['/api/front/tfilters/products', ['tfilters']];
        yield 'not a front path' => ['/api/admin/products/42', []];
    }

    #[DataProvider('listedPaths')]
    public function testReadingAListedResourceKeepsThePageComplete(string $path): void
    {
        $collector = new SharedCacheKeyCollector(SharedCacheKeyCollector::READABLE_PREFIXES);

        $collector->record($path);

        self::assertTrue($collector->isComplete());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function listedPaths(): iterable
    {
        yield 'the languages' => ['/api/front/languages'];
        yield 'the sales' => ['/api/front/sales?active=true&itemsPerPage=1000'];
        yield 'the videos of products' => ['/api/front/product_videos?product=42'];
        yield 'the videos a product page reads through its combinations' => ['/api/front/product_sale_elements_product_video?productSaleElement=12'];
        yield 'the associations of products' => ['/api/front/product_associations'];
        yield 'the filters of a listing' => ['/api/front/tfilters/products'];
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

    public function testAResourceAModuleDeclaresTagsThePageWithTheKeysOfTheModule(): void
    {
        $collector = new SharedCacheKeyCollector(SharedCacheKeyCollector::READABLE_PREFIXES, [$this->contributor(['/api/header-highlights/' => ['header_highlights']])]);

        $collector->record('/api/header-highlights?width=1280');
        $collector->record('/api/header-highlights/3');

        self::assertSame(['header_highlights'], $collector->keys());
        self::assertTrue($collector->isComplete());
    }

    public function testAPathThatOnlyStartsLikeADeclaredResourceIsNotReadable(): void
    {
        $collector = new SharedCacheKeyCollector(SharedCacheKeyCollector::READABLE_PREFIXES, [$this->contributor(['/api/header-highlights' => ['header_highlights']])]);

        $collector->record('/api/header-highlights-admin');

        self::assertFalse($collector->isComplete());
    }

    public function testTwoModulesDeclaringTheSameResourceAddTheirKeys(): void
    {
        $collector = new SharedCacheKeyCollector([], [
            $this->contributor(['/api/shared' => ['one']]),
            $this->contributor(['/api/shared' => ['two', 'one']]),
        ]);

        $collector->record('/api/shared');

        self::assertSame(['one', 'two'], $collector->keys());
    }

    public function testAPageTaggedByItsViewKeepsItsKeysWithinTheLimit(): void
    {
        $collector = new SharedCacheKeyCollector([]);

        $collector->tag('cms-page-3', 'cms', '', 'cms-page-3');
        self::assertSame(['cms-page-3', 'cms'], $collector->keys());
        self::assertTrue($collector->isComplete());

        $collector->tag(...array_map(static fn (int $id): string => 'key-'.$id, range(1, SharedCacheKeyCollector::MAX_KEYS)));

        self::assertFalse($collector->isComplete());
        self::assertCount(SharedCacheKeyCollector::MAX_KEYS, $collector->keys());
    }

    /**
     * @param array<string, list<string>> $resources
     */
    private function contributor(array $resources): SharedCacheContributorInterface
    {
        return new class($resources) implements SharedCacheContributorInterface {
            /**
             * @param array<string, list<string>> $resources
             */
            public function __construct(private readonly array $resources)
            {
            }

            public function readableResources(): array
            {
                return $this->resources;
            }

            public function shareableViews(): array
            {
                return [];
            }
        };
    }
}
