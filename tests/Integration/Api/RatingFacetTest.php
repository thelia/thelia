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
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\CategoryFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\RatingFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Resource\Filter;
use Thelia\Api\Resource\FilterValue;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Catalog\Product\ProductRatingSourceInterface;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\Category;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\Template;
use Thelia\Test\IntegrationTestCase;

/**
 * The rating facet of a listing, fed by a review module through ProductRatingSourceInterface.
 * The source is a stand-in here: the core must behave the same whatever module answers, and
 * whether or not one is installed on the machine running the suite.
 *
 *   FIVE 4.6   FOUR 4.0   THREE 3.2   TWO 2.5   ONE 1.0   UNRATED (no review)
 */
final class RatingFacetTest extends IntegrationTestCase
{
    private Category $category;

    /** @var array<string, int> product reference => id, in creation order */
    private array $productIds = [];

    /** @var array<int, float> product id => average rating */
    private array $ratings = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCatalogue();
    }

    public function testEachThresholdCountsTheProductsReachingIt(): void
    {
        self::assertSame(
            ['4 stars & up' => 2, '3 stars & up' => 3, '2 stars & up' => 4],
            $this->counts($this->facets($this->filterService(withSource: true), [])),
        );
    }

    public function testAThresholdKeepsTheProductsReachingIt(): void
    {
        $filterService = $this->filterService(withSource: true);

        self::assertSame(['FIVE', 'FOUR'], $this->matching($filterService, [4]));
        self::assertSame(['FIVE', 'FOUR', 'THREE', 'TWO'], $this->matching($filterService, [2]));
    }

    public function testTwoThresholdsCheckedTogetherReadAsTheLowerOne(): void
    {
        self::assertSame(
            ['FIVE', 'FOUR', 'THREE', 'TWO'],
            $this->matching($this->filterService(withSource: true), [4, 2]),
        );
    }

    public function testACheckedThresholdKeepsTheOthersOnOffer(): void
    {
        $facets = $this->facets($this->filterService(withSource: true), ['rating' => ['rating' => [4]]]);

        self::assertSame(['4 stars & up' => 2, '3 stars & up' => 3, '2 stars & up' => 4], $this->counts($facets));
    }

    public function testAThresholdNoProductReachesIsNotOffered(): void
    {
        $this->ratings = [$this->productIds['THREE'] => 3.2];

        self::assertSame(
            ['3 stars & up' => 1, '2 stars & up' => 1],
            $this->counts($this->facets($this->filterService(withSource: true), [])),
        );
    }

    public function testWithoutASourceTheFacetIsWithheldAndAQueryStringNamingItIsIgnored(): void
    {
        $filterService = $this->filterService(withSource: false);

        self::assertNull($this->ratingFacet($this->facets($filterService, [])));
        self::assertSame(array_keys($this->productIds), $this->matching($filterService, [4]));
        self::assertSame(['rating'], $filterService->withheldFilterNames('products'));
    }

    public function testWithASourceNothingIsWithheld(): void
    {
        self::assertSame([], $this->filterService(withSource: true)->withheldFilterNames('products'));
    }

    private function filterService(bool $withSource): FilterService
    {
        $container = static::getContainer();
        $translator = $container->get(Translator::class);
        $ratings = &$this->ratings;
        $source = new class($ratings) implements ProductRatingSourceInterface {
            /** @param array<int, float> $ratings */
            public function __construct(private array &$ratings)
            {
            }

            public function productIdsRatedAtLeast(float $minimumRating, ?array $amongProductIds = null): array
            {
                $ids = [];

                foreach ($this->ratings as $productId => $rating) {
                    if ($rating >= $minimumRating && ($amongProductIds === null || \in_array($productId, $amongProductIds, true))) {
                        $ids[] = $productId;
                    }
                }

                return $ids;
            }
        };

        return new FilterService(
            filters: [
                $container->get(CategoryFilter::class),
                new RatingFilter($translator, $withSource ? [$source] : []),
            ],
            filterTypes: [],
            langService: $container->get(LangService::class),
            requestStack: $container->get(RequestStack::class),
            translator: $translator,
        );
    }

    /**
     * @param array<string, mixed> $tfilters
     *
     * @return array<Filter>
     */
    private function facets(FilterService $filterService, array $tfilters): array
    {
        return $filterService->getFilters(
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
    private function ratingFacet(array $facets): ?Filter
    {
        foreach ($facets as $facet) {
            if ($facet->getType() === 'rating') {
                return $facet;
            }
        }

        return null;
    }

    /**
     * @param array<Filter> $facets
     *
     * @return array<string, int>
     */
    private function counts(array $facets): array
    {
        $counts = [];

        /** @var FilterValue $value */
        foreach ($this->ratingFacet($facets)?->getValues() ?? [] as $value) {
            $counts[$value->getTitle()] = $value->getCount();
        }

        return $counts;
    }

    /**
     * @param list<int> $thresholds
     *
     * @return list<string>
     */
    private function matching(FilterService $filterService, array $thresholds): array
    {
        $query = ProductQuery::create()->filterById(array_values($this->productIds), Criteria::IN);
        $filtered = $filterService->filterWithTFilter(
            tfilters: ['rating' => ['rating' => $thresholds]],
            resource: 'products',
            query: $query,
        );

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
        $template->setName('Rating template');
        $template->save($connection);

        $this->category = $factory->category();
        $this->category->setDefaultTemplateId($template->getId())->save($connection);

        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        foreach (['FIVE' => 4.6, 'FOUR' => 4.0, 'THREE' => 3.2, 'TWO' => 2.5, 'ONE' => 1.0, 'UNRATED' => null] as $ref => $rating) {
            $product = $factory->product($this->category, $taxRule, $currency, ['ref' => $ref]);
            $this->productIds[$ref] = (int) $product->getId();

            if ($rating !== null) {
                $this->ratings[(int) $product->getId()] = $rating;
            }
        }
    }
}
