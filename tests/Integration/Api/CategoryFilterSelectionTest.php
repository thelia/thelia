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

namespace Thelia\Tests\Integration\Api;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Model\ProductQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * A product is filed in a category, so two sub-categories checked in the filter column ask for
 * either of them. A shopper who checks a second aisle expects more products, never an empty list.
 *
 * With a depth, a checked category browses its own branch: checking an aisle that holds only
 * shelves shows the products of those shelves. A hidden category stops the walk.
 *
 * Catalogue:
 *
 *   department            —
 *     fruits             FRUIT
 *       citrus           CITRUS
 *     vegetables         VEGETABLE
 *     archived (hidden)  ARCHIVED
 *   other department     OTHER
 */
final class CategoryFilterSelectionTest extends IntegrationTestCase
{
    private const FRUIT = 'CATEGORY-FRUIT';
    private const CITRUS = 'CATEGORY-CITRUS';
    private const VEGETABLE = 'CATEGORY-VEGETABLE';
    private const ARCHIVED = 'CATEGORY-ARCHIVED';
    private const OTHER = 'CATEGORY-OTHER';

    private FilterService $filterService;

    /** @var array<string, int> */
    private array $categoryIds = [];

    /** @var array<string, int> product reference => id, in creation order */
    private array $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->filterService = static::getContainer()->get(FilterService::class);
        $this->createCatalogue();
    }

    public function testOneCheckedSubCategoryKeepsItsProducts(): void
    {
        self::assertSame(
            [self::FRUIT],
            $this->matchingReferences([$this->categoryIds['fruits']]),
        );
    }

    public function testTwoCheckedSubCategoriesWidenTheSelection(): void
    {
        self::assertSame(
            [self::FRUIT, self::VEGETABLE],
            $this->matchingReferences([$this->categoryIds['fruits'], $this->categoryIds['vegetables']]),
        );
    }

    public function testACheckedSubCategoryRepeatedByTheQueryStringCountsOnce(): void
    {
        self::assertSame(
            [self::FRUIT],
            $this->matchingReferences([$this->categoryIds['fruits'], (string) $this->categoryIds['fruits']]),
        );
    }

    public function testACheckedCategoryBrowsesItsOwnBranchWithADepth(): void
    {
        self::assertSame(
            [self::FRUIT, self::CITRUS],
            $this->matchingReferences([$this->categoryIds['fruits']], categoryDepth: 5),
        );
    }

    public function testTheBrowsedCategoryWithADepthKeepsItsWholeVisibleBranch(): void
    {
        self::assertSame(
            [self::FRUIT, self::CITRUS, self::VEGETABLE],
            $this->matchingReferences([$this->categoryIds['department']], categoryDepth: 5),
        );
    }

    public function testTheBrowsedCategoryWithoutADepthKeepsOnlyWhatItHoldsItself(): void
    {
        self::assertSame(
            [],
            $this->matchingReferences([$this->categoryIds['department']]),
        );
    }

    /**
     * @param list<int|string> $selection the checked category ids, as the query string carries them
     *
     * @return list<string> the references of the matching products, in creation order
     */
    private function matchingReferences(array $selection, ?int $categoryDepth = null): array
    {
        // Restricted to the products this test created: the filter is what is under test,
        // not whatever else the database holds.
        $query = ProductQuery::create()->filterById(array_values($this->productIds), Criteria::IN);

        $filtered = $this->filterService->filterWithTFilter(
            tfilters: ['category' => [$this->categoryIds['department'] => $selection]],
            resource: 'products',
            query: $query,
            categoryDepth: $categoryDepth,
        );

        $matched = [];

        foreach ($filtered->find($this->getPropelConnection()) as $product) {
            $matched[] = (int) $product->getId();
        }

        return array_keys(array_filter(
            $this->productIds,
            static fn (int $id): bool => \in_array($id, $matched, true),
        ));
    }

    private function createCatalogue(): void
    {
        $factory = $this->createFixtureFactory();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $categories = ['department' => $factory->category()];
        $departmentId = (int) $categories['department']->getId();

        $categories['fruits'] = $factory->category(['parent' => $departmentId]);
        $categories['vegetables'] = $factory->category(['parent' => $departmentId]);
        $categories['archived'] = $factory->category(['parent' => $departmentId, 'visible' => 0]);
        $categories['citrus'] = $factory->category(['parent' => (int) $categories['fruits']->getId()]);
        $categories['other'] = $factory->category();

        foreach ($categories as $name => $category) {
            $this->categoryIds[$name] = (int) $category->getId();
        }

        $catalogue = [
            self::FRUIT => 'fruits',
            self::CITRUS => 'citrus',
            self::VEGETABLE => 'vegetables',
            self::ARCHIVED => 'archived',
            self::OTHER => 'other',
        ];

        foreach ($catalogue as $reference => $name) {
            $product = $factory->product($categories[$name], $taxRule, $currency, ['ref' => $reference]);
            $this->productIds[$reference] = (int) $product->getId();
        }
    }
}
