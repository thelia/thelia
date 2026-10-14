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

namespace Thelia\Tests\Api\Front;

use Thelia\Model\Category;
use Thelia\Model\Product;
use Thelia\Model\ProductCategory;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Test\ApiTestCase;

/**
 * A category listing sorted by the date each product was put in the category, which is not the
 * date the product was created: a product created long ago can have just been added to a range.
 * The date is read on the link the category filter keeps, so a product's link to a category
 * outside the listed branch does not rank it.
 *
 * Catalogue (product created / put in the category):
 *
 *   root            LINK-NEW     2020 / 2024
 *     sub           LINK-MID     2022 / 2022, also put in "other" in 2025
 *                   LINK-OLD     2024 / 2020
 *   other root      LINK-MID
 */
final class ProductCategoryLinkOrderApiTest extends ApiTestCase
{
    private int $rootId;

    private int $subId;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $root = $factory->category();
        $sub = $factory->category(['parent' => $root->getId()]);
        $other = $factory->category();
        $this->rootId = $root->getId();
        $this->subId = $sub->getId();

        $new = $factory->product($root, $taxRule, $currency, ['ref' => 'LINK-NEW', 'createdAt' => new \DateTime('2020-01-01 08:00:00')]);
        // Created before LINK-MID, so the default order of "sub" (position) is the reverse of the link order.
        $old = $factory->product($sub, $taxRule, $currency, ['ref' => 'LINK-OLD', 'createdAt' => new \DateTime('2024-01-01 08:00:00')]);
        $mid = $factory->product($sub, $taxRule, $currency, ['ref' => 'LINK-MID', 'createdAt' => new \DateTime('2022-01-01 08:00:00')]);

        $this->datedLink($new, $root, '2024-06-01 10:00:00');
        $this->datedLink($mid, $sub, '2022-06-01 10:00:00');
        $this->datedLink($old, $sub, '2020-06-01 10:00:00');

        (new ProductCategory())
            ->setProductId($mid->getId())
            ->setCategoryId($other->getId())
            ->setDefaultCategory(0)
            ->setPosition(1)
            ->save($this->getPropelConnection());
        $this->datedLink($mid, $other, '2025-06-01 10:00:00');
    }

    public function testABranchIsSortedByTheDateItsProductsWerePutInIt(): void
    {
        self::assertSame(
            ['LINK-NEW', 'LINK-MID', 'LINK-OLD'],
            $this->references('productCategories.category.id='.$this->rootId.'&depth=10&order[productCategories.createdAt]=desc'),
        );
        self::assertSame(
            ['LINK-OLD', 'LINK-MID', 'LINK-NEW'],
            $this->references('productCategories.category.id='.$this->rootId.'&depth=10&order[productCategories.createdAt]=asc'),
        );
    }

    public function testTheLinkDateIsNotTheProductCreationDate(): void
    {
        self::assertSame(
            ['LINK-OLD', 'LINK-MID', 'LINK-NEW'],
            $this->references('productCategories.category.id='.$this->rootId.'&depth=10&order[createdAt]=desc'),
        );
    }

    public function testACategoryWithoutDepthIsSortedTheSameWay(): void
    {
        self::assertSame(
            ['LINK-MID', 'LINK-OLD'],
            $this->references('productCategories.category.id='.$this->subId.'&order[productCategories.createdAt]=desc'),
        );
    }

    public function testSortingDoesNotChangeTheAnnouncedTotal(): void
    {
        $branch = 'productCategories.category.id='.$this->rootId.'&depth=10';

        self::assertSame(3, $this->total($branch));
        self::assertSame(3, $this->total($branch.'&order[productCategories.createdAt]=desc'));
    }

    private function datedLink(Product $product, Category $category, string $date): void
    {
        ProductCategoryQuery::create()
            ->filterByProductId($product->getId())
            ->filterByCategoryId($category->getId())
            ->update(['CreatedAt' => $date], $this->getPropelConnection());
    }

    /**
     * @return list<string>
     */
    private function references(string $query): array
    {
        return array_map(
            static fn (array $product): string => (string) $product['ref'],
            $this->decode($query)['hydra:member'] ?? [],
        );
    }

    private function total(string $query): int
    {
        return (int) $this->decode($query)['hydra:totalItems'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $query): array
    {
        $response = $this->jsonRequest('GET', '/api/front/products?'.$query.'&itemsPerPage=50');
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
