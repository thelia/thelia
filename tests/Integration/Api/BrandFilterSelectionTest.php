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
 * A product carries a single brand, so two brands checked in the filter column ask for
 * either of them. A shopper who checks a second brand expects more products, never an
 * empty list.
 */
final class BrandFilterSelectionTest extends IntegrationTestCase
{
    private const FIRST_BRAND_PRODUCT = 'BRAND-FIRST';
    private const SECOND_BRAND_PRODUCT = 'BRAND-SECOND';
    private const THIRD_BRAND_PRODUCT = 'BRAND-THIRD';
    private const UNBRANDED_PRODUCT = 'BRAND-NONE';

    private FilterService $filterService;

    /** @var array<string, int> */
    private array $brandIds = [];

    /** @var array<string, int> product reference => id, in creation order */
    private array $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->filterService = static::getContainer()->get(FilterService::class);
        $this->createCatalogue();
    }

    public function testOneCheckedBrandKeepsItsProducts(): void
    {
        self::assertSame(
            [self::FIRST_BRAND_PRODUCT],
            $this->matchingReferences([$this->brandIds['first']]),
        );
    }

    public function testTwoCheckedBrandsWidenTheSelection(): void
    {
        self::assertSame(
            [self::FIRST_BRAND_PRODUCT, self::SECOND_BRAND_PRODUCT],
            $this->matchingReferences([$this->brandIds['first'], $this->brandIds['second']]),
        );
    }

    public function testACheckedBrandRepeatedByTheQueryStringCountsOnce(): void
    {
        self::assertSame(
            [self::FIRST_BRAND_PRODUCT],
            $this->matchingReferences([$this->brandIds['first'], (string) $this->brandIds['first']]),
        );
    }

    /**
     * @param list<int|string> $selection the checked brand ids, as the query string carries them
     *
     * @return list<string> the references of the matching products, in creation order
     */
    private function matchingReferences(array $selection): array
    {
        // Restricted to the products this test created: the filter is what is under test,
        // not whatever else the database holds.
        $query = ProductQuery::create()->filterById(array_values($this->productIds), Criteria::IN);

        $filtered = $this->filterService->filterWithTFilter(
            tfilters: ['brand' => ['brand' => $selection]],
            resource: 'products',
            query: $query,
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
        $connection = $this->getPropelConnection();

        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        foreach (['first', 'second', 'third'] as $name) {
            $this->brandIds[$name] = (int) $factory->brand()->getId();
        }

        $catalogue = [
            self::FIRST_BRAND_PRODUCT => $this->brandIds['first'],
            self::SECOND_BRAND_PRODUCT => $this->brandIds['second'],
            self::THIRD_BRAND_PRODUCT => $this->brandIds['third'],
            self::UNBRANDED_PRODUCT => null,
        ];

        foreach ($catalogue as $reference => $brandId) {
            $product = $factory->product($category, $taxRule, $currency, ['ref' => $reference]);
            $product->setBrandId($brandId);
            $product->save($connection);
            $this->productIds[$reference] = (int) $product->getId();
        }
    }
}
