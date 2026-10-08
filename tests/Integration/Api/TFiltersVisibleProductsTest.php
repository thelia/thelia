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
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Product\ProductVisibilityRuleInterface;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\Sale\ReservedSaleProductRule;
use Thelia\Domain\Sale\ReservedSaleVisibility;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Model\Category;
use Thelia\Model\ChoiceFilter;
use Thelia\Model\Customer;
use Thelia\Model\Feature;
use Thelia\Model\FeatureProduct;
use Thelia\Model\Product;
use Thelia\Model\Sale;
use Thelia\Model\Template;
use Thelia\Test\IntegrationTestCase;

final class TFiltersVisibleProductsTest extends IntegrationTestCase
{
    private FilterService $filterService;

    private int $categoryId = 0;

    private Category $category;

    private Feature $colour;

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
     * A product the visitor cannot see, here because a reserved operation hides it
     * from everybody it does not name, lends nothing to the facets: no brand, no
     * feature value, no count. The same rule hides it from the listing itself.
     */
    public function testAProductHiddenFromTheVisitorFeedsNoFacet(): void
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();
        $currency = $factory->currency();

        $reserved = $factory->product($this->category, $factory->taxRule(), $currency, ['ref' => 'RESERVED', 'visible' => 1]);
        $reserved->setBrandId($factory->brand(['title' => 'Reserved brand'])->getId())->save($connection);
        $this->holdValue($reserved, (int) $this->colour->getId(), (int) $factory->featureAv($this->colour, ['title' => 'Green'])->getId());

        $sale = $factory->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => true,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
        $factory->saleProduct($sale, $reserved);
        $factory->saleCustomer($sale, $factory->customer($factory->customerTitle()));
        $factory->saleOffsetCurrency($sale, $currency, 10.0);

        // Both services answer from memory for the whole request.
        $this->getService(SaleAudienceChecker::class)->reset();
        $this->getService(ReservedSaleVisibility::class)->reset();

        self::assertSame(
            [
                'brand/Brand' => ['Shown brand'],
                'feature/Colour' => ['Blue'],
            ],
            $this->facets(['visible' => 'true']),
        );
    }

    /**
     * A rule of a module hides a product from the facets as a reserved operation
     * does. The kernel of the tests is shared and its container is not to be
     * changed, so the service is rebuilt from the container's own collaborators,
     * with a product visibility holding the rule.
     */
    public function testAProductHiddenByAModuleRuleFeedsNoFacet(): void
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        $ruled = $factory->product($this->category, $factory->taxRule(), $factory->currency(), ['ref' => 'RULED', 'visible' => 1]);
        $ruled->setBrandId($factory->brand(['title' => 'Ruled brand'])->getId())->save($connection);
        $this->holdValue($ruled, (int) $this->colour->getId(), (int) $factory->featureAv($this->colour, ['title' => 'Yellow'])->getId());

        $rule = new class((int) $ruled->getId()) implements ProductVisibilityRuleInterface {
            public function __construct(private readonly int $hiddenProductId)
            {
            }

            public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string
            {
                return \sprintf('%s <> %d', $productIdColumn, $this->hiddenProductId);
            }
        };

        // Without the rule the product feeds the facets: the fixture is one the rule has to work on.
        self::assertContains('Ruled brand', $this->facets(['visible' => 'true'])['brand/Brand']);

        $collaborators = [];

        foreach (['filters', 'filterTypes', 'langService', 'requestStack', 'translator'] as $property) {
            $collaborators[$property] = (new \ReflectionProperty($this->filterService, $property))->getValue($this->filterService);
        }

        $reservedSaleRule = new ReservedSaleProductRule($this->getService(SaleAudienceChecker::class), $this->getService(CurrentCustomerProvider::class));
        $this->filterService = new FilterService(...$collaborators, productVisibility: new ProductVisibility($reservedSaleRule, $this->getService(CurrentCustomerProvider::class), [$rule]));

        self::assertSame(
            [
                'brand/Brand' => ['Shown brand'],
                'feature/Colour' => ['Blue'],
            ],
            $this->facets(['visible' => 'true']),
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
        $this->category = $category;
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $colour = $factory->feature(['title' => 'Colour']);
        $this->colour = $colour;

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
