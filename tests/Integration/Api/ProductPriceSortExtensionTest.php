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
use Propel\Runtime\Util\PropelModelPager;
use Thelia\Api\Bridge\Propel\Extension\ProductPriceSortExtension;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\ProductFilter\ProductPriceSortRequests;
use Thelia\Api\Resource\Product;
use Thelia\Model\Currency;
use Thelia\Model\ProductQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The extension that ranks a product collection on what the visitor pays, called on queries
 * other code has already shaped.
 */
final class ProductPriceSortExtensionTest extends ActionIntegrationTestCase
{
    private const MODULE_RESOURCE = 'Acme\\Module\\Api\\Resource\\PricedThing';

    public function testItServesTheCollectionOfAResourceOtherThanProduct(): void
    {
        $this->products(['Z3A' => 30.0, 'Z3B' => 20.0, 'Z3C' => 10.0]);
        $extension = $this->getService(ProductPriceSortExtension::class);
        $query = $this->query();
        $this->getService(ProductPriceSortRequests::class)->request($query, 'ASC');

        self::assertTrue($extension->supportsResult(self::MODULE_RESOURCE));
        self::assertSame(['Z3C', 'Z3B', 'Z3A'], $this->refsOf($extension->getResult($query, self::MODULE_RESOURCE)));
    }

    public function testItLeavesTheCollectionAloneWhenNoPriceOrderWasAsked(): void
    {
        $this->products(['Z3A' => 10.0]);

        self::assertNull($this->getService(ProductPriceSortExtension::class)->getResult($this->query(), Product::class));
    }

    public function testAnOrderOnAVirtualColumnOfTheQuerySurvivesTheRanking(): void
    {
        $this->products(['Z3A' => 10.0, 'Z3B' => 10.0, 'Z3C' => 10.0]);
        $extension = $this->getService(ProductPriceSortExtension::class);
        // A module orders its collection on a column it computed: it must still exist in the
        // query that reads the ids to rank.
        $query = $this->query()->withColumn('Product.Ref', 'ref_alias')->orderBy('ref_alias', Criteria::DESC);
        $this->getService(ProductPriceSortRequests::class)->request($query, 'ASC');

        // The three of them cost the same: the order of the query settles them.
        self::assertSame(['Z3C', 'Z3B', 'Z3A'], $this->refsOf($extension->getResult($query, Product::class)));
    }

    private function query(): ProductQuery
    {
        return ProductQuery::create()->filterByRef(['Z3A', 'Z3B', 'Z3C'], Criteria::IN);
    }

    /**
     * @param array<string, float> $prices untaxed price by reference
     */
    private function products(array $prices): void
    {
        $category = $this->factory->category();
        $taxRule = $this->factory->taxRule();

        foreach ($prices as $ref => $price) {
            $this->factory->product($category, $taxRule, Currency::getDefaultCurrency(), ['ref' => $ref, 'basePrice' => $price, 'baseQuantity' => 10]);
        }
    }

    /**
     * @return list<string>
     */
    private function refsOf(mixed $result): array
    {
        self::assertIsObject($result);

        $products = $result instanceof PropelModelPager ? $result->getResults() : $result;

        return array_values(array_map(static fn (object $product): string => $product->getRef(), iterator_to_array($products, false)));
    }
}
