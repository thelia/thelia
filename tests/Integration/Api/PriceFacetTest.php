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
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\PriceFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Resource\Filter;
use Thelia\Api\Resource\FilterValue;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Category;
use Thelia\Model\Country;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleCountry;
use Thelia\Model\Template;
use Thelia\Test\IntegrationTestCase;

/**
 * The price facet of a listing, drawn on the price the product card shows: default
 * combination, promotional price when on sale, taxes of the delivery country included.
 *
 *   CHEAP          10.00 untaxed, 20 % VAT     12.00
 *   ON-SALE        50.00, on sale at 40.00     48.00
 *   DEAR          100.00                      120.00
 *   UNTAXED        30.00, no tax rule          30.00
 *   NO-COMBINATION every combination deleted   no price
 */
final class PriceFacetTest extends IntegrationTestCase
{
    private FilterService $filterService;

    private Category $category;

    /** @var array<string, int> product reference => id, in creation order */
    private array $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->filterService = static::getContainer()->get(FilterService::class);
        $this->getService(PriceFilter::class)->reset();
        $this->createCatalogue();
    }

    protected function tearDown(): void
    {
        $this->getService(PriceFilter::class)->reset();

        parent::tearDown();
    }

    public function testTheBoundsAreTheCheapestAndTheDearestPriceShownRoundedOutwards(): void
    {
        $facet = $this->priceFacet($this->facets([]));

        self::assertNotNull($facet);
        self::assertSame('delta', $facet->getFieldType(), 'A price facet is drawn as a slider unless the merchant chose otherwise.');
        self::assertSame(['12', '120'], $this->bounds($facet));
    }

    public function testAnIntervalKeepsTheProductsWhosePriceShownFallsInIt(): void
    {
        // 48 is the promotional price taxed; 50 untaxed or 60 taxed would fall outside.
        self::assertSame(['ON-SALE', 'UNTAXED'], $this->matching(['price' => ['price' => ['min' => '20', 'max' => '50']]]));
        self::assertSame(['CHEAP', 'ON-SALE', 'DEAR', 'UNTAXED'], $this->matching(['price' => ['price' => ['min' => '12', 'max' => '120']]]));
        self::assertSame(['DEAR'], $this->matching(['price' => ['price' => ['min' => '100']]]));
    }

    public function testASingleHandleSliderReadsAsAMaximum(): void
    {
        self::assertSame(['CHEAP', 'UNTAXED'], $this->matching(['price' => ['price' => '30']]));
    }

    public function testWithoutABoundTheListingIsLeftWhole(): void
    {
        self::assertSame(array_keys($this->productIds), $this->matching(['price' => ['price' => ['min' => '', 'max' => '']]]));
    }

    public function testACheckedIntervalKeepsTheWholeSliderOnOffer(): void
    {
        $facet = $this->priceFacet($this->facets(['price' => ['price' => ['min' => '20', 'max' => '50']]]));

        self::assertNotNull($facet);
        self::assertSame(['12', '120'], $this->bounds($facet));
    }

    public function testTheIntervalLeavesTheOrderOfTheListingAlone(): void
    {
        $query = ProductQuery::create()
            ->filterById(array_values($this->productIds), Criteria::IN)
            ->orderByRef(Criteria::DESC);

        $filtered = $this->filterService->filterWithTFilter(
            tfilters: ['price' => ['price' => ['min' => '0', 'max' => '200']]],
            resource: 'products',
            query: $query,
        );

        self::assertSame(
            ['UNTAXED', 'ON-SALE', 'DEAR', 'CHEAP'],
            array_map(static fn (Product $product): string => $product->getRef(), iterator_to_array($filtered->find($this->getPropelConnection()))),
        );
    }

    public function testThePricesFollowTheVisitorsCurrency(): void
    {
        $dollar = $this->createFixtureFactory()->currency(['code' => 'USD', 'symbol' => '$', 'rate' => 2.0]);
        // The facet reads the session of the main request, the one a sub-request shares; the
        // kernel, and that request with it, is booted anew for every test.
        $session = $this->getService(RequestStack::class)->getMainRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);
        $session->setCurrency($dollar);

        $facet = $this->priceFacet($this->facets([]));

        // No price typed in dollars: the euro prices are converted at the rate, as on the card.
        self::assertNotNull($facet);
        self::assertSame(['24', '240'], $this->bounds($facet));
    }

    public function testAProductPricedByACatalogPriceRuleIsJudgedOnThatPrice(): void
    {
        $factory = $this->createFixtureFactory();
        $rule = $factory->catalogPriceRule(['active' => true, 'percentageValue' => 50.0]);
        $factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $this->productIds['DEAR']);
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        // DEAR is shown at 50.00 untaxed, 60.00 taxed, instead of 120.00.
        $facet = $this->priceFacet($this->facets([]));

        self::assertNotNull($facet);
        self::assertSame(['12', '60'], $this->bounds($facet));
        self::assertSame(['DEAR'], $this->matching(['price' => ['price' => ['min' => '55', 'max' => '65']]]));
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
     */
    private function priceFacet(array $facets): ?Filter
    {
        foreach ($facets as $facet) {
            if ($facet->getType() === 'price') {
                return $facet;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function bounds(Filter $facet): array
    {
        return array_map(static fn (FilterValue $value): string => $value->getTitle(), $facet->getValues());
    }

    /**
     * @param array<string, mixed> $tfilters
     *
     * @return list<string> the references of the matching products, in creation order
     */
    private function matching(array $tfilters): array
    {
        $query = ProductQuery::create()->filterById(array_values($this->productIds), Criteria::IN);
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
        $template->setName('Price template');
        $template->save($connection);

        $this->category = $factory->category();
        $this->category->setDefaultTemplateId($template->getId())->save($connection);

        // A non-empty override array forces a rule of its own instead of reusing the seeded one.
        $taxRule = $factory->taxRule(['isDefault' => false]);
        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId(Country::getDefaultCountry()->getId())
            ->setTaxId($factory->tax(['requirements' => ['percent' => '20']])->getId())
            ->setPosition(1)
            ->save($connection);
        $currency = $factory->currency();

        $create = function (string $ref, float $price) use ($factory, $taxRule, $currency): Product {
            $product = $factory->product($this->category, $taxRule, $currency, ['ref' => $ref, 'basePrice' => $price]);
            $this->productIds[$ref] = (int) $product->getId();

            return $product;
        };

        $create('CHEAP', 10.0);
        $onSale = $create('ON-SALE', 50.0);
        $create('DEAR', 100.0);
        $untaxed = $create('UNTAXED', 30.0);
        $noCombination = $create('NO-COMBINATION', 70.0);

        $saleElement = ProductSaleElementsQuery::create()->filterByProductId($onSale->getId())->filterByIsDefault(true)->findOne($connection);
        $saleElement->setPromo(1)->save($connection);
        ProductPriceQuery::create()
            ->filterByProductSaleElementsId($saleElement->getId())
            ->findOne($connection)
            ->setPromoPrice('40.000000')
            ->save($connection);

        $untaxed->setTaxRuleId(null)->save($connection);
        ProductSaleElementsQuery::create()->filterByProductId($noCombination->getId())->delete($connection);
    }
}
