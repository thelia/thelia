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

namespace Thelia\Tests\Integration\Api\PriceVisibility;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\PriceFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Resource\Filter;
use Thelia\Api\Resource\FilterValue;
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\Template;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Tests\Integration\Domain\Pricing\PriceVisibility\PriceDisplayFixtures;
use Thelia\Tests\Support\Trait\ResetsPriceMemos;

/**
 * The price slider of a listing and the interval a buyer draws on it never tell a price the
 * visitor is not shown: a product with a hidden price matches no interval and draws no bound.
 *
 *   CHEAP 60, MID 80, DEAR 100 untaxed, 20 % VAT: the slider is drawn on 72, 96 and 120
 *
 *   shop visible       every visitor is drawn 72..120
 *   DEAR on request    nobody is drawn DEAR: 72..96, and 120 matches no interval
 *   shop logged_in     a visitor is drawn nothing; a customer 72..120
 */
final class PriceFacetPriceDisplayTest extends IntegrationTestCase
{
    use LogsInAsCustomer;
    use PriceDisplayFixtures;
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
        $this->forgetPriceDisplayMemos();
        $this->resetPriceMemos();

        $template = new Template();
        $template->setLocale('en_US');
        $template->setName('Price display template');
        $template->save($this->getPropelConnection());

        $this->category = $this->factory->category();
        $this->category->setDefaultTemplateId($template->getId())->save($this->getPropelConnection());
        $taxRule = $this->factory->taxRule();

        foreach (['CHEAP' => 60.0, 'MID' => 80.0, 'DEAR' => 100.0] as $ref => $price) {
            $this->products[$ref] = $this->factory->product($this->category, $taxRule, Currency::getDefaultCurrency(), ['ref' => $ref, 'basePrice' => $price]);
        }
    }

    protected function tearDown(): void
    {
        $this->forgetPriceDisplayMemos();
        $this->resetPriceMemos();

        parent::tearDown();
    }

    public function testAVisitorIsDrawnTheSliderWhileEveryPriceIsVisible(): void
    {
        self::assertSame(['72', '120'], $this->bounds());
        self::assertSame(['MID'], $this->matching(['price' => ['price' => ['min' => '90', 'max' => '100']]]));
    }

    public function testAProductWhosePriceIsHiddenMatchesNoIntervalAndDrawsNoBound(): void
    {
        $this->productShows($this->products['DEAR'], PriceDisplayMode::OnRequest);
        $this->resetPriceMemos();

        self::assertSame(['72', '96'], $this->bounds(), 'the slider stops at the dearest price that is shown');
        self::assertSame([], $this->matching(['price' => ['price' => ['min' => '110', 'max' => '130']]]), 'an interval that holds only the hidden price is empty');
        self::assertSame(['MID'], $this->matching(['price' => ['price' => ['min' => '90', 'max' => '130']]]), 'and does not hold the hidden product');
    }

    public function testAVisitorIsDrawnNothingWhereTheShopShowsItsPricesToCustomersOnly(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $this->resetPriceMemos();

        self::assertSame([], $this->bounds());
        self::assertSame([], $this->matching(['price' => ['price' => ['min' => '50', 'max' => '200']]]));
    }

    public function testACustomerIsDrawnTheSliderWhereTheShopShowsItsPricesToCustomersOnly(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $this->loginAsCustomerInSession($this->factory->customer($this->factory->customerTitle()));
        $this->resetPriceMemos();

        self::assertSame(['72', '120'], $this->bounds());
        self::assertSame(['MID'], $this->matching(['price' => ['price' => ['min' => '90', 'max' => '100']]]));
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
     * @param array<string, mixed> $tfilters
     *
     * @return list<string>
     */
    private function matching(array $tfilters): array
    {
        $this->getService(PriceFilter::class)->reset();
        $ids = array_map(static fn (Product $product): int => (int) $product->getId(), $this->products);
        $query = ProductQuery::create()->filterById(array_values($ids), Criteria::IN);
        $filtered = $this->filterService->filterWithTFilter(tfilters: $tfilters, resource: 'products', query: $query);

        return array_values(array_map(
            static fn (Product $product): string => $product->getRef(),
            iterator_to_array($filtered->find($this->getPropelConnection())),
        ));
    }
}
