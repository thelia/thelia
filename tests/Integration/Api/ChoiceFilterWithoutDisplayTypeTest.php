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

use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Resource\Filter;
use Thelia\Model\Category;
use Thelia\Model\ChoiceFilter;
use Thelia\Model\Feature;
use Thelia\Model\FeatureProduct;
use Thelia\Model\Template;
use Thelia\Test\IntegrationTestCase;

/**
 * A shop upgraded from Thelia 2 carries choice_filter rows without a display type: the column
 * did not exist there. Such a row still rules the position and the visibility of its filter, and
 * the filter is offered the way a filter without any row is, as a checkbox list.
 */
final class ChoiceFilterWithoutDisplayTypeTest extends IntegrationTestCase
{
    private FilterService $filterService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filterService = static::getContainer()->get(FilterService::class);
    }

    public function testARowWithoutDisplayTypeOffersItsFilterAsACheckboxList(): void
    {
        [$category, $colour] = $this->categoryWithAFilteredFeature(displayType: null);

        $facet = $this->featureFacet($category, $colour);

        self::assertSame('checkbox', $facet->getFieldType());
        self::assertSame(3, $facet->getPosition());
    }

    public function testARowWithAnEmptyDisplayTypeOffersItsFilterAsACheckboxList(): void
    {
        [$category, $colour] = $this->categoryWithAFilteredFeature(displayType: '');

        self::assertSame('checkbox', $this->featureFacet($category, $colour)->getFieldType());
    }

    public function testARowWithADisplayTypeKeepsIt(): void
    {
        [$category, $colour] = $this->categoryWithAFilteredFeature(displayType: 'radio');

        self::assertSame('radio', $this->featureFacet($category, $colour)->getFieldType());
    }

    private function featureFacet(Category $category, Feature $feature): Filter
    {
        $facets = $this->filterService->getFilters(
            [
                'path_info' => '/api/front/products',
                'filters' => [
                    'tfilters' => ['category' => [['eq' => $category->getId()]]],
                    'locale' => 'en_US',
                ],
            ],
            'products',
        );

        foreach ($facets as $facet) {
            if ($facet->getType() === 'feature' && $facet->getId() === $feature->getId()) {
                return $facet;
            }
        }

        self::fail('The category does not offer its feature filter.');
    }

    /**
     * @return array{Category, Feature}
     */
    private function categoryWithAFilteredFeature(?string $displayType): array
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        $template = new Template();
        $template->setLocale('en_US');
        $template->setName('Upgraded template');
        $template->save($connection);

        $category = $factory->category();
        $category->setDefaultTemplateId($template->getId())->save($connection);

        $colour = $factory->feature(['title' => 'Colour']);
        $blue = $factory->featureAv($colour, ['title' => 'Blue']);

        $product = $factory->product($category, $factory->taxRule(), $factory->currency());

        $featureProduct = new FeatureProduct();
        $featureProduct->setProductId($product->getId());
        $featureProduct->setFeatureId($colour->getId());
        $featureProduct->setFeatureAvId($blue->getId());
        $featureProduct->save($connection);

        $row = new ChoiceFilter();
        $row->setCategoryId($category->getId());
        $row->setTemplateId($template->getId());
        $row->setFeatureId($colour->getId());
        $row->setPosition(3);
        $row->setVisible(true);
        $row->setType($displayType);
        $row->save($connection);

        return [$category, $colour];
    }
}
