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

namespace Thelia\Tests\Integration\Domain\Catalog\Restriction;

use Propel\Runtime\Propel;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Catalog\Category\CategoryVisibility;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Restriction\CatalogRestrictionMode;
use Thelia\Domain\Catalog\Restriction\CatalogRestrictionResolver;
use Thelia\Domain\Catalog\Restriction\CatalogRestrictionRule;
use Thelia\Model\Brand;
use Thelia\Model\CatalogRestriction;
use Thelia\Model\Category;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * What the catalog restrictions of a customer open and close: a deny rule closes
 * its target and wins over any allow rule; a rule on a category covers its sub-tree; as soon as
 * a customer has one allow rule, whatever no allow rule opens is closed, the products filed
 * nowhere included; the rules of the group and the customer's own add up; and the whole is
 * cumulated, by intersection, with the categories a customer group lists (#122).
 *
 * The tree of the examples: Cat1 > Cat1a, Cat2, Cat3.
 */
final class CatalogRestrictionTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private FixtureFactory $factory;

    /** @var array<string, Category> */
    private array $categories;

    /** @var array<string, Product> */
    private array $products;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();

        $cat1 = $this->factory->category();
        $this->categories = [
            'cat1' => $cat1,
            'cat1a' => $this->factory->category(['parent' => $cat1->getId()]),
            'cat2' => $this->factory->category(),
            'cat3' => $this->factory->category(),
        ];
        $this->products = [
            'inCat1' => $this->product($this->categories['cat1']),
            'inCat1a' => $this->product($this->categories['cat1a']),
            'inCat2' => $this->product($this->categories['cat2']),
            'inCat3' => $this->product($this->categories['cat3']),
            'filedNowhere' => $this->product($this->categories['cat3']),
        ];
        ProductCategoryQuery::create()->filterByProductId($this->products['filedNowhere']->getId())->delete();
        $this->forget();
    }

    protected function tearDown(): void
    {
        $this->session()->setCustomerUser(null);
        $this->forget();

        parent::tearDown();
    }

    public function testACustomerWithNoRuleIsNarrowedNowhere(): void
    {
        $customer = $this->customerOf($this->factory->customerGroup());

        self::assertNull($this->getService(CatalogRestrictionResolver::class)->scopeFor($customer));
        self::assertNull($this->getService(CatalogRestrictionRule::class)->visibleProductClause('product.id', $customer));
        self::assertSame([], $this->getService(CatalogRestrictionResolver::class)->closedCategoryIdsFor($customer));
        self::assertSame(array_values($this->idsOf(...array_keys($this->products))), $this->visibleIds($customer));
    }

    public function testTheRulesOfACustomerAreReadInOneStatementForTheRequest(): void
    {
        $group = $this->factory->customerGroup();
        $this->allow($group, category: $this->categories['cat1']);
        $customer = $this->customerOf($group);
        $this->forget();

        $statements = $this->recordSqlQueries(function () use ($customer): void {
            $rule = $this->getService(CatalogRestrictionRule::class);
            $rule->visibleProductClause('product.id', $customer);
            $rule->visibleProductClause('product_sale_elements.product_id', $customer);
            $this->getService(CategoryVisibility::class)->closedCategoryIdsFor($customer);
        });

        // The existence probe of the front is the other statement that names the table.
        $readingTheRules = array_filter($statements, static fn (string $statement): bool => !str_contains($statement, 'AS catalog_restrictions'));

        self::assertSame(1, self::countSqlQueriesSelectingFrom(array_values($readingTheRules), 'catalog_restriction'));
    }

    /**
     * Example: allowed {Cat1, Cat2}, so Cat1, its sub-category and Cat2 and their
     * products, nothing else, and not the product filed nowhere.
     */
    public function testAnAllowListOpensTheTargetsAndTheirSubTreesAndClosesTheRest(): void
    {
        $group = $this->factory->customerGroup();
        $this->allow($group, category: $this->categories['cat1']);
        $this->allow($group, category: $this->categories['cat2']);
        $customer = $this->customerOf($group);

        self::assertSame($this->idsOf('inCat1', 'inCat1a', 'inCat2'), $this->visibleIds($customer));
        self::assertSame([(int) $this->categories['cat3']->getId()], $this->closedCategoriesOf($customer, 'cat1', 'cat1a', 'cat2', 'cat3'));
    }

    public function testADenyRuleBeatsAnAllowRuleAndCoversASubTree(): void
    {
        $group = $this->factory->customerGroup();
        $this->allow($group, category: $this->categories['cat1']);
        $this->deny($group, category: $this->categories['cat1a']);
        $customer = $this->customerOf($group);

        self::assertSame($this->idsOf('inCat1'), $this->visibleIds($customer));

        $this->deny($group, category: $this->categories['cat1']);
        $this->forget();

        self::assertSame([], $this->visibleIds($customer), 'Deny on the parent closes the sub-category an allow rule opened.');
    }

    public function testADenyOnlyListClosesItsTargetsAndLeavesTheRestOpenFiledNowhereIncluded(): void
    {
        $group = $this->factory->customerGroup();
        $this->deny($group, category: $this->categories['cat2']);
        $customer = $this->customerOf($group);

        self::assertSame($this->idsOf('inCat1', 'inCat1a', 'inCat3', 'filedNowhere'), $this->visibleIds($customer));
    }

    public function testAProductAndABrandOpenWhatNoCategoryOpensTheFiledNowhereOneIncluded(): void
    {
        $group = $this->factory->customerGroup();
        $brand = $this->factory->brand();
        $this->products['filedNowhere']->setBrandId($brand->getId())->save($this->getPropelConnection());
        $this->allow($group, product: $this->products['inCat3']);
        $this->allow($group, brand: $brand);
        $customer = $this->customerOf($group);

        self::assertSame($this->idsOf('inCat3', 'filedNowhere'), $this->visibleIds($customer));
        self::assertSame(
            [(int) $this->categories['cat1']->getId(), (int) $this->categories['cat1a']->getId(), (int) $this->categories['cat2']->getId()],
            $this->closedCategoriesOf($customer, 'cat1', 'cat1a', 'cat2', 'cat3'),
            'Cat3 files an allowed product and stays open, the others are closed.',
        );
    }

    public function testADeniedBrandAndADeniedProductAreClosed(): void
    {
        $group = $this->factory->customerGroup();
        $brand = $this->factory->brand();
        $this->products['inCat1']->setBrandId($brand->getId())->save($this->getPropelConnection());
        $this->deny($group, brand: $brand);
        $this->deny($group, product: $this->products['inCat3']);
        $customer = $this->customerOf($group);

        self::assertSame($this->idsOf('inCat1a', 'inCat2', 'filedNowhere'), $this->visibleIds($customer));
    }

    public function testTheAncestorsOfAnOpenCategoryStayPassingAndItsSiblingsAreClosed(): void
    {
        $group = $this->factory->customerGroup();
        $this->allow($group, category: $this->categories['cat1a']);
        $customer = $this->customerOf($group);

        self::assertSame(
            [(int) $this->categories['cat2']->getId(), (int) $this->categories['cat3']->getId()],
            $this->closedCategoriesOf($customer, 'cat1', 'cat1a', 'cat2', 'cat3'),
            'Cat1 is the ancestor of the open category: passing, not closed.',
        );
        self::assertSame($this->idsOf('inCat1a'), $this->visibleIds($customer));
    }

    public function testTheRulesOfTheGroupAndTheOnesOfTheCustomerAddUp(): void
    {
        $group = $this->factory->customerGroup();
        $this->allow($group, category: $this->categories['cat1']);
        $customer = $this->customerOf($group);
        $other = $this->customerOf($group);
        $this->allow(null, category: $this->categories['cat2'], customer: $customer);
        $this->deny(null, product: $this->products['inCat1a'], customer: $customer);

        self::assertSame($this->idsOf('inCat1', 'inCat2'), $this->visibleIds($customer));
        self::assertSame($this->idsOf('inCat1', 'inCat1a'), $this->visibleIds($other), 'The rules of a customer are not the group\'s.');
    }

    /**
     * #122 and the restrictions are two doors in a row: a category is browsable and a product
     * visible when both let it through.
     */
    public function testTheCategoriesOfTheGroupAreCumulatedWithTheRestrictionsByIntersection(): void
    {
        $listing = $this->factory->customerGroup('listing', categoryIds: [(int) $this->categories['cat1']->getId(), (int) $this->categories['cat2']->getId()]);
        $this->deny($listing, category: $this->categories['cat1a']);
        $customer = $this->customerOf($listing);
        $this->forget();

        self::assertSame(
            $this->idsOf('inCat1', 'inCat2', 'filedNowhere'),
            $this->visibleIds($customer),
            'Cat3 by #122, Cat1a by the deny rule; a product filed nowhere is no concern of either list.',
        );

        $closed = $this->getService(CategoryVisibility::class)->closedCategoryIdsFor($customer);
        self::assertContains((int) $this->categories['cat3']->getId(), $closed, 'Closed by the list of the group.');
        self::assertContains((int) $this->categories['cat1a']->getId(), $closed, 'Closed by the restriction.');
        self::assertNotContains((int) $this->categories['cat1']->getId(), $closed);
        self::assertNotContains((int) $this->categories['cat2']->getId(), $closed);
    }

    public function testAnAllowRuleCannotOpenWhatTheListOfTheGroupClosed(): void
    {
        $listing = $this->factory->customerGroup('listing', categoryIds: [(int) $this->categories['cat2']->getId()]);
        $this->allow($listing, category: $this->categories['cat1']);
        $customer = $this->customerOf($listing);
        $this->forget();

        self::assertSame([], $this->visibleIds($customer));
    }

    /**
     * The shape of the clause is part of the contract of the rule: every OR is parenthesised
     * before it is joined to the denies, the tables of the subqueries are aliased, nothing is bound.
     */
    public function testTheClauseParenthesisesItsDisjunctionAndAliasesItsSubqueries(): void
    {
        $group = $this->factory->customerGroup();
        $this->allow($group, category: $this->categories['cat2']);
        $this->allow($group, product: $this->products['inCat3']);
        $this->deny($group, product: $this->products['inCat1']);
        $customer = $this->customerOf($group);

        $clause = $this->getService(CatalogRestrictionRule::class)->visibleProductClause('product.id', $customer);

        self::assertSame(
            \sprintf(
                '((product.id IN (%1$d) OR EXISTS (SELECT 1 FROM product_category allowed_product_category WHERE allowed_product_category.product_id = product.id AND allowed_product_category.category_id IN (%2$d))) AND product.id NOT IN (%3$d))',
                $this->products['inCat3']->getId(),
                $this->categories['cat2']->getId(),
                $this->products['inCat1']->getId(),
            ),
            $clause,
        );
        self::assertSame(substr_count((string) $clause, '('), substr_count((string) $clause, ')'));
        self::assertStringNotContainsString('?', (string) $clause);
    }

    /**
     * A listing by category narrowed by an allow list: every subquery of the plan is read through
     * a key, never a scan of product_category.
     */
    public function testTheListingOfACategoryReadsTheIndexesOfTheRestrictionSubqueries(): void
    {
        $group = $this->factory->customerGroup();
        $this->allow($group, category: $this->categories['cat1']);
        $this->deny($group, category: $this->categories['cat1a']);
        $this->deny($group, brand: $this->factory->brand());
        $customer = $this->customerOf($group);
        $clause = $this->getService(CatalogRestrictionRule::class)->visibleProductClause('product.id', $customer);

        $connection = Propel::getConnection();
        $statement = $connection->prepare(\sprintf(
            'EXPLAIN SELECT product.id FROM product INNER JOIN product_category listed ON listed.product_id = product.id WHERE listed.category_id = %d AND %s',
            $this->categories['cat1']->getId(),
            $clause,
        ));
        $statement->execute();
        $plan = $statement->fetchAll(\PDO::FETCH_ASSOC);

        // Whatever the optimizer makes of them (dependent subqueries, or materialised semi-joins).
        $subqueryRows = array_filter($plan, static fn (array $row): bool => 1 === preg_match('/^(allowed|denied)_/', (string) $row['table']));
        self::assertCount(3, $subqueryRows, 'The plan holds the three subqueries of the rules: '.json_encode($plan));

        foreach ($subqueryRows as $row) {
            self::assertNotNull($row['key'], \sprintf('Table %s of a subquery is scanned: %s', $row['table'], json_encode($plan)));
            self::assertNotSame('ALL', $row['type'], json_encode($plan));
        }
    }

    public function testNoRuleAddsNoCriterionToTheProductQuery(): void
    {
        $customer = $this->customerOf($this->factory->customerGroup());
        $this->session()->setCustomerUser($customer);

        self::assertNull($this->getService(ProductVisibility::class)->visibleProductClause('product.id'));
    }

    /**
     * @return list<int>
     */
    private function idsOf(string ...$names): array
    {
        $ids = array_map(fn (string $name): int => (int) $this->products[$name]->getId(), $names);
        sort($ids);

        return $ids;
    }

    /**
     * The products among the fixtures the customer sees, through ProductVisibility as every reader asks it.
     *
     * @return list<int>
     */
    private function visibleIds(Customer $customer): array
    {
        $this->session()->setCustomerUser($customer);
        $this->forget();

        $query = ProductQuery::create()->filterById(array_map(static fn (Product $product): int => (int) $product->getId(), $this->products));
        $this->getService(ProductVisibility::class)->applyTo($query, ProductTableMap::COL_ID);

        $ids = array_map('intval', $query->select('Id')->find()->toArray());
        sort($ids);

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function closedCategoriesOf(Customer $customer, string ...$among): array
    {
        $closed = $this->getService(CategoryVisibility::class)->closedCategoryIdsFor($customer);
        $closed = array_values(array_filter($closed, fn (int $id): bool => \in_array($id, array_map(fn (string $name): int => (int) $this->categories[$name]->getId(), $among), true)));
        sort($closed);

        return $closed;
    }

    private function product(Category $category): Product
    {
        return $this->factory->product($category, $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 10]);
    }

    private function allow(?CustomerGroup $group, ?Category $category = null, ?Brand $brand = null, ?Product $product = null, ?Customer $customer = null): void
    {
        $this->write(CatalogRestrictionMode::Allow, $group, $customer, $category, $brand, $product);
    }

    private function deny(?CustomerGroup $group, ?Category $category = null, ?Brand $brand = null, ?Product $product = null, ?Customer $customer = null): void
    {
        $this->write(CatalogRestrictionMode::Deny, $group, $customer, $category, $brand, $product);
    }

    private function write(CatalogRestrictionMode $mode, ?CustomerGroup $group, ?Customer $customer, ?Category $category, ?Brand $brand, ?Product $product): void
    {
        (new CatalogRestriction())
            ->setMode($mode->value)
            ->setCustomerGroupId($group?->getId())
            ->setCustomerId($customer?->getId())
            ->setCategoryId($category?->getId())
            ->setBrandId($brand?->getId())
            ->setProductId($product?->getId())
            ->save($this->getPropelConnection());

        $this->forget();
    }

    private function customerOf(CustomerGroup $group): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
    }

    private function forget(): void
    {
        $this->getService('services_resetter')->reset();
    }

    private function session(): Session
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}
