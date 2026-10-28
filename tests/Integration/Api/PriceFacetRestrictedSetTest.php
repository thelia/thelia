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
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\PriceFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Resource\Filter;
use Thelia\Api\Resource\FilterValue;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\Template;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Support\Trait\ResetsPriceMemos;

/**
 * The price facet is drawn, and narrows the listing, on what a member pays for the products of
 * the listing, and only of those.
 *
 *   A  85, B  90 promotional 80, C 120 (60 for the group): in the category
 *   OTHER 10: in another one
 *
 *   M, of a group with 15 % off shown untaxed prices, with a discount of 10 %:
 *   C 54, A 65.03, B 68.85 (the promotion of B is dearer than the discounted base).
 */
final class PriceFacetRestrictedSetTest extends IntegrationTestCase
{
    use LogsInAsCustomer;
    use RecordsSqlQueries;
    use ResetsPriceMemos;

    private FilterService $filterService;

    private FixtureFactory $factory;

    private Category $category;

    /** @var array<string, Product> */
    private array $products = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->filterService = static::getContainer()->get(FilterService::class);
        $this->resetPriceMemos();

        $currency = Currency::getDefaultCurrency();
        $taxRule = $this->factory->taxRule();
        $template = new Template();
        $template->setLocale('en_US');
        $template->setName('Price template');
        $template->save($this->getPropelConnection());

        $this->category = $this->factory->category();
        $this->category->setDefaultTemplateId($template->getId())->save($this->getPropelConnection());
        $otherCategory = $this->factory->category();

        foreach (['A' => 85.0, 'B' => 90.0, 'C' => 120.0] as $ref => $price) {
            $this->products[$ref] = $this->factory->product($this->category, $taxRule, $currency, ['ref' => $ref, 'basePrice' => $price]);
        }

        $this->products['OTHER'] = $this->factory->product($otherCategory, $taxRule, $currency, ['ref' => 'OTHER', 'basePrice' => 10.0]);

        $saleElements = $this->products['B']->getDefaultSaleElements();
        $saleElements->setPromo(1)->save();
        ProductPriceQuery::create()->filterByProductSaleElementsId($saleElements->getId())->findOne()->setPromoPrice('80')->save();

        $group = $this->factory->customerGroup(discount: 15.0, displayTaxedPrice: false);
        $this->factory->customerGroupProductPrice($group, $this->products['C']->getDefaultSaleElements(), $currency, 60.0);
        $member = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $member->setDiscount('10')->save();
        $this->loginAsCustomerInSession($member);
    }

    protected function tearDown(): void
    {
        $this->resetPriceMemos();

        parent::tearDown();
    }

    public function testTheSliderOfTheMemberIsDrawnOnTheirPrices(): void
    {
        self::assertSame(['54', '69'], $this->bounds());
    }

    public function testTheMemberIsShownWhatTheyPayUnderTheirCeiling(): void
    {
        self::assertSame(['C', 'A'], $this->recordedMatching());
    }

    public function testAListingOrderedOnAVirtualColumnIsStillNarrowed(): void
    {
        $this->getService(PriceFilter::class)->reset();
        $listing = array_map(static fn (Product $product): int => (int) $product->getId(), [$this->products['A'], $this->products['B'], $this->products['C']]);

        // A module orders its listing on a column it computed: the query that reads the ids
        // to price must not carry that order without the column.
        $filtered = $this->filterService->filterWithTFilter(
            tfilters: ['price' => ['price' => ['max' => '66']]],
            resource: 'products',
            query: ProductQuery::create()->filterById($listing, Criteria::IN)->withColumn('Product.Ref', 'ref_alias')->orderBy('ref_alias', Criteria::DESC),
        );

        self::assertSame(['C', 'A'], array_values(array_map(
            static fn (Product $product): string => $product->getRef(),
            iterator_to_array($filtered->find($this->getPropelConnection())),
        )));
    }

    public function testOnlyTheProductsOfTheListingArePriced(): void
    {
        $statements = [];

        self::assertSame(['C', 'A'], $this->recordedMatching($statements));

        $pricedSaleElementIds = [];

        foreach ($statements as $sql) {
            if (str_contains($sql, 'customer_group_product_price') && 1 === preg_match_all('/product_sale_elements_id IN \(([\d, ]+)\)/', $sql, $lists)) {
                foreach ($lists[1] as $list) {
                    array_push($pricedSaleElementIds, ...array_map('intval', explode(',', $list)));
                }
            }
        }

        self::assertContains((int) $this->products['A']->getDefaultSaleElements()->getId(), $pricedSaleElementIds, implode("\n", $statements));
        self::assertNotContains((int) $this->products['OTHER']->getDefaultSaleElements()->getId(), $pricedSaleElementIds, 'The product of another category is not priced.');
    }

    /**
     * @return list<string>
     */
    private function bounds(): array
    {
        $facets = $this->filterService->getFilters(
            [
                'path_info' => '/api/front/products',
                'filters' => [
                    'tfilters' => ['category' => [['eq' => $this->category->getId()]]],
                    'locale' => 'en_US',
                ],
            ],
            'products',
        );

        foreach ($facets as $facet) {
            if ($facet instanceof Filter && 'price' === $facet->getType()) {
                return array_map(static fn (FilterValue $value): string => $value->getTitle(), $facet->getValues());
            }
        }

        return [];
    }

    /**
     * What the price facet keeps of the listing (the three products of the category), up to
     * 66, the cheapest first for the member.
     *
     * @param list<string> $statements the statements the filtering ran
     *
     * @return list<string>
     */
    private function recordedMatching(array &$statements = []): array
    {
        $this->getService(PriceFilter::class)->reset();
        $listing = array_map(static fn (Product $product): int => (int) $product->getId(), [$this->products['A'], $this->products['B'], $this->products['C']]);
        $refs = [];

        $statements = $this->recordSqlQueries(function () use ($listing, &$refs): void {
            $filtered = $this->filterService->filterWithTFilter(
                tfilters: ['price' => ['price' => ['max' => '66']]],
                resource: 'products',
                query: ProductQuery::create()->filterById($listing, Criteria::IN),
            );

            $refs = array_values(array_map(
                static fn (Product $product): string => $product->getRef(),
                iterator_to_array($filtered->find($this->getPropelConnection())),
            ));
        });

        // The members the member pays least for first: C 54, A 65.03.
        usort($refs, static fn (string $a, string $b): int => ['C' => 0, 'A' => 1][$a] <=> ['C' => 0, 'A' => 1][$b]);

        return $refs;
    }
}
