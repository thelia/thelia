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
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Category;
use Thelia\Model\ChoiceFilter;
use Thelia\Model\ChoiceFilterOther;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\Template;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The category facet and the category filter of the product listing never name, walk into or
 * filter on a category closed to the customer group of the visitor: a closed category is as
 * good as absent, and a product an open category keeps visible does not betray the closed one
 * it also sits in. The default group has no list and sees everything. The admin reads are not
 * narrowed.
 *
 * Catalogue, "pro" listing "open" only:
 *
 *   department (passing for "pro")
 *     open
 *     closed (closed to "pro")
 *       closed child
 */
final class TFiltersClosedCategoriesTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private FilterService $filterService;

    private Category $department;

    private Category $open;

    private Category $closed;

    private Category $closedChild;

    private CustomerGroup $pro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->filterService = static::getContainer()->get(FilterService::class);

        $this->department = $this->factory->category();
        $this->open = $this->factory->category(['parent' => $this->department->getId()]);
        $this->closed = $this->factory->category(['parent' => $this->department->getId()]);
        $this->closedChild = $this->factory->category(['parent' => $this->closed->getId()]);
        $this->pro = $this->factory->customerGroup('pro', categoryIds: [(int) $this->open->getId()]);
        $this->offerTheCategoryFacet($this->department);
        $this->offerTheCategoryFacet($this->closed);

        // The facets are read from the products of the browsed set: one sits in each branch, and one
        // sits in the closed category and in the open one, which keeps it visible to every group.
        foreach ([$this->open, $this->closed, $this->closedChild] as $category) {
            $this->factory->product($category, $this->factory->taxRule(), $this->factory->currency());
        }
        $shared = $this->factory->product($this->closed, $this->factory->taxRule(), $this->factory->currency());
        $this->factory->productCategory($shared, $this->open);
        $this->factory->productCategory($shared, $this->closedChild);

        $this->getService('services_resetter')->reset();
    }

    protected function tearDown(): void
    {
        $this->session()->setCustomerUser(null);
        $this->getService('services_resetter')->reset();

        parent::tearDown();
    }

    public function testTheFacetOfABrowsedCategoryDoesNotListItsClosedSubCategories(): void
    {
        $this->signInAs($this->pro);

        $ids = $this->categoryFacetIds($this->department, depth: 3);

        self::assertContains((int) $this->open->getId(), $ids);
        self::assertNotContains((int) $this->closed->getId(), $ids);
        self::assertNotContains((int) $this->closedChild->getId(), $ids, 'Closing a category closes its sub-tree.');
    }

    public function testTheDefaultGroupWithNoListSeesEveryCategoryInTheFacet(): void
    {
        $this->signInAs(null);

        $ids = $this->categoryFacetIds($this->department, depth: 3);

        self::assertContains((int) $this->closed->getId(), $ids);
        self::assertContains((int) $this->closedChild->getId(), $ids);
    }

    public function testAClosedBrowsedCategoryIsNotDevelopedIntoItsSubCategories(): void
    {
        $this->signInAs($this->pro);

        self::assertSame([], $this->categoryFacetIds($this->closed, depth: 3));
    }

    public function testTwoGroupsReadingTheSameFacetAreAnsweredDifferently(): void
    {
        $this->signInAs($this->pro);
        $seenByThePro = $this->categoryFacetIds($this->department, depth: 3);
        $this->signInAs(null);
        $seenByTheConsumer = $this->categoryFacetIds($this->department, depth: 3);
        $this->signInAs($this->pro);
        $seenAgainByThePro = $this->categoryFacetIds($this->department, depth: 3);

        self::assertNotContains((int) $this->closed->getId(), $seenByThePro);
        self::assertContains((int) $this->closed->getId(), $seenByTheConsumer);
        self::assertSame($seenByThePro, $seenAgainByThePro, 'The answer given to one group is not kept for the next.');
    }

    public function testFilteringOnAClosedCategoryMatchesNothingForAFrontReader(): void
    {
        $product = $this->factory->product($this->closed, $this->factory->taxRule(), $this->factory->currency());
        $this->factory->productCategory($product, $this->open);
        $this->signInAs($this->pro);

        self::assertSame([], $this->matchingIds([$this->closed->getId()], openToVisitorOnly: true, product: $product));
        self::assertSame([(int) $product->getId()], $this->matchingIds([$this->open->getId()], openToVisitorOnly: true, product: $product));
    }

    public function testTheDepthOfAFilterDoesNotWalkIntoAClosedSubTree(): void
    {
        $inChild = $this->factory->product($this->closedChild, $this->factory->taxRule(), $this->factory->currency());
        $this->signInAs($this->pro);

        self::assertSame([], $this->matchingIds([$this->department->getId()], openToVisitorOnly: true, product: $inChild, categoryDepth: 3));
        self::assertSame([(int) $inChild->getId()], $this->matchingIds([$this->department->getId()], openToVisitorOnly: false, product: $inChild, categoryDepth: 3));
    }

    public function testTheDefaultGroupWithNoListFiltersOnEveryCategory(): void
    {
        $product = $this->factory->product($this->closed, $this->factory->taxRule(), $this->factory->currency());
        $this->signInAs(null);

        self::assertSame([(int) $product->getId()], $this->matchingIds([$this->closed->getId()], openToVisitorOnly: true, product: $product));
    }

    public function testAnAdminReadOfTheFilterIsNotNarrowed(): void
    {
        $product = $this->factory->product($this->closed, $this->factory->taxRule(), $this->factory->currency());
        $this->signInAs($this->pro);

        self::assertSame([(int) $product->getId()], $this->matchingIds([$this->closed->getId()], openToVisitorOnly: false, product: $product));
    }

    /**
     * @return list<int> the ids of the categories the facet offers
     */
    private function categoryFacetIds(Category $browsed, int $depth): array
    {
        $filters = $this->filterService->getFilters(
            [
                'path_info' => '/api/front/products',
                'filters' => [
                    'tfilters' => ['category' => [['eq' => $browsed->getId()]], 'category_depth' => $depth],
                    'category_depth' => $depth,
                    'locale' => 'en_US',
                ],
            ],
            'products',
        );

        $ids = [];

        /** @var Filter $filter */
        foreach ($filters as $filter) {
            if ('category' !== $filter->getType()) {
                continue;
            }

            /** @var FilterValue $value */
            foreach ($filter->getValues() as $value) {
                $ids[] = (int) $value->getId();
            }
        }

        return $ids;
    }

    /**
     * @param list<int|string|null> $selection
     *
     * @return list<int> the id of the product when the filter keeps it
     */
    private function matchingIds(array $selection, bool $openToVisitorOnly, Product $product, ?int $categoryDepth = null): array
    {
        $query = ProductQuery::create()->filterById($product->getId(), Criteria::EQUAL);

        $filtered = $this->filterService->filterWithTFilter(
            tfilters: ['category' => [$this->department->getId() => $selection]],
            resource: 'products',
            query: $query,
            categoryDepth: $categoryDepth,
            openToVisitorOnly: $openToVisitorOnly,
        );

        $ids = [];
        foreach ($filtered->find($this->getPropelConnection()) as $found) {
            $ids[] = (int) $found->getId();
        }

        return $ids;
    }

    /**
     * A visible choice filter is what makes a browsed category expose a category facet at all.
     */
    private function offerTheCategoryFacet(Category $category): void
    {
        $connection = $this->getPropelConnection();

        $template = new Template();
        $template->setLocale('en_US')->setName('Template '.$category->getId())->save($connection);
        $category->setDefaultTemplateId($template->getId())->save($connection);

        $other = new ChoiceFilterOther();
        $other->setType('category')->setVisible(true)->setLocale('en_US')->setTitle('Category')->save($connection);

        (new ChoiceFilter())
            ->setCategoryId($category->getId())
            ->setTemplateId($template->getId())
            ->setOtherId($other->getId())
            ->setPosition(1)
            ->setVisible(true)
            ->setType('checkbox')
            ->save($connection);
    }

    private function signInAs(?CustomerGroup $group): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle(), null === $group ? [] : ['customerGroup' => $group]);
        $this->session()->setCustomerUser($customer);
    }

    private function session(): Session
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}
