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

namespace Thelia\Tests\Integration\Domain\Catalog;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Thelia\Api\Service\API\ResourceCache;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Catalog\Category\CategoryVisibility;
use Thelia\Domain\Catalog\Product\ClosedCategoryProductRule;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Product\ProductVisibilityRuleInterface;
use Thelia\Domain\Customer\Service\CustomerGroupLinks;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\Sale\ReservedSaleProductRule;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupCategory;
use Thelia\Model\CustomerGroupCategoryQuery;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A group that lists categories browses them and their sub-trees (open), passes through
 * their ancestors (passing: browsable, their own products hidden unless an open category
 * holds them too) and is shut out of every other one (closed); a group with no list browses
 * the whole catalog; and while no group has a list, nothing is narrowed and the data access
 * cache is left alone.
 *
 * The tree of the examples: Living Room > [Armchairs, Sofas], Dining Room > [Chairs, Stools],
 * the professionals listing Sofas only.
 */
final class CategoryVisibilityTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private FixtureFactory $factory;

    private CategoryVisibility $categoryVisibility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->categoryVisibility = $this->getService(CategoryVisibility::class);
        $this->getService('services_resetter')->reset();
    }

    protected function tearDown(): void
    {
        $session = $this->session();
        $session->setCustomerUser(null);
        $this->getService('services_resetter')->reset();

        parent::tearDown();
    }

    public function testNothingIsClosedWhileNoCategoryIsRestricted(): void
    {
        $this->factory->category();
        $customer = $this->factory->customer($this->factory->customerTitle());

        self::assertSame([], $this->categoryVisibility->closedCategoryIdsFor($customer));
        self::assertSame([], $this->categoryVisibility->closedCategoryIdsFor(null));
        self::assertFalse($this->categoryVisibility->hasRestrictions());
        self::assertNull($this->getService(ClosedCategoryProductRule::class)->visibleProductClause('product.id', $customer));
    }

    public function testTheProfessionalsBrowseSofasPassThroughLivingRoomAndAreShutOutOfTheRest(): void
    {
        $tree = $this->furnitureTree();
        $pro = $this->factory->customerGroup('pro', categoryIds: [$tree['sofas']]);
        $this->getService('services_resetter')->reset();
        $professional = $this->customerOf($pro);

        self::assertTrue($this->categoryVisibility->hasRestrictions());
        self::assertSame(
            [$tree['diningRoom'], $tree['chairs'], $tree['stools'], $tree['armchairs']],
            $this->categoryVisibility->closedCategoryIdsFor($professional),
            'Armchairs and the whole Dining Room are closed; Living Room is passing, Sofas open.',
        );
        self::assertSame([$tree['sofas']], $this->categoryVisibility->openCategoryIdsFor($professional));
    }

    public function testTheDefaultGroupWithNoListBrowsesTheWholeCatalogAndAVisitorIsTheDefaultGroup(): void
    {
        $tree = $this->furnitureTree();
        $this->factory->customerGroup('pro', categoryIds: [$tree['sofas']]);
        $this->getService('services_resetter')->reset();

        self::assertSame([], $this->categoryVisibility->closedCategoryIdsFor($this->customerOf(null)));
        self::assertNull($this->categoryVisibility->openCategoryIdsFor($this->customerOf(null)));
        self::assertSame([], $this->categoryVisibility->closedCategoryIdsFor(null), 'A visitor is the default group.');
        self::assertNull($this->getService(ClosedCategoryProductRule::class)->visibleProductClause('product.id', null));
    }

    public function testAVisitorFollowsTheListOfTheDefaultGroupWhenItHasOne(): void
    {
        $tree = $this->furnitureTree();
        $this->listForTheDefaultGroup([$tree['diningRoom']]);
        $this->getService('services_resetter')->reset();

        self::assertSame([$tree['livingRoom'], $tree['armchairs'], $tree['sofas']], $this->categoryVisibility->closedCategoryIdsFor(null));
        self::assertSame([$tree['diningRoom'], $tree['chairs'], $tree['stools']], $this->categoryVisibility->openCategoryIdsFor(null));
    }

    public function testAListedCategoryOpensItsWholeSubTree(): void
    {
        $root = $this->factory->category();
        $child = $this->factory->category(['parent' => $root->getId()]);
        $grandChild = $this->factory->category(['parent' => $child->getId()]);
        $sibling = $this->factory->category();
        $pro = $this->factory->customerGroup('pro', categoryIds: [(int) $root->getId()]);
        $this->getService('services_resetter')->reset();

        self::assertSame([(int) $sibling->getId()], $this->categoryVisibility->closedCategoryIdsFor($this->customerOf($pro)));
        self::assertSame([(int) $root->getId(), (int) $child->getId(), (int) $grandChild->getId()], $this->categoryVisibility->openCategoryIdsFor($this->customerOf($pro)));
    }

    public function testEveryAncestorOfAListedCategoryIsPassingUpToTheRoot(): void
    {
        $root = $this->factory->category();
        $child = $this->factory->category(['parent' => $root->getId()]);
        $grandChild = $this->factory->category(['parent' => $child->getId()]);
        $uncle = $this->factory->category(['parent' => $root->getId()]);
        $pro = $this->factory->customerGroup('pro', categoryIds: [(int) $grandChild->getId()]);
        $this->getService('services_resetter')->reset();

        self::assertSame([(int) $uncle->getId()], $this->categoryVisibility->closedCategoryIdsFor($this->customerOf($pro)), 'The root and the child are passing.');
        self::assertSame([(int) $grandChild->getId()], $this->categoryVisibility->openCategoryIdsFor($this->customerOf($pro)));
    }

    public function testTheListOfOneGroupNeverClosesAnythingToAnother(): void
    {
        $tree = $this->furnitureTree();
        $pro = $this->factory->customerGroup('pro', categoryIds: [$tree['sofas']]);
        $trade = $this->factory->customerGroup('trade', categoryIds: [$tree['chairs']]);
        $retail = $this->factory->customerGroup('retail');
        $this->getService('services_resetter')->reset();

        self::assertContains($tree['sofas'], $this->categoryVisibility->closedCategoryIdsFor($this->customerOf($trade)), 'Sofas is not on the list of trade.');
        self::assertContains($tree['chairs'], $this->categoryVisibility->closedCategoryIdsFor($this->customerOf($pro)), 'Chairs is not on the list of pro.');
        self::assertSame([], $this->categoryVisibility->closedCategoryIdsFor($this->customerOf($retail)), 'A group with no list sees everything, whatever the others list.');
    }

    public function testTheProductsAreVisibleThroughAnOpenCategoryOnly(): void
    {
        $tree = $this->furnitureTree();
        $sofa = $this->product($tree['sofas']);
        $armchair = $this->product($tree['armchairs']);
        $onTheLivingRoomShelf = $this->product($tree['livingRoom']);
        $alsoASofa = $this->product($tree['livingRoom']);
        $this->factory->productCategory($alsoASofa, $this->category($tree['sofas']));
        $chair = $this->product($tree['chairs']);
        $armchairAndChair = $this->product($tree['armchairs']);
        $this->factory->productCategory($armchairAndChair, $this->category($tree['chairs']));
        $pro = $this->factory->customerGroup('pro', categoryIds: [$tree['sofas']]);
        $this->getService('services_resetter')->reset();

        $this->signIn($this->customerOf($pro));
        $visible = $this->visibleProductIds();

        self::assertContains((int) $sofa->getId(), $visible, 'A product of an open category is visible.');
        self::assertNotContains((int) $armchair->getId(), $visible, 'A product of a closed category is hidden.');
        self::assertNotContains((int) $onTheLivingRoomShelf->getId(), $visible, 'A product filed in a passing category only is hidden.');
        self::assertContains((int) $alsoASofa->getId(), $visible, 'A product of a passing category that an open one holds too is visible.');
        self::assertNotContains((int) $chair->getId(), $visible);
        self::assertNotContains((int) $armchairAndChair->getId(), $visible, 'Two closed categories do not make an open one.');
    }

    public function testTheProfessionalTheConsumerAndTheVisitorEachSeeTheirOwnCatalog(): void
    {
        $tree = $this->furnitureTree();
        $sofa = $this->product($tree['sofas']);
        $armchair = $this->product($tree['armchairs']);
        $pro = $this->factory->customerGroup('pro', categoryIds: [$tree['sofas']]);
        $this->getService('services_resetter')->reset();

        $this->signIn($this->customerOf($pro));
        $seenByTheProfessional = $this->visibleProductIds();
        $this->signIn($this->customerOf(null));
        $seenByTheConsumer = $this->visibleProductIds();
        $this->session()->setCustomerUser(null);
        $this->getService('services_resetter')->reset();
        $seenByTheVisitor = $this->visibleProductIds();

        self::assertContains((int) $sofa->getId(), $seenByTheProfessional);
        self::assertNotContains((int) $armchair->getId(), $seenByTheProfessional);
        self::assertContains((int) $sofa->getId(), $seenByTheConsumer);
        self::assertContains((int) $armchair->getId(), $seenByTheConsumer);
        self::assertContains((int) $sofa->getId(), $seenByTheVisitor);
        self::assertContains((int) $armchair->getId(), $seenByTheVisitor);
    }

    public function testAProductWithNoCategoryStaysVisibleToARestrictedGroup(): void
    {
        $tree = $this->furnitureTree();
        $orphan = $this->product($tree['armchairs']);
        ProductCategoryQuery::create()->filterByProductId($orphan->getId())->delete();
        $pro = $this->factory->customerGroup('pro', categoryIds: [$tree['sofas']]);
        $this->getService('services_resetter')->reset();

        $this->signIn($this->customerOf($pro));

        self::assertContains((int) $orphan->getId(), $this->visibleProductIds());
    }

    public function testDeletingTheOnlyCategoryAGroupListsClosesTheCatalogToItInsteadOfOpeningIt(): void
    {
        $tree = $this->furnitureTree();
        $armchair = $this->product($tree['armchairs']);
        $onTheLivingRoomShelf = $this->product($tree['livingRoom']);
        $orphan = $this->product($tree['armchairs']);
        ProductCategoryQuery::create()->filterByProductId($orphan->getId())->delete();
        $pro = $this->factory->customerGroup('pro', categoryIds: [$tree['sofas']]);

        // The administrator deletes Sofas: the database drops the link and nothing else.
        $this->category($tree['sofas'])->delete($this->getPropelConnection());
        self::assertSame(0, CustomerGroupCategoryQuery::create()->filterByCustomerGroupId($pro->getId())->count(), 'The list is empty.');
        self::assertSame(1, CustomerGroupQuery::create()->findPk($pro->getId())->getRestrictsCategories(), 'The flag is still there.');
        $this->getService('services_resetter')->reset();
        $professional = $this->customerOf($pro);

        self::assertTrue($this->categoryVisibility->hasRestrictions());
        self::assertSame(
            [$tree['livingRoom'], $tree['diningRoom'], $tree['chairs'], $tree['stools'], $tree['armchairs']],
            $this->categoryVisibility->closedCategoryIdsFor($professional),
            'Living Room is closed too: nothing is open, so nothing is passing.',
        );
        self::assertSame([], $this->categoryVisibility->openCategoryIdsFor($professional));

        $this->signIn($professional);
        $visible = $this->visibleProductIds();
        self::assertNotContains((int) $armchair->getId(), $visible);
        self::assertNotContains((int) $onTheLivingRoomShelf->getId(), $visible);
        self::assertContains((int) $orphan->getId(), $visible, 'A product filed nowhere stays visible.');

        $this->signIn($this->customerOf(null));
        $this->getService('services_resetter')->reset();
        self::assertSame([], $this->categoryVisibility->closedCategoryIdsFor($this->customerOf(null)), 'The default group sees all.');
    }

    public function testEmptyingTheListWithTheWriterOpensTheCatalogAgain(): void
    {
        $tree = $this->furnitureTree();
        $pro = $this->factory->customerGroup('pro', categoryIds: [$tree['sofas']]);
        $this->category($tree['sofas'])->delete($this->getPropelConnection());

        $this->getService(CustomerGroupLinks::class)->replace($pro, [], null, $this->getPropelConnection());
        $this->getService('services_resetter')->reset();

        self::assertSame([], $this->categoryVisibility->closedCategoryIdsFor($this->customerOf($pro)));
        self::assertNull($this->categoryVisibility->openCategoryIdsFor($this->customerOf($pro)));
        self::assertFalse($this->categoryVisibility->hasRestrictions());
    }

    public function testAListWithNoFlagRestrictsNothing(): void
    {
        $tree = $this->furnitureTree();
        $pro = $this->factory->customerGroup('pro', categoryIds: [$tree['sofas']]);
        $pro->setRestrictsCategories(0)->save($this->getPropelConnection());
        $this->getService('services_resetter')->reset();

        self::assertFalse($this->categoryVisibility->hasRestrictions());
        self::assertSame([], $this->categoryVisibility->closedCategoryIdsFor($this->customerOf($pro)));
    }

    public function testNoRestrictionLeavesTheProductQueryAndTheCacheAlone(): void
    {
        $product = $this->product($this->factory->category());
        $this->factory->customerGroup('pro');
        $this->getService('services_resetter')->reset();

        $visibility = $this->getService(ProductVisibility::class);

        self::assertNull($visibility->visibleProductClause('product.id'));
        self::assertFalse($visibility->hasModuleRules());
        self::assertContains((int) $product->getId(), $this->visibleProductIds());

        $cache = $this->resourceCache();
        self::assertSame(['first'], $cache->remember('categories', '/api/front/categories', static fn (): array => ['first']));
        self::assertSame(['first'], $cache->remember('categories', '/api/front/categories', static fn (): array => ['second']), 'Cached as before.');
        self::assertSame(['first'], $cache->remember('products', '/api/front/products/1', static fn (): array => ['first']));
        self::assertSame(['first'], $cache->remember('products', '/api/front/products/1', static fn (): array => ['second']), 'Cached as before.');
    }

    /**
     * A closed category does not take the catalog out of the cache: the group is in the
     * key of what its visibility changes, and each group is served from its own entry.
     */
    public function testAClosedCategoryKeepsTheCatalogOnTheCacheOneEntryPerGroup(): void
    {
        $listed = $this->factory->category();
        $this->factory->category();
        $groupA = $this->factory->customerGroup('group-a', categoryIds: [(int) $listed->getId()]);
        $groupB = $this->factory->customerGroup('group-b');
        $this->getService('services_resetter')->reset();

        self::assertFalse($this->getService(ProductVisibility::class)->hasModuleRules(), 'A closed category is not a rule of a module.');

        $cache = $this->resourceCache();
        $firstOfA = $this->customerOf($groupA);
        $secondOfA = $this->customerOf($groupA);
        $ofB = $this->customerOf($groupB);

        foreach (['/api/front/categories', '/api/front/products/1', '/api/front/product_images'] as $path) {
            $read = function (?Customer $customer, string $payload) use ($cache, $path): array {
                $this->session()->setCustomerUser($customer);

                return $cache->remember('key', $path, static fn (): array => [$payload]);
            };

            self::assertSame(['A'], $read($firstOfA, 'A'), $path);
            self::assertSame(['B'], $read($ofB, 'B'), $path.': a customer of another group is not served the entry of the first');
            self::assertSame(['visitor'], $read(null, 'visitor'), $path.': nor is a visitor');
            self::assertSame(['A'], $read($firstOfA, 'again'), $path.': the group is served from the cache');
            self::assertSame(['A'], $read($secondOfA, 'again'), $path.': a second customer of the group shares its entry');
            self::assertSame(['visitor'], $read(null, 'again'), $path);
            self::assertSame(['B'], $read($ofB, 'again'), $path);
        }

        $this->session()->setCustomerUser($ofB);
        self::assertSame(['first'], $cache->remember('countries', '/api/front/countries', static fn (): array => ['first']));
        self::assertSame(['first'], $cache->remember('countries', '/api/front/countries', static fn (): array => ['second']), 'What does not depend on the visitor stays cached, with no group in its key.');
    }

    /**
     * The rule of a module may look at anything of the visitor, which no key can hold: the
     * products and what hangs on them step aside for as long as one is declared, the
     * categories, which it does not touch, stay on the cache of their group.
     */
    public function testAModuleRuleStillTakesTheProductsOutOfTheCacheWhileTheCategoriesStayOnIt(): void
    {
        $listed = $this->factory->category();
        $this->factory->customerGroup('group-a', categoryIds: [(int) $listed->getId()]);
        $this->getService('services_resetter')->reset();
        $this->session()->setCustomerUser($this->customerOf(null));

        $rule = new class implements ProductVisibilityRuleInterface {
            public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string
            {
                return null;
            }
        };
        $withModuleRule = new ProductVisibility(
            $this->getService(ReservedSaleProductRule::class),
            $this->getService(CurrentCustomerProvider::class),
            [$rule],
            $this->getService(ClosedCategoryProductRule::class),
        );
        $cache = $this->resourceCache($withModuleRule);

        foreach (['/api/front/products/1', '/api/front/product_images'] as $path) {
            self::assertSame(['first'], $cache->remember('key', $path, static fn (): array => ['first']), $path);
            self::assertSame(['second'], $cache->remember('key', $path, static fn (): array => ['second']), $path.': bypassed');
        }

        self::assertSame(['first'], $cache->remember('key', '/api/front/categories', static fn (): array => ['first']));
        self::assertSame(['first'], $cache->remember('key', '/api/front/categories', static fn (): array => ['second']));
    }

    public function testTheListsAreReadOncePerGroupAndNotPerProduct(): void
    {
        $tree = $this->furnitureTree();
        $pro = $this->factory->customerGroup('pro', categoryIds: [$tree['sofas']]);
        $this->getService('services_resetter')->reset();
        $professional = $this->customerOf($pro);
        $consumer = $this->customerOf(null);

        $this->categoryVisibility->closedCategoryIdsFor($professional);
        $this->categoryVisibility->closedCategoryIdsFor($consumer);

        $statements = $this->recordSqlQueries(function () use ($professional, $consumer): void {
            for ($i = 0; $i < 30; ++$i) {
                foreach ([$professional, $consumer] as $customer) {
                    $this->categoryVisibility->closedCategoryIdsFor($customer);
                    $this->categoryVisibility->openCategoryIdsFor($customer);
                    $this->getService(ClosedCategoryProductRule::class)->visibleProductClause('product.id', $customer);
                }
            }
        });

        self::assertSame([], $statements, 'Thirty readings cost no query once the first is made.');
    }

    /**
     * Living Room > [Armchairs, Sofas], Dining Room > [Chairs, Stools], created in that order.
     *
     * @return array{livingRoom: int, diningRoom: int, chairs: int, stools: int, armchairs: int, sofas: int}
     */
    private function furnitureTree(): array
    {
        $livingRoom = $this->factory->category();
        $diningRoom = $this->factory->category();
        $chairs = $this->factory->category(['parent' => $diningRoom->getId()]);
        $stools = $this->factory->category(['parent' => $diningRoom->getId()]);
        $armchairs = $this->factory->category(['parent' => $livingRoom->getId()]);
        $sofas = $this->factory->category(['parent' => $livingRoom->getId()]);

        return [
            'livingRoom' => (int) $livingRoom->getId(),
            'diningRoom' => (int) $diningRoom->getId(),
            'chairs' => (int) $chairs->getId(),
            'stools' => (int) $stools->getId(),
            'armchairs' => (int) $armchairs->getId(),
            'sofas' => (int) $sofas->getId(),
        ];
    }

    /**
     * @param list<int> $categoryIds
     */
    private function listForTheDefaultGroup(array $categoryIds): void
    {
        $defaultGroupId = (int) CustomerGroupQuery::findDefault()->getId();

        foreach ($categoryIds as $categoryId) {
            (new CustomerGroupCategory())->setCustomerGroupId($defaultGroupId)->setCategoryId($categoryId)->save();
        }

        CustomerGroupQuery::findDefault()->setRestrictsCategories(1)->save();
    }

    private function category(int $categoryId): Category
    {
        return CategoryQuery::create()->findPk($categoryId) ?? throw new \RuntimeException('No category '.$categoryId);
    }

    private function product(Category|int $category): Product
    {
        return $this->factory->product(\is_int($category) ? $this->category($category) : $category, $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 10]);
    }

    private function customerOf(?CustomerGroup $group): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), null === $group ? [] : ['customerGroup' => $group]);
    }

    private function signIn(Customer $customer): void
    {
        $this->session()->setCustomerUser($customer);
    }

    /**
     * @return list<int>
     */
    private function visibleProductIds(): array
    {
        $query = ProductQuery::create();
        $this->getService(ProductVisibility::class)->applyTo($query, ProductTableMap::COL_ID);

        return array_map('intval', $query->select('Id')->find()->toArray());
    }

    private function resourceCache(?ProductVisibility $productVisibility = null): ResourceCache
    {
        return new ResourceCache(
            new ArrayAdapter(),
            true,
            60,
            ['/api/front/products', '/api/front/product_images', '/api/front/categories', '/api/front/countries'],
            ['/api/front/products'],
            $this->getService(PricingActivityChecker::class),
            $this->getService(CatalogActivityProbe::class),
            $this->getService(CustomerGroupResolver::class),
            productVisibility: $productVisibility ?? $this->getService(ProductVisibility::class),
            categoryVisibility: $this->categoryVisibility,
            currentCustomerProvider: $this->getService(CurrentCustomerProvider::class),
        );
    }

    private function session(): Session
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}
