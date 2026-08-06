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
use Thelia\Model\ChoiceFilterOther;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\Template;
use Thelia\Test\IntegrationTestCase;

/**
 * A brand name is a proper noun, so a shop routinely leaves it untranslated in
 * secondary locales. Reading such a title in the requested locale yields null,
 * which FilterValue::setTitle() rejects — and because FilterService builds the
 * whole list in one pass, a single untranslated entity used to fail every filter
 * of the page, not just its own facet.
 *
 * Whether the title then falls back to the default language is governed by the
 * back office "If a translation is missing or incomplete" setting, as it is in
 * ResourceService::formatI18ns().
 */
final class FilterValueTitleFallbackTest extends IntegrationTestCase
{
    private const BRAND_TITLE = 'Untranslated Brand';

    private FilterService $filterService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterService = static::getContainer()->get(FilterService::class);
    }

    protected function tearDown(): void
    {
        // The config cache is static: left as-is it would outlive the transaction
        // rollback and leak this test's setting into the rest of the suite.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testUntranslatedTitleFallsBackWhenTheSettingAllowsIt(): void
    {
        $this->setTranslationFallback(Lang::REPLACE_BY_DEFAULT_LANGUAGE);

        $categoryId = $this->createCategoryWithAnUntranslatedBrandedProduct();

        $brandFilter = $this->findBrandFilter($categoryId, 'fr_FR');

        self::assertInstanceOf(Filter::class, $brandFilter);

        $values = $brandFilter->getValues();
        self::assertCount(1, $values);
        self::assertInstanceOf(FilterValue::class, $values[0]);
        self::assertSame(self::BRAND_TITLE, $values[0]->getTitle());
    }

    /**
     * Strict mode keeps the facet unlabelled rather than borrowing another
     * language — but it must still not take the filter list down with it.
     */
    public function testUntranslatedTitleStaysEmptyWhenTheSettingIsStrict(): void
    {
        $this->setTranslationFallback(Lang::STRICTLY_USE_REQUESTED_LANGUAGE);

        $categoryId = $this->createCategoryWithAnUntranslatedBrandedProduct();

        $brandFilter = $this->findBrandFilter($categoryId, 'fr_FR');

        self::assertInstanceOf(Filter::class, $brandFilter);

        $values = $brandFilter->getValues();
        self::assertCount(1, $values);
        self::assertSame('', $values[0]->getTitle());
    }

    public function testUntranslatedBrandKeepsItsTitleInTheDefaultLocale(): void
    {
        $categoryId = $this->createCategoryWithAnUntranslatedBrandedProduct();

        $brandFilter = $this->findBrandFilter($categoryId, 'en_US');

        self::assertInstanceOf(Filter::class, $brandFilter);
        self::assertSame(self::BRAND_TITLE, $brandFilter->getValues()[0]->getTitle());
    }

    /**
     * ConfigQuery::read() only honours a stored value through the cache the kernel
     * primes at boot; on a cold read a stored "0" is falsy and collapses back to the
     * default. Priming the cache the way ConfigCacheService does is what makes the
     * strict setting observable here at all.
     */
    private function setTranslationFallback(int $mode): void
    {
        ConfigQuery::write('default_lang_without_translation', (string) $mode);

        $configs = [];
        foreach (ConfigQuery::create()->find() as $config) {
            $configs[$config->getName()] = $config->getValue();
        }

        ConfigQuery::initCache($configs);
    }

    private function createCategoryWithAnUntranslatedBrandedProduct(): int
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

        // Translated in the default language only: this is what triggers the bug.
        $brand = $factory->brand(['locale' => 'en_US', 'title' => self::BRAND_TITLE]);

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
