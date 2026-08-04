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
use Thelia\Model\ChoiceFilter;
use Thelia\Model\ChoiceFilterOther;
use Thelia\Model\Template;
use Thelia\Test\IntegrationTestCase;

/**
 * The brand filter carries no parent entity to take its group label from, so
 * FilterService::hydrateFilterDto() falls back to a static label. That fallback
 * must be localized, like the one already hardcoded for the category filter,
 * instead of exposing the raw technical filter name.
 */
final class BrandFilterTitleTest extends IntegrationTestCase
{
    private FilterService $filterService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterService = static::getContainer()->get(FilterService::class);
    }

    public function testBrandFilterTitleIsLocalized(): void
    {
        $categoryId = $this->createCategoryWithABrandedProduct();

        $brandFilter = $this->findBrandFilter($categoryId, 'fr_FR');

        self::assertInstanceOf(Filter::class, $brandFilter);
        self::assertSame('Marque', $brandFilter->getTitle());
    }

    public function testBrandFilterTitleFallsBackToEnglishLabel(): void
    {
        $categoryId = $this->createCategoryWithABrandedProduct();

        $brandFilter = $this->findBrandFilter($categoryId, 'en_US');

        self::assertInstanceOf(Filter::class, $brandFilter);
        self::assertSame('Brand', $brandFilter->getTitle());
    }

    private function createCategoryWithABrandedProduct(): int
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        $template = new Template();
        $template->setLocale('en_US');
        $template->setName('Template with a brand filter');
        $template->save($connection);

        $category = $factory->category();
        $category->setDefaultTemplateId($template->getId());
        $category->save($connection);

        $brand = $factory->brand();
        // BrandFilter::getValue() reads the brand title in the requested locale,
        // so both tested locales need a translation row.
        $brand->setLocale('fr_FR')->setTitle('Marque de test');
        $brand->save($connection);

        $product = $factory->product($category, $factory->taxRule(), $factory->currency());
        $product->setBrandId($brand->getId());
        $product->setTemplateId($template->getId());
        $product->save($connection);

        // A visible choice filter is what makes the category expose filters at all.
        $other = new ChoiceFilterOther();
        $other->setType('brand');
        $other->setVisible(true);
        $other->setLocale('en_US');
        $other->setTitle('Brand');
        $other->save($connection);

        $choiceFilter = new ChoiceFilter();
        $choiceFilter->setCategoryId($category->getId());
        $choiceFilter->setTemplateId($template->getId());
        $choiceFilter->setOtherId($other->getId());
        $choiceFilter->setPosition(1);
        $choiceFilter->setVisible(true);
        $choiceFilter->setType('checkbox');
        $choiceFilter->save($connection);

        return $category->getId();
    }

    private function findBrandFilter(int $categoryId, string $locale): ?Filter
    {
        $filters = $this->filterService->getFilters(
            [
                'path_info' => '/api/front/products',
                'filters' => [
                    // tfilters values are nested one level deeper than the filter name.
                    'tfilters' => ['category' => [['eq' => $categoryId]]],
                    'locale' => $locale,
                ],
            ],
            'products',
        );

        foreach ($filters as $filter) {
            if ($filter->getType() === 'brand') {
                return $filter;
            }
        }

        return null;
    }
}
