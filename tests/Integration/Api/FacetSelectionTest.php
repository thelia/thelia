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
use Thelia\Api\Resource\Filter;
use Thelia\Api\Resource\FilterValue;
use Thelia\Model\AttributeCombination;
use Thelia\Model\FeatureProduct;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\Template;
use Thelia\Test\IntegrationTestCase;

/**
 * The facet column of a listing: every value tells how many products it would keep, and a
 * value that is checked keeps its siblings on offer. Checking "Acme" must not make the other
 * brands vanish, and must not hide the "Acme" count either.
 *
 * Catalogue: brands Acme and Bolt; feature Colour (Blue, Red); attribute Size (S, M).
 *
 *   ACME-BLUE-S   Acme, Blue, one sale element S + Slim
 *   ACME-RED-M    Acme, Red,  one sale element M
 *   BOLT-BLUE-SM  Bolt, Blue, one sale element S and another M + Slim
 *   BOLT-RED-S    Bolt, Red,  one sale element S
 */
final class FacetSelectionTest extends IntegrationTestCase
{
    private FilterService $filterService;

    private int $categoryId = 0;

    /** @var array<string, int> */
    private array $ids = [];

    /** @var array<string, int> product reference => id, in creation order */
    private array $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->filterService = static::getContainer()->get(FilterService::class);
        $this->createCatalogue();
    }

    public function testEveryValueCountsTheProductsItWouldKeep(): void
    {
        $facets = $this->facets([]);

        self::assertSame(['Acme' => 2, 'Bolt' => 2], $this->counts($facets, 'brand'));
        self::assertSame(['Blue' => 2, 'Red' => 2], $this->counts($facets, 'feature', $this->ids['colour']));
        self::assertSame(['S' => 3, 'M' => 2], $this->counts($facets, 'attribute', $this->ids['size']));
    }

    public function testACheckedBrandKeepsTheOtherBrandsOnOfferAndNarrowsTheRest(): void
    {
        $facets = $this->facets(['brand' => ['brand' => [$this->ids['acme']]]]);

        // The brand facet is read from the set relaxed of the brand selection: both brands, full counts.
        self::assertSame(['Acme' => 2, 'Bolt' => 2], $this->counts($facets, 'brand'));
        // Every other facet follows the two Acme products.
        self::assertSame(['Blue' => 1, 'Red' => 1], $this->counts($facets, 'feature', $this->ids['colour']));
        self::assertSame(['S' => 1, 'M' => 1], $this->counts($facets, 'attribute', $this->ids['size']));
    }

    public function testACheckedFeatureValueKeepsItsSiblingsAndNarrowsTheOtherFilters(): void
    {
        $facets = $this->facets(['feature' => [$this->ids['colour'] => [$this->ids['blue']]]]);

        self::assertSame(['Blue' => 2, 'Red' => 2], $this->counts($facets, 'feature', $this->ids['colour']));
        self::assertSame(['Acme' => 1, 'Bolt' => 1], $this->counts($facets, 'brand'));
        // Blue products: ACME-BLUE-S (S) and BOLT-BLUE-SM (S and M).
        self::assertSame(['S' => 2, 'M' => 1], $this->counts($facets, 'attribute', $this->ids['size']));
    }

    public function testASelectionThatMatchesNothingStillOffersTheCheckedFilterToUndo(): void
    {
        // Bolt + Red + M matches nothing: BOLT-RED-S only comes in S.
        $facets = $this->facets([
            'brand' => ['brand' => [$this->ids['bolt']]],
            'feature' => [$this->ids['colour'] => [$this->ids['red']]],
            'attribute' => [$this->ids['size'] => [$this->ids['m']]],
        ]);

        // Relaxed of the brand: Red + M products = ACME-RED-M, whose brand is Acme.
        self::assertSame(['Acme' => 1], $this->counts($facets, 'brand'));
        // Relaxed of the colour: Bolt + M = BOLT-BLUE-SM, which is Blue.
        self::assertSame(['Blue' => 1], $this->counts($facets, 'feature', $this->ids['colour']));
        // Relaxed of the size: Bolt + Red = BOLT-RED-S, which is S.
        self::assertSame(['S' => 1], $this->counts($facets, 'attribute', $this->ids['size']));
    }

    public function testTwoValuesOfOneAttributeWidenAndTwoAttributesNarrowOnASingleSaleElement(): void
    {
        self::assertSame(
            ['ACME-BLUE-S', 'ACME-RED-M', 'BOLT-BLUE-SM', 'BOLT-RED-S'],
            $this->matching(['attribute' => [$this->ids['size'] => [$this->ids['s'], $this->ids['m']]]]),
        );
        self::assertSame(
            ['ACME-RED-M', 'BOLT-BLUE-SM'],
            $this->matching(['attribute' => [$this->ids['size'] => [$this->ids['m']]]]),
        );
        // S and Slim must sit on the same sale element: BOLT-BLUE-SM has S on one and Slim on
        // the other, so it is not a match.
        self::assertSame(
            ['ACME-BLUE-S'],
            $this->matching(['attribute' => [
                $this->ids['size'] => [$this->ids['s']],
                $this->ids['fit'] => [$this->ids['slim']],
            ]]),
        );
    }

    public function testACheckedFeatureAndABoundedOneAreReadEachInItsOwnMode(): void
    {
        // Colour checked to Blue, Weight (numeric titles 100 / 300) bounded to at least 200.
        self::assertSame(
            ['BOLT-BLUE-SM'],
            $this->matching([
                'feature' => [
                    $this->ids['colour'] => [$this->ids['blue']],
                    $this->ids['weight'] => ['min' => 200],
                ],
            ]),
        );
        self::assertSame(
            ['ACME-BLUE-S', 'ACME-RED-M'],
            $this->matching(['feature' => [$this->ids['weight'] => ['max' => 150]]]),
        );
    }

    /**
     * @return array<Filter>
     */
    private function facets(array $tfilters): array
    {
        return $this->filterService->getFilters(
            [
                'path_info' => '/api/front/products',
                'filters' => [
                    'tfilters' => ['category' => [['eq' => $this->categoryId]]] + $tfilters,
                    'locale' => 'en_US',
                ],
            ],
            'products',
        );
    }

    /**
     * @param array<Filter> $facets
     *
     * @return array<string, int> value title => count, in the order the facet offers them
     */
    private function counts(array $facets, string $type, ?int $mainId = null): array
    {
        foreach ($facets as $facet) {
            if ($facet->getType() !== $type || ($mainId !== null && $facet->getId() !== $mainId)) {
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
     * @return list<string> the references of the matching products, in creation order
     */
    private function matching(array $tfilters): array
    {
        $query = ProductQuery::create()->filterById(array_values($this->productIds), Criteria::IN);
        $filtered = $this->filterService->filterWithTFilter(tfilters: $tfilters, resource: 'products', query: $query);

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
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        $template = new Template();
        $template->setLocale('en_US');
        $template->setName('Faceted template');
        $template->save($connection);

        $category = $factory->category();
        $category->setDefaultTemplateId($template->getId());
        $category->save($connection);
        $this->categoryId = (int) $category->getId();

        $acme = $factory->brand(['title' => 'Acme']);
        $bolt = $factory->brand(['title' => 'Bolt']);
        $colour = $factory->feature(['title' => 'Colour']);
        $blue = $factory->featureAv($colour, ['title' => 'Blue']);
        $red = $factory->featureAv($colour, ['title' => 'Red']);
        $weight = $factory->feature(['title' => 'Weight']);
        $light = $factory->featureAv($weight, ['title' => '100']);
        $heavy = $factory->featureAv($weight, ['title' => '300']);
        $size = $factory->attribute(['title' => 'Size']);
        $small = $factory->attributeAv($size, ['title' => 'S']);
        $medium = $factory->attributeAv($size, ['title' => 'M']);
        $fit = $factory->attribute(['title' => 'Fit']);
        $slim = $factory->attributeAv($fit, ['title' => 'Slim']);

        $this->ids = [
            'acme' => (int) $acme->getId(), 'bolt' => (int) $bolt->getId(),
            'colour' => (int) $colour->getId(), 'blue' => (int) $blue->getId(), 'red' => (int) $red->getId(),
            'weight' => (int) $weight->getId(),
            'size' => (int) $size->getId(), 's' => (int) $small->getId(), 'm' => (int) $medium->getId(),
            'fit' => (int) $fit->getId(), 'slim' => (int) $slim->getId(),
        ];

        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $catalogue = [
            'ACME-BLUE-S' => [$acme, $blue, $light, [[[$size, $small], [$fit, $slim]]]],
            'ACME-RED-M' => [$acme, $red, $light, [[[$size, $medium]]]],
            'BOLT-BLUE-SM' => [$bolt, $blue, $heavy, [[[$size, $small]], [[$size, $medium], [$fit, $slim]]]],
            'BOLT-RED-S' => [$bolt, $red, $heavy, [[[$size, $small]]]],
        ];

        foreach ($catalogue as $reference => [$brand, $colourValue, $weightValue, $saleElements]) {
            $product = $factory->product($category, $taxRule, $currency, ['ref' => $reference]);
            $product->setBrandId($brand->getId());
            $product->save($connection);
            $this->productIds[$reference] = (int) $product->getId();

            foreach ([[$colour, $colourValue], [$weight, $weightValue]] as [$feature, $featureAv]) {
                $featureProduct = new FeatureProduct();
                $featureProduct->setProductId($product->getId());
                $featureProduct->setFeatureId($feature->getId());
                $featureProduct->setFeatureAvId($featureAv->getId());
                $featureProduct->save($connection);
            }

            foreach ($saleElements as $index => $combinations) {
                $saleElement = $index === 0 ? $product->getDefaultSaleElements() : $this->extraSaleElement($product, $reference.'-'.$index);

                foreach ($combinations as [$attribute, $attributeAv]) {
                    $combination = new AttributeCombination();
                    $combination->setProductSaleElementsId($saleElement->getId());
                    $combination->setAttributeId($attribute->getId());
                    $combination->setAttributeAvId($attributeAv->getId());
                    $combination->save($connection);
                }
            }
        }
    }

    private function extraSaleElement(Product $product, string $reference): ProductSaleElements
    {
        $saleElement = new ProductSaleElements();
        $saleElement->setProductId($product->getId());
        $saleElement->setRef($reference);
        $saleElement->setQuantity(1);
        $saleElement->setIsDefault(false);
        $saleElement->save($this->getPropelConnection());

        return $saleElement;
    }
}
