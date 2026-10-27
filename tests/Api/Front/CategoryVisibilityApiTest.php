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

namespace Thelia\Tests\Api\Front;

use Thelia\Model\Category;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupCategory;
use Thelia\Model\Product;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The front API follows the list of the group of the customer. On the tree Living Room >
 * [Armchairs, Sofas], Dining Room > [Chairs, Stools], the professionals list Sofas: Sofas is
 * open, Living Room is passing (in the collection, its item answers, its own products are
 * hidden unless Sofas holds them too), every other category is closed (out of the collection,
 * 404 on its item, its products hidden). The default group, and an anonymous visitor who
 * belongs to it, have no list and read everything. The admin API is not narrowed.
 */
final class CategoryVisibilityApiTest extends ApiTestCase
{
    use RecordsSqlQueries;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->getService('services_resetter')->reset();
    }

    protected function tearDown(): void
    {
        $this->getService('services_resetter')->reset();

        parent::tearDown();
    }

    public function testTheProfessionalsReadTheOpenAndThePassingCategoriesOnly(): void
    {
        [$tree, $pro] = $this->furnitureTreeWithAProfessionalGroup();
        $proToken = $this->tokenOf($pro);

        self::assertEqualsCanonicalizing(
            [(int) $tree['livingRoom']->getId(), (int) $tree['sofas']->getId()],
            $this->memberIds('/api/front/categories', $proToken),
        );

        foreach (['livingRoom', 'sofas'] as $name) {
            self::assertSame(200, $this->jsonRequest('GET', '/api/front/categories/'.$tree[$name]->getId(), token: $proToken)->getStatusCode(), $name);
        }

        foreach (['armchairs', 'diningRoom', 'chairs', 'stools'] as $name) {
            self::assertSame(404, $this->jsonRequest('GET', '/api/front/categories/'.$tree[$name]->getId(), token: $proToken)->getStatusCode(), $name);
        }
    }

    public function testTheDefaultGroupAndAnAnonymousVisitorReadEveryCategory(): void
    {
        [$tree] = $this->furnitureTreeWithAProfessionalGroup();

        foreach (['default group' => $this->tokenOf(null), 'anonymous' => null] as $who => $token) {
            self::assertCount(6, $this->memberIds('/api/front/categories', $token), $who);

            foreach ($tree as $name => $category) {
                self::assertSame(200, $this->jsonRequest('GET', '/api/front/categories/'.$category->getId(), token: $token)->getStatusCode(), $who.', '.$name);
            }
        }
    }

    public function testAProductIsVisibleThroughAnOpenCategoryOnly(): void
    {
        [$tree, $pro] = $this->furnitureTreeWithAProfessionalGroup();
        $sofa = $this->product($tree['sofas']);
        $alsoASofa = $this->product($tree['livingRoom']);
        $this->factory->productCategory($alsoASofa, $tree['sofas']);
        $armchair = $this->product($tree['armchairs']);
        $onTheLivingRoomShelf = $this->product($tree['livingRoom']);
        $proToken = $this->tokenOf($pro);

        $ids = $this->memberIds('/api/front/products', $proToken);

        self::assertContains((int) $sofa->getId(), $ids);
        self::assertContains((int) $alsoASofa->getId(), $ids);
        self::assertNotContains((int) $armchair->getId(), $ids, 'A product of a closed category.');
        self::assertNotContains((int) $onTheLivingRoomShelf->getId(), $ids, 'A product filed in the passing category only.');
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/products/'.$alsoASofa->getId(), token: $proToken)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/products/'.$armchair->getId(), token: $proToken)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/products/'.$onTheLivingRoomShelf->getId(), token: $proToken)->getStatusCode());
        self::assertSame(
            404,
            $this->jsonRequest('GET', '/api/front/product_categories/'.$onTheLivingRoomShelf->getId().'/categories/'.$tree['livingRoom']->getId(), token: $proToken)->getStatusCode(),
            'The link of a hidden product to the passing category does not name it.',
        );
        self::assertSame(
            200,
            $this->jsonRequest('GET', '/api/front/product_categories/'.$alsoASofa->getId().'/categories/'.$tree['livingRoom']->getId(), token: $proToken)->getStatusCode(),
        );
    }

    public function testDeletingTheOnlyCategoryTheProfessionalsListShutsTheCatalogToThemAndOpensNothing(): void
    {
        [$tree, $pro] = $this->furnitureTreeWithAProfessionalGroup();
        $armchair = $this->product($tree['armchairs']);
        $onTheLivingRoomShelf = $this->product($tree['livingRoom']);
        $orphan = $this->product($tree['armchairs']);
        ProductCategoryQuery::create()->filterByProductId($orphan->getId())->delete();
        $proToken = $this->tokenOf($pro);
        $consumerToken = $this->tokenOf(null);

        // The administrator deletes Sofas: the list of the professionals is empty, the flag stays.
        $tree['sofas']->delete($this->getPropelConnection());
        $this->getService('services_resetter')->reset();

        self::assertSame([], $this->memberIds('/api/front/categories', $proToken), 'No category is open, so none is passing.');

        foreach (['livingRoom', 'armchairs', 'diningRoom', 'chairs', 'stools'] as $name) {
            self::assertSame(404, $this->jsonRequest('GET', '/api/front/categories/'.$tree[$name]->getId(), token: $proToken)->getStatusCode(), $name);
        }

        $ids = $this->memberIds('/api/front/products', $proToken);
        self::assertNotContains((int) $armchair->getId(), $ids);
        self::assertNotContains((int) $onTheLivingRoomShelf->getId(), $ids);
        self::assertContains((int) $orphan->getId(), $ids, 'A product filed nowhere stays visible.');
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/products/'.$armchair->getId(), token: $proToken)->getStatusCode());

        self::assertCount(5, $this->memberIds('/api/front/categories', $consumerToken), 'The default group still reads every category left.');
    }

    public function testTheProfessionalTheConsumerAndTheAnonymousVisitorAreAnsweredDifferently(): void
    {
        [$tree, $pro] = $this->furnitureTreeWithAProfessionalGroup();
        $sofa = $this->product($tree['sofas']);
        $armchair = $this->product($tree['armchairs']);
        $proToken = $this->tokenOf($pro);
        $consumerToken = $this->tokenOf(null);

        $seenByTheProfessional = $this->memberIds('/api/front/products', $proToken);
        $seenByTheConsumer = $this->memberIds('/api/front/products', $consumerToken);
        $seenByTheVisitor = $this->memberIds('/api/front/products', null);
        $seenAgainByTheProfessional = $this->memberIds('/api/front/products', $proToken);

        self::assertContains((int) $sofa->getId(), $seenByTheProfessional);
        self::assertNotContains((int) $armchair->getId(), $seenByTheProfessional);
        self::assertContains((int) $armchair->getId(), $seenByTheConsumer);
        self::assertContains((int) $sofa->getId(), $seenByTheConsumer);
        self::assertContains((int) $armchair->getId(), $seenByTheVisitor);
        self::assertNotContains((int) $armchair->getId(), $seenAgainByTheProfessional, 'The answer given to one customer is not kept for the next.');
    }

    public function testACombinationOfAProductOfAClosedCategoryCannotBeAddedToTheCart(): void
    {
        [$tree, $pro] = $this->furnitureTreeWithAProfessionalGroup();
        $product = $this->product($tree['armchairs']);

        // The combination the visitor may not see is not found when the request names it
        // (400), before the processor would refuse it (422): either way it is not added.
        foreach ([[$pro, [400, 422]], [null, [201]]] as [$group, $statuses]) {
            $customer = $this->factory->customer(
                $this->factory->customerTitle(),
                ['password' => 'password'] + (null === $group ? [] : ['customerGroup' => $group]),
            );
            $cart = $this->factory->cart($customer);

            $response = $this->jsonRequest('POST', '/api/front/cart_items', [
                'cart' => '/api/front/carts/'.$cart->getId(),
                'productSaleElements' => '/api/front/product_sale_elements/'.$product->getProductSaleElementss()->getFirst()->getId(),
                'quantity' => 1,
            ], $this->authenticateAsCustomer($customer));

            self::assertContains($response->getStatusCode(), $statuses, (string) $response->getContent());
        }
    }

    public function testTheAdminApiIsNotNarrowed(): void
    {
        [$tree] = $this->furnitureTreeWithAProfessionalGroup();

        $response = $this->jsonRequest('GET', '/api/admin/categories/'.$tree['armchairs']->getId(), token: $this->authenticateAsAdmin());

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testTheRestrictionCostsAFixedNumberOfQueriesWhateverTheSizeOfThePage(): void
    {
        $listed = $this->factory->category();
        for ($i = 0; $i < 30; ++$i) {
            $this->product($i % 2 ? $listed : $this->factory->category());
        }
        $pro = $this->factory->customerGroup('pro');
        $token = $this->tokenOf($pro);

        $withoutRestriction = $this->recordSqlQueries(fn () => $this->memberIds('/api/front/products?itemsPerPage=30', $token));

        (new CustomerGroupCategory())->setCustomerGroupId((int) $pro->getId())->setCategoryId((int) $listed->getId())->save();
        $pro->setRestrictsCategories(1)->save($this->getPropelConnection());
        $this->getService('services_resetter')->reset();
        $statements = $this->recordSqlQueries(fn () => $this->memberIds('/api/front/products?itemsPerPage=30', $token));

        // The activity statement of the request says a group is restricted, then the lists are
        // read: once, not once per product.
        self::assertSame(1, self::countSqlQueriesSelectingFrom($statements, 'customer_group_category'), 'The lists are read once, not once per product.');
        self::assertSame(0, self::countSqlQueriesSelectingFrom($withoutRestriction, 'customer_group_category'), 'Without a restricted group, no list is read.');
        self::assertLessThanOrEqual(
            \count($withoutRestriction) + 3,
            \count($statements),
            'The restriction adds a few reads of its own and none per product of the page.',
        );
        self::assertCount(15, $this->memberIds('/api/front/products?itemsPerPage=30', $token), 'The group sees the products of its category only.');
    }

    /**
     * Living Room > [Armchairs, Sofas], Dining Room > [Chairs, Stools], the professionals listing Sofas.
     *
     * @return array{0: array<string, Category>, 1: CustomerGroup}
     */
    private function furnitureTreeWithAProfessionalGroup(): array
    {
        $livingRoom = $this->factory->category();
        $diningRoom = $this->factory->category();
        $tree = [
            'livingRoom' => $livingRoom,
            'diningRoom' => $diningRoom,
            'chairs' => $this->factory->category(['parent' => $diningRoom->getId()]),
            'stools' => $this->factory->category(['parent' => $diningRoom->getId()]),
            'armchairs' => $this->factory->category(['parent' => $livingRoom->getId()]),
            'sofas' => $this->factory->category(['parent' => $livingRoom->getId()]),
        ];
        $pro = $this->factory->customerGroup('pro', categoryIds: [(int) $tree['sofas']->getId()]);
        $this->getService('services_resetter')->reset();

        return [$tree, $pro];
    }

    private function product(Category $category): Product
    {
        return $this->factory->product($category, $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 10]);
    }

    private function tokenOf(?CustomerGroup $group): string
    {
        $customer = $this->factory->customer(
            $this->factory->customerTitle(),
            ['password' => 'password'] + (null === $group ? [] : ['customerGroup' => $group]),
        );

        return $this->authenticateAsCustomer($customer);
    }

    /**
     * @return list<int>
     */
    private function memberIds(string $uri, ?string $token): array
    {
        $response = $this->jsonRequest('GET', $uri, token: $token);
        self::assertJsonResponseSuccessful($response);

        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return array_map(static fn (array $member): int => (int) $member['id'], $payload['hydra:member']);
    }
}
