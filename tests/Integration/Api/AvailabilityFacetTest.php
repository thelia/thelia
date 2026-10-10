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
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\AvailabilityFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Resource\Filter;
use Thelia\Api\Resource\FilterValue;
use Thelia\Model\Category;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Template;
use Thelia\Test\IntegrationTestCase;

/**
 * The availability facet of a listing: in stock, or on order and out of stock.
 *
 *   STOCKED       default combination, 5 in stock                      in stock
 *   ONE-SIZE-LEFT default combination at 0, a second one with 3        in stock
 *   HIDDEN-STOCK  default combination at 0, a hidden one with 9        out
 *   SOLD-OUT      default combination at 0                             out
 *   DOWNLOAD      virtual, default combination at 0                    in stock
 *   NO-COMBINATION every combination deleted                           out
 */
final class AvailabilityFacetTest extends IntegrationTestCase
{
    private FilterService $filterService;

    private Category $category;

    /** @var array<string, int> product reference => id, in creation order */
    private array $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->filterService = static::getContainer()->get(FilterService::class);
        $this->createCatalogue();
    }

    public function testEachValueCountsTheProductsItWouldKeep(): void
    {
        self::assertSame(
            ['In stock' => 3, 'On order or out of stock' => 3],
            $this->counts($this->facets([])),
        );
    }

    public function testInStockKeepsVirtualProductsAndProductsWithOneVisibleCombinationInStock(): void
    {
        self::assertSame(
            ['STOCKED', 'ONE-SIZE-LEFT', 'DOWNLOAD'],
            $this->matching([AvailabilityFilter::IN_STOCK]),
        );
    }

    public function testOutOfStockKeepsTheRestIncludingAProductWithoutAnyCombination(): void
    {
        self::assertSame(
            ['HIDDEN-STOCK', 'SOLD-OUT', 'NO-COMBINATION'],
            $this->matching([AvailabilityFilter::OUT_OF_STOCK]),
        );
    }

    public function testBothValuesOrNoValueLeaveTheListingWhole(): void
    {
        $everything = array_keys($this->productIds);

        self::assertSame($everything, $this->matching([AvailabilityFilter::IN_STOCK, AvailabilityFilter::OUT_OF_STOCK]));
        self::assertSame($everything, $this->matching([]));
        self::assertSame($everything, $this->matching([99]), 'A forged value must not narrow the listing.');
    }

    public function testACheckedValueKeepsItsSiblingOnOffer(): void
    {
        $facets = $this->facets(['availability' => ['availability' => [AvailabilityFilter::IN_STOCK]]]);

        self::assertSame(
            ['In stock' => 3, 'On order or out of stock' => 3],
            $this->counts($facets),
        );
    }

    public function testWithoutStockControlEveryProductIsInStock(): void
    {
        ConfigQuery::write('check-available-stock', '0');

        self::assertSame(['In stock' => 6], $this->counts($this->facets([])));
        self::assertSame(array_keys($this->productIds), $this->matching([AvailabilityFilter::IN_STOCK]));
        self::assertSame([], $this->matching([AvailabilityFilter::OUT_OF_STOCK]));
    }

    public function testAListingOutsideAnyCategoryOffersNoAvailabilityFacet(): void
    {
        $facets = $this->filterService->getFilters(
            ['path_info' => '/api/front/products', 'filters' => ['tfilters' => [], 'locale' => 'en_US']],
            'products',
        );

        self::assertSame([], $this->counts($facets));
    }

    /**
     * @param array<string, mixed> $tfilters
     *
     * @return array<Filter>
     */
    private function facets(array $tfilters): array
    {
        return $this->filterService->getFilters(
            [
                'path_info' => '/api/front/products',
                'filters' => [
                    'tfilters' => ['category' => [['eq' => $this->category->getId()]]] + $tfilters,
                    'locale' => 'en_US',
                ],
            ],
            'products',
        );
    }

    /**
     * @param array<Filter> $facets
     *
     * @return array<string, int>
     */
    private function counts(array $facets): array
    {
        foreach ($facets as $facet) {
            if ($facet->getType() !== 'availability') {
                continue;
            }

            $counts = [];

            /** @var FilterValue $value */
            foreach ($facet->getValues() as $value) {
                $counts[$value->getTitle()] = $value->getCount();
            }

            return $counts;
        }

        return [];
    }

    /**
     * @param list<int> $values
     *
     * @return list<string> the references of the matching products, in creation order
     */
    private function matching(array $values): array
    {
        $query = ProductQuery::create()->filterById(array_values($this->productIds), Criteria::IN);
        $tfilters = $values === [] ? [] : ['availability' => ['availability' => $values]];
        $filtered = $this->filterService->filterWithTFilter(tfilters: $tfilters, resource: 'products', query: $query);

        $matched = array_map(
            static fn (Product $product): int => (int) $product->getId(),
            iterator_to_array($filtered->find($this->getPropelConnection())),
        );

        return array_keys(array_filter(
            $this->productIds,
            static fn (int $id): bool => \in_array($id, $matched, true),
        ));
    }

    private function createCatalogue(): void
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        $template = new Template();
        $template->setLocale('en_US');
        $template->setName('Availability template');
        $template->save($connection);

        $this->category = $factory->category();
        $this->category->setDefaultTemplateId($template->getId())->save($connection);

        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $create = function (string $ref, int $quantity, bool $virtual = false) use ($factory, $taxRule, $currency, $connection): Product {
            $product = $factory->product($this->category, $taxRule, $currency, ['ref' => $ref, 'baseQuantity' => $quantity]);

            if ($virtual) {
                $product->setVirtual(1)->save($connection);
            }

            $this->productIds[$ref] = (int) $product->getId();

            return $product;
        };

        $create('STOCKED', 5);
        $oneSizeLeft = $create('ONE-SIZE-LEFT', 0);
        $factory->productSaleElement($oneSizeLeft, ['quantity' => 3])->setVisible(1)->save($connection);
        $hiddenStock = $create('HIDDEN-STOCK', 0);
        $factory->productSaleElement($hiddenStock, ['quantity' => 9])->setVisible(0)->save($connection);
        $create('SOLD-OUT', 0);
        $create('DOWNLOAD', 0, virtual: true);
        $noCombination = $create('NO-COMBINATION', 0);
        ProductSaleElementsQuery::create()->filterByProductId($noCombination->getId())->delete($connection);
    }
}
