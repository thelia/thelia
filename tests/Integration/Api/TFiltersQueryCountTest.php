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

use Propel\Runtime\Propel;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Resource\Filter;
use Thelia\Api\Resource\FilterValue;
use Thelia\Model\Attribute;
use Thelia\Model\AttributeCombination;
use Thelia\Model\Category;
use Thelia\Model\ChoiceFilter;
use Thelia\Model\Feature;
use Thelia\Model\FeatureProduct;
use Thelia\Model\Template;
use Thelia\Test\IntegrationTestCase;

/**
 * A facet list describes which values a result set holds, which the database can
 * answer in one aggregate per filter. Reading it record by record instead made the
 * endpoint cost grow with the catalogue — the shop that reported it waited minutes
 * on 15 000 products.
 *
 * The measurement that matters is therefore the number of queries, not the wall
 * time: a catalogue four times bigger must not cost a single query more.
 */
final class TFiltersQueryCountTest extends IntegrationTestCase
{
    private const SMALL_CATALOGUE = 2;
    private const LARGE_CATALOGUE = 8;

    private FilterService $filterService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterService = static::getContainer()->get(FilterService::class);
    }

    protected function tearDown(): void
    {
        $this->getPropelConnection()->useDebug(false);
        Propel::enableInstancePooling();

        parent::tearDown();
    }

    public function testTheFacetCostDoesNotFollowTheCatalogueSize(): void
    {
        // Instance pooling would make the second measurement cheaper than the first
        // for reasons that have nothing to do with what is being measured.
        Propel::disableInstancePooling();

        $small = $this->measure(self::SMALL_CATALOGUE);
        $large = $this->measure(self::LARGE_CATALOGUE);

        self::assertNotSame([], $small['facets'], 'The fixture must expose facets, otherwise nothing is measured.');
        self::assertSame($small['facets'], $large['facets'], 'Four times more products holding the same values must expose the same facets.');
        self::assertLessThanOrEqual(
            $small['queries'],
            $large['queries'],
            \sprintf(
                'The facet list cost %d queries for %d products and %d for %d: it still follows the catalogue size.',
                $small['queries'],
                self::SMALL_CATALOGUE,
                $large['queries'],
                self::LARGE_CATALOGUE,
            ),
        );

        // The remaining queries are bounded by the shop's taxonomy, not by its catalogue.
        self::assertLessThan(100, $large['queries']);
    }

    /**
     * @return array{queries: int, facets: array<string, array<string>>}
     */
    private function measure(int $productCount): array
    {
        $categoryId = $this->createCatalogue($productCount);

        $connection = $this->getPropelConnection();
        $connection->useDebug(true);

        $before = $connection->getQueryCount();
        $filters = $this->filterService->getFilters(
            [
                'path_info' => '/api/front/products',
                'filters' => [
                    // tfilters values are nested one level deeper than the filter name.
                    'tfilters' => ['category' => [['eq' => $categoryId]]],
                    'locale' => 'en_US',
                ],
            ],
            'products',
        );
        $queries = $connection->getQueryCount() - $before;

        return ['queries' => $queries, 'facets' => $this->describe($filters)];
    }

    /**
     * @param array<Filter> $filters
     *
     * @return array<string, array<string>>
     */
    private function describe(array $filters): array
    {
        $facets = [];

        foreach ($filters as $filter) {
            $titles = array_map(
                static fn (FilterValue $value): string => (string) $value->getTitle(),
                $filter->getValues(),
            );
            sort($titles);
            $facets[$filter->getType().'/'.$filter->getTitle()] = $titles;
        }

        ksort($facets);

        return $facets;
    }

    /**
     * Every product holds the same feature and attribute values, so the facets of a
     * two-product catalogue and of an eight-product one are identical.
     */
    private function createCatalogue(int $productCount): int
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        $template = new Template();
        $template->setLocale('en_US');
        $template->setName('Filtered template');
        $template->save($connection);

        $category = $factory->category();
        $category->setDefaultTemplateId($template->getId());
        $category->save($connection);

        $feature = $factory->feature(['title' => 'Colour']);
        $featureAv = $factory->featureAv($feature, ['title' => 'Blue']);

        $attribute = $factory->attribute(['title' => 'Size']);
        $attributeAv = $factory->attributeAv($attribute, ['title' => 'Large']);

        $this->declareChoiceFilter($category, $template, $feature, null);
        $this->declareChoiceFilter($category, $template, null, $attribute);

        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        for ($i = 0; $i < $productCount; ++$i) {
            $product = $factory->product($category, $taxRule, $currency);

            $featureProduct = new FeatureProduct();
            $featureProduct->setProductId($product->getId());
            $featureProduct->setFeatureId($feature->getId());
            $featureProduct->setFeatureAvId($featureAv->getId());
            $featureProduct->save($connection);

            $combination = new AttributeCombination();
            $combination->setProductSaleElementsId($product->getDefaultSaleElements()->getId());
            $combination->setAttributeId($attribute->getId());
            $combination->setAttributeAvId($attributeAv->getId());
            $combination->save($connection);
        }

        return $category->getId();
    }

    private function declareChoiceFilter(Category $category, Template $template, ?Feature $feature, ?Attribute $attribute): void
    {
        $choiceFilter = new ChoiceFilter();
        $choiceFilter->setCategoryId($category->getId());
        $choiceFilter->setTemplateId($template->getId());
        $choiceFilter->setFeatureId($feature?->getId());
        $choiceFilter->setAttributeId($attribute?->getId());
        $choiceFilter->setPosition(1);
        $choiceFilter->setVisible(true);
        $choiceFilter->setType('checkbox');
        $choiceFilter->save($this->getPropelConnection());
    }
}
