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

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FacetScopeInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Resource\Filter;
use Thelia\Api\Resource\FilterValue;
use Thelia\Model\ChoiceFilter;
use Thelia\Model\FeatureProduct;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\Template;
use Thelia\Test\IntegrationTestCase;

/**
 * A module narrows the products the facets are read from, the way it narrows the listing they
 * accompany: a value held by a product the listing never shows is neither offered nor counted.
 *
 * Catalogue, all filed in one category with a Colour column:
 *
 *   LOCAL-BLUE   brand Local,  Blue
 *   ELSEWHERE-BLUE  brand Remote, Blue
 *   ELSEWHERE-RED   brand Remote, Red
 *
 * The scope under test hides the two products named ELSEWHERE-*.
 */
final class FacetCatalogScopeTest extends IntegrationTestCase
{
    private FilterService $filterService;

    private int $categoryId = 0;

    private int $colourId = 0;

    private int $blueId = 0;

    /** @var list<int> */
    private array $elsewhereProductIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->filterService = static::getContainer()->get(FilterService::class);
        $this->createCatalogue();
    }

    public function testWithoutAScopeEveryProductIsCounted(): void
    {
        $facets = $this->facets([]);

        self::assertSame(['Blue' => 2, 'Red' => 1], $this->counts($facets, 'feature'));
        self::assertSame(['Local' => 1, 'Remote' => 2], $this->counts($facets, 'brand'));
    }

    public function testAScopeKeepsTheProductsItHidesOutOfTheCounts(): void
    {
        $this->filterService = $this->withScopes([$this->hidingElsewhere()]);

        $facets = $this->facets([]);

        self::assertSame(['Blue' => 1], $this->counts($facets, 'feature'));
        self::assertSame(['Local' => 1], $this->counts($facets, 'brand'));
    }

    /**
     * A checked value offers its siblings from the set narrowed by everything but its own
     * group: that set is read through the scope too.
     */
    public function testAScopeAlsoNarrowsTheSetOfTheSiblingsOfACheckedValue(): void
    {
        $this->filterService = $this->withScopes([$this->hidingElsewhere()]);

        $facets = $this->facets(['feature' => [$this->colourId => [$this->blueId]]]);

        self::assertSame(['Blue' => 1], $this->counts($facets, 'feature'));
        self::assertSame(['Local' => 1], $this->counts($facets, 'brand'));
    }

    /**
     * Each scope hides a different product: the Remote brand and the Red value survive only if
     * the loop stops after the first scope.
     */
    public function testEveryScopeIsAskedAndTheyNarrowTogether(): void
    {
        [$remoteBlue, $remoteRed] = $this->elsewhereProductIds;

        $this->filterService = $this->withScopes([$this->hiding([$remoteBlue]), $this->hiding([$remoteRed])]);

        $facets = $this->facets([]);

        self::assertSame(['Blue' => 1], $this->counts($facets, 'feature'));
        self::assertSame(['Local' => 1], $this->counts($facets, 'brand'));
    }

    /**
     * A facet that does not count by aggregate, as a module's would, is read from the products
     * themselves: the query that feeds it is the scope's too.
     */
    public function testAFacetReadFromTheProductsAlsoFollowsTheScope(): void
    {
        $reference = $this->referenceFacet();

        $this->filterService = $this->withScopes([], [$reference]);
        self::assertSame(['ELSEWHERE-BLUE', 'ELSEWHERE-RED', 'LOCAL-BLUE'], array_keys($this->counts($this->facets([]), 'reference')));

        $this->filterService = $this->withScopes([$this->hidingElsewhere()], [$reference]);
        self::assertSame(['LOCAL-BLUE'], array_keys($this->counts($this->facets([]), 'reference')));
    }

    public function testAScopeIsGivenTheResourceTheFacetsDescribe(): void
    {
        $seen = new \ArrayObject();
        $scope = new class($seen) implements FacetScopeInterface {
            public function __construct(private readonly \ArrayObject $seen)
            {
            }

            public function restrict(ModelCriteria $query, string $resource): void
            {
                $this->seen[] = [$resource, $query::class];
            }
        };

        $this->filterService = $this->withScopes([$scope]);
        $this->facets([]);

        self::assertNotSame([], $seen->getArrayCopy());

        foreach ($seen as [$resource, $queryClass]) {
            self::assertSame('products', $resource);
            self::assertSame(ProductQuery::class, $queryClass);
        }
    }

    /**
     * Without `#[AutowireIterator]` the container falls back on the empty default and every
     * scope of every module is silently ignored; without `#[AutoconfigureTag]` no module class
     * is ever collected.
     */
    public function testTheContainerGivesTheServiceTheScopesItCollects(): void
    {
        $service = $this->getService(FilterService::class);

        self::assertInstanceOf(RewindableGenerator::class, (new \ReflectionProperty($service, 'facetScopes'))->getValue($service));

        $tags = array_map(
            static fn (\ReflectionAttribute $attribute): ?string => $attribute->getArguments()[0] ?? null,
            (new \ReflectionClass(FacetScopeInterface::class))->getAttributes(AutoconfigureTag::class),
        );

        self::assertSame([FacetScopeInterface::TAG], $tags);
        self::assertSame('api.thelia.facet_scope', FacetScopeInterface::TAG);
    }

    /**
     * @param list<FacetScopeInterface>   $scopes
     * @param list<TheliaFilterInterface> $extraFilters facets added to the ones of the core
     */
    private function withScopes(array $scopes, array $extraFilters = []): FilterService
    {
        $arguments = [];

        foreach ((new \ReflectionClass(FilterService::class))->getConstructor()->getParameters() as $parameter) {
            $name = $parameter->getName();
            $value = (new \ReflectionProperty($this->filterService, $name))->getValue($this->filterService);
            $arguments[$name] = match ($name) {
                'facetScopes' => $scopes,
                'filters' => [...$value, ...$extraFilters],
                default => $value,
            };
        }

        return new FilterService(...$arguments);
    }

    private function hidingElsewhere(): FacetScopeInterface
    {
        return $this->hiding($this->elsewhereProductIds);
    }

    /**
     * @param list<int> $hiddenProductIds
     */
    private function hiding(array $hiddenProductIds): FacetScopeInterface
    {
        return new class($hiddenProductIds) implements FacetScopeInterface {
            /**
             * @param list<int> $hiddenProductIds
             */
            public function __construct(private readonly array $hiddenProductIds)
            {
            }

            public function restrict(ModelCriteria $query, string $resource): void
            {
                $query->where(\sprintf('%s NOT IN (%s)', ProductTableMap::COL_ID, implode(', ', $this->hiddenProductIds)));
            }
        };
    }

    /**
     * A facet of a module: not aggregated, it offers the reference of each product it is given.
     */
    private function referenceFacet(): TheliaFilterInterface
    {
        return new class implements TheliaFilterInterface {
            public function getResourceType(): array
            {
                return ['products'];
            }

            public static function getFilterName(): array
            {
                return ['reference'];
            }

            public function filter(ModelCriteria $query, $value, bool $isMinOrMaxFilter = false, ?int $categoryDepth = null): void
            {
            }

            public function getValue(ActiveRecordInterface $activeRecord, string $locale, $valueSearched = null, ?int $depth = 1): ?array
            {
                return [(new FilterValue())->setId((int) $activeRecord->getId())->setTitle((string) $activeRecord->getRef())];
            }
        };
    }

    /**
     * @param array<string, mixed> $tfilters the selection, besides the browsed category
     *
     * @return array<Filter>
     */
    private function facets(array $tfilters): array
    {
        return $this->filterService->getFilters(
            [
                'path_info' => '/api/front/products',
                'filters' => [
                    'tfilters' => ['category' => [['eq' => $this->categoryId]], ...$tfilters],
                    'locale' => 'en_US',
                    'visible' => 'true',
                ],
            ],
            'products',
        );
    }

    /**
     * @param array<Filter> $facets
     *
     * @return array<string, int>
     */
    private function counts(array $facets, string $type): array
    {
        foreach ($facets as $facet) {
            if ($facet->getType() !== $type) {
                continue;
            }

            $counts = [];

            /** @var FilterValue $value */
            foreach ($facet->getValues() as $value) {
                $counts[(string) $value->getTitle()] = (int) $value->getCount();
            }

            ksort($counts);

            return $counts;
        }

        return [];
    }

    private function createCatalogue(): void
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        $template = new Template();
        $template->setLocale('en_US');
        $template->setName('Scoped template');
        $template->save($connection);

        $category = $factory->category();
        $category->setDefaultTemplateId($template->getId())->save($connection);
        $this->categoryId = (int) $category->getId();

        $colour = $factory->feature(['title' => 'Colour']);
        $this->colourId = (int) $colour->getId();

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
        $this->blueId = (int) $blue->getId();

        $local = $factory->brand(['title' => 'Local']);
        $remote = $factory->brand(['title' => 'Remote']);
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $catalogue = [
            'LOCAL-BLUE' => [$local, $blue],
            'ELSEWHERE-BLUE' => [$remote, $blue],
            'ELSEWHERE-RED' => [$remote, $red],
        ];

        foreach ($catalogue as $reference => [$brand, $value]) {
            $product = $factory->product($category, $taxRule, $currency, ['ref' => $reference, 'visible' => 1]);
            $product->setBrandId($brand->getId())->save($connection);
            $this->holdValue($product, (int) $colour->getId(), (int) $value->getId());

            if (str_starts_with($reference, 'ELSEWHERE')) {
                $this->elsewhereProductIds[] = (int) $product->getId();
            }
        }
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
