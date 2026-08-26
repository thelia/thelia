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
use Thelia\Api\Resource\FilterValue;
use Thelia\Model\ChoiceFilter;
use Thelia\Model\FeatureProduct;
use Thelia\Model\Product;
use Thelia\Model\Template;
use Thelia\Test\IntegrationTestCase;

final class TFiltersVisibleProductsTest extends IntegrationTestCase
{
    private FilterService $filterService;

    private int $categoryId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filterService = static::getContainer()->get(FilterService::class);
        $this->createCatalogue();
    }

    public function testWithoutVisibilityEveryProductFeedsTheFacets(): void
    {
        self::assertSame(
            [
                'brand/Brand' => ['Hidden brand', 'Shown brand'],
                'feature/Colour' => ['Blue', 'Red'],
            ],
            $this->facets([]),
        );
    }

    public function testVisibleProductsAloneFeedTheFacets(): void
    {
        self::assertSame(
            [
                'brand/Brand' => ['Shown brand'],
                'feature/Colour' => ['Blue'],
            ],
            $this->facets(['visible' => 'true']),
        );
    }

    public function testHiddenProductsAloneFeedTheFacetsWhenAsked(): void
    {
        self::assertSame(
            [
                'brand/Brand' => ['Hidden brand'],
                'feature/Colour' => ['Red'],
            ],
            $this->facets(['visible' => '0']),
        );
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array<string, list<string>>
     */
    private function facets(array $filters): array
    {
        $filterObjects = $this->filterService->getFilters(
            [
                'path_info' => '/api/front/products',
                'filters' => [
                    'tfilters' => ['category' => [['eq' => $this->categoryId]]],
                    'locale' => 'en_US',
                    ...$filters,
                ],
            ],
            'products',
        );

        $facets = [];

        /** @var Filter $filter */
        foreach ($filterObjects as $filter) {
            if ($filter->getType() === 'category') {
                continue;
            }

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

    private function createCatalogue(): void
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        // Without a template on the category the service offers no filter at all.
        $template = new Template();
        $template->setLocale('en_US');
        $template->setName('Filtered template');
        $template->save($connection);

        $category = $factory->category();
        $category->setDefaultTemplateId($template->getId());
        $category->save($connection);
        $this->categoryId = (int) $category->getId();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $colour = $factory->feature(['title' => 'Colour']);

        $choiceFilter = new ChoiceFilter();
        $choiceFilter->setCategoryId($category->getId());
        $choiceFilter->setTemplateId($template->getId());
        $choiceFilter->setFeatureId($colour->getId());
        $choiceFilter->setPosition(1);
        $choiceFilter->setVisible(true);
        $choiceFilter->setType('checkbox');
        $choiceFilter->save($connection);
        $blue = $factory->featureAv($colour, ['title' => 'Blue']);
        $red = $factory->featureAv($colour, ['title' => 'Red']);

        $shown = $factory->product($category, $taxRule, $currency, ['ref' => 'SHOWN', 'visible' => 1]);
        $shown->setBrandId($factory->brand(['title' => 'Shown brand'])->getId())->save($connection);
        $this->holdValue($shown, (int) $colour->getId(), (int) $blue->getId());

        $hidden = $factory->product($category, $taxRule, $currency, ['ref' => 'HIDDEN', 'visible' => 0]);
        $hidden->setBrandId($factory->brand(['title' => 'Hidden brand'])->getId())->save($connection);
        $this->holdValue($hidden, (int) $colour->getId(), (int) $red->getId());
    }

    private function holdValue(Product $product, int $featureId, int $featureAvId): void
    {
        $featureProduct = new FeatureProduct();
        $featureProduct->setProductId($product->getId());
        $featureProduct->setFeatureId($featureId);
        $featureProduct->setFeatureAvId($featureAvId);
        $featureProduct->save($this->getPropelConnection());
    }
}
