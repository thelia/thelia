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

use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Restriction\CatalogRestrictionMode;
use Thelia\Model\Brand;
use Thelia\Model\CatalogRestriction;
use Thelia\Model\Category;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The front API follows the catalog restrictions of the caller: the
 * professionals are allowed Cat1 and Cat2 only. On the tree Cat1 > Cat1a, Cat2, Cat3, a product
 * is read through Cat1, Cat1a (a sub-category follows its parent) or Cat2 and is a 404 anywhere
 * else, the one filed nowhere included, in the collection, in the search, on its item, on its
 * sale elements and on the categories. A caller with no rule, and an anonymous visitor, read it
 * all. A product left out is a 404, never a 403: the answer does not tell it exists.
 */
final class CatalogRestrictionApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    /** @var array<string, Category> */
    private array $categories;

    /** @var array<string, Product> */
    private array $products;

    private CustomerGroup $pro;

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
            'inCat1' => $this->product($this->categories['cat1'], 'in cat1'),
            'inCat1a' => $this->product($this->categories['cat1a'], 'in cat1a'),
            'inCat2' => $this->product($this->categories['cat2'], 'in cat2'),
            'inCat3' => $this->product($this->categories['cat3'], 'in cat3'),
            'filedNowhere' => $this->product($this->categories['cat3'], 'filed nowhere'),
        ];
        ProductCategoryQuery::create()->filterByProductId($this->products['filedNowhere']->getId())->delete();

        $this->pro = $this->factory->customerGroup('pro');
        $this->restrict($this->pro, CatalogRestrictionMode::Allow, category: $this->categories['cat1']);
        $this->restrict($this->pro, CatalogRestrictionMode::Allow, category: $this->categories['cat2']);
        $this->getService('services_resetter')->reset();
    }

    protected function tearDown(): void
    {
        $this->getService('services_resetter')->reset();

        parent::tearDown();
    }

    public function testTheCollectionAndTheSearchHoldTheAllowedProductsOnly(): void
    {
        $token = $this->tokenOf($this->pro);
        $expected = $this->idsOf('inCat1', 'inCat1a', 'inCat2');

        self::assertEqualsCanonicalizing($expected, $this->memberIds('/api/front/products?itemsPerPage=30&title=Restrict', $token), 'search');
        self::assertEqualsCanonicalizing($expected, $this->memberIds('/api/front/products?itemsPerPage=30&title=Restrict', $token), 'collection');
    }

    public function testAProductOutsideTheAssortmentIsANotFoundOnItsItemAndOnItsSaleElements(): void
    {
        $token = $this->tokenOf($this->pro);

        foreach (['inCat1', 'inCat1a', 'inCat2'] as $name) {
            self::assertSame(200, $this->jsonRequest('GET', '/api/front/products/'.$this->products[$name]->getId(), token: $token)->getStatusCode(), $name);
            self::assertSame(200, $this->jsonRequest('GET', '/api/front/product_sale_elements/'.$this->products[$name]->getDefaultSaleElements()->getId(), token: $token)->getStatusCode(), $name.' sale element');
        }

        foreach (['inCat3', 'filedNowhere'] as $name) {
            $response = $this->jsonRequest('GET', '/api/front/products/'.$this->products[$name]->getId(), token: $token);
            self::assertSame(404, $response->getStatusCode(), $name.': never a 403');
            self::assertSame(404, $this->jsonRequest('GET', '/api/front/product_sale_elements/'.$this->products[$name]->getDefaultSaleElements()->getId(), token: $token)->getStatusCode(), $name.' sale element');
        }
    }

    public function testTheCategoriesOutsideTheAssortmentAreGone(): void
    {
        $token = $this->tokenOf($this->pro);

        self::assertEqualsCanonicalizing(
            [(int) $this->categories['cat1']->getId(), (int) $this->categories['cat1a']->getId(), (int) $this->categories['cat2']->getId()],
            $this->memberIds('/api/front/categories?itemsPerPage=30', $token),
        );
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/categories/'.$this->categories['cat3']->getId(), token: $token)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/categories/'.$this->categories['cat1a']->getId(), token: $token)->getStatusCode());
    }

    public function testACustomerWithNoRuleAndAnAnonymousVisitorReadEverything(): void
    {
        foreach (['no rule' => $this->tokenOf(null), 'anonymous' => null] as $who => $token) {
            $ids = $this->memberIds('/api/front/products?itemsPerPage=30&title=Restrict', $token);

            self::assertEqualsCanonicalizing($this->idsOf('inCat1', 'inCat1a', 'inCat2', 'inCat3', 'filedNowhere'), $ids, $who);
            self::assertSame(200, $this->jsonRequest('GET', '/api/front/products/'.$this->products['inCat3']->getId(), token: $token)->getStatusCode(), $who);
        }
    }

    public function testADenyRuleBeatsAnAllowRuleOfTheSameBranchOfTheTree(): void
    {
        $this->restrict($this->pro, CatalogRestrictionMode::Deny, category: $this->categories['cat1a']);
        $this->getService('services_resetter')->reset();
        $token = $this->tokenOf($this->pro);

        self::assertEqualsCanonicalizing($this->idsOf('inCat1', 'inCat2'), $this->memberIds('/api/front/products?itemsPerPage=30&title=Restrict', $token));
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/products/'.$this->products['inCat1a']->getId(), token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/categories/'.$this->categories['cat1a']->getId(), token: $token)->getStatusCode());
    }

    public function testADeniedBrandIsOutOfTheCollectionAndItsProductsAreNotFound(): void
    {
        $group = $this->factory->customerGroup('buyers');
        $hidden = $this->factory->brand(['title' => 'Hidden brand']);
        $shown = $this->factory->brand(['title' => 'Shown brand']);
        $this->products['inCat1']->setBrandId($hidden->getId())->save($this->getPropelConnection());
        $this->products['inCat2']->setBrandId($shown->getId())->save($this->getPropelConnection());
        $this->restrict($group, CatalogRestrictionMode::Deny, brand: $hidden);
        $this->getService('services_resetter')->reset();
        $token = $this->tokenOf($group);

        $brandIds = $this->memberIds('/api/front/brands?itemsPerPage=30', $token);

        self::assertContains((int) $shown->getId(), $brandIds);
        self::assertNotContains((int) $hidden->getId(), $brandIds);
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/brands/'.$hidden->getId(), token: $token)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/brands/'.$shown->getId(), token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/products/'.$this->products['inCat1']->getId(), token: $token)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/products/'.$this->products['inCat2']->getId(), token: $token)->getStatusCode());
    }

    public function testAnAllowListOnBrandsClosesTheOtherBrands(): void
    {
        $group = $this->factory->customerGroup('brand-buyers');
        $listed = $this->factory->brand(['title' => 'Listed brand']);
        $other = $this->factory->brand(['title' => 'Other brand']);
        $this->products['inCat3']->setBrandId($listed->getId())->save($this->getPropelConnection());
        $this->restrict($group, CatalogRestrictionMode::Allow, brand: $listed);
        $this->getService('services_resetter')->reset();
        $token = $this->tokenOf($group);

        $brandIds = $this->memberIds('/api/front/brands?itemsPerPage=30', $token);

        self::assertContains((int) $listed->getId(), $brandIds);
        self::assertNotContains((int) $other->getId(), $brandIds);
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/brands/'.$other->getId(), token: $token)->getStatusCode());
        self::assertSame($this->idsOf('inCat3'), $this->memberIds('/api/front/products?itemsPerPage=30&title=Restrict', $token));
    }

    public function testTheBrandOfAnOpenProductIsListedWhateverTheAllowList(): void
    {
        $group = $this->factory->customerGroup('tools-buyers');
        $bosch = $this->factory->brand(['title' => 'Bosch']);
        $makita = $this->factory->brand(['title' => 'Makita']);
        $noProduct = $this->factory->brand(['title' => 'No product']);
        $this->products['inCat1']->setBrandId($bosch->getId())->save($this->getPropelConnection());
        $this->products['inCat3']->setBrandId($makita->getId())->save($this->getPropelConnection());
        $this->restrict($group, CatalogRestrictionMode::Allow, category: $this->categories['cat1']);
        $this->getService('services_resetter')->reset();
        $token = $this->tokenOf($group);

        $brandIds = $this->memberIds('/api/front/brands?itemsPerPage=50', $token);

        self::assertContains((int) $bosch->getId(), $brandIds, 'the brand of an open product is listed');
        self::assertNotContains((int) $makita->getId(), $brandIds, 'the brand of closed products only is not');
        self::assertNotContains((int) $noProduct->getId(), $brandIds, 'a brand with no product is not');
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/brands/'.$bosch->getId(), token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/brands/'.$makita->getId(), token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/brands/'.$noProduct->getId(), token: $token)->getStatusCode());
        self::assertSame($this->idsOf('inCat1', 'inCat1a'), $this->memberIds('/api/front/products?itemsPerPage=30&title=Restrict', $token), 'the products stay the open ones');
    }

    /**
     * The only Makita product of an allowed category is offline: it opens nothing, Makita stays closed
     * (listing and item), where Bosch, which carries an online open product, is listed.
     */
    public function testABrandWhoseOnlyProductIsOfflineStaysClosed(): void
    {
        $group = $this->factory->customerGroup('tools-buyers-offline');
        $bosch = $this->factory->brand(['title' => 'Bosch']);
        $makita = $this->factory->brand(['title' => 'Makita']);
        $this->products['inCat1']->setBrandId($bosch->getId())->save($this->getPropelConnection());
        $this->products['inCat1a']->setBrandId($makita->getId())->setVisible(0)->save($this->getPropelConnection());
        $this->restrict($group, CatalogRestrictionMode::Allow, category: $this->categories['cat1']);
        $this->getService('services_resetter')->reset();
        $token = $this->tokenOf($group);

        $brandIds = $this->memberIds('/api/front/brands?itemsPerPage=50', $token);

        self::assertContains((int) $bosch->getId(), $brandIds);
        self::assertNotContains((int) $makita->getId(), $brandIds, 'the only product of the brand is offline');
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/brands/'.$bosch->getId(), token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/brands/'.$makita->getId(), token: $token)->getStatusCode());
    }

    public function testADeniedBrandStaysClosedEvenWithAnOpenProduct(): void
    {
        $group = $this->factory->customerGroup('tools-buyers-without-bosch');
        $bosch = $this->factory->brand(['title' => 'Bosch']);
        $this->products['inCat1']->setBrandId($bosch->getId())->save($this->getPropelConnection());
        $this->restrict($group, CatalogRestrictionMode::Allow, category: $this->categories['cat1']);
        $this->restrict($group, CatalogRestrictionMode::Deny, brand: $bosch);
        $this->getService('services_resetter')->reset();
        $token = $this->tokenOf($group);

        self::assertNotContains((int) $bosch->getId(), $this->memberIds('/api/front/brands?itemsPerPage=50', $token));
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/brands/'.$bosch->getId(), token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/products/'.$this->products['inCat1']->getId(), token: $token)->getStatusCode());
    }

    public function testWithNoRestrictionEveryBrandIsListed(): void
    {
        $bosch = $this->factory->brand(['title' => 'Bosch']);
        $noProduct = $this->factory->brand(['title' => 'No product']);
        $this->products['inCat3']->setBrandId($bosch->getId())->save($this->getPropelConnection());

        foreach (['no rule' => $this->tokenOf(null), 'anonymous' => null] as $who => $token) {
            $brandIds = $this->memberIds('/api/front/brands?itemsPerPage=50', $token);

            self::assertContains((int) $bosch->getId(), $brandIds, $who);
            self::assertContains((int) $noProduct->getId(), $brandIds, $who);
        }
    }

    public function testTheRuleOfACustomerAloneNarrowsThatCustomerOnly(): void
    {
        $group = $this->factory->customerGroup('plain');
        $alone = $this->customerOf($group);
        $other = $this->customerOf($group);
        $this->restrictCustomer($alone, CatalogRestrictionMode::Deny, product: $this->products['inCat2']);
        $this->getService('services_resetter')->reset();

        self::assertSame(404, $this->jsonRequest('GET', '/api/front/products/'.$this->products['inCat2']->getId(), token: $this->authenticateAsCustomer($alone))->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/products/'.$this->products['inCat2']->getId(), token: $this->authenticateAsCustomer($other))->getStatusCode());
    }

    public function testTheSitemapOfAnAnonymousVisitorFollowsTheRulesOfTheDefaultGroup(): void
    {
        $this->restrict(CustomerGroupQuery::findDefault(), CatalogRestrictionMode::Allow, category: $this->categories['cat2']);
        $this->getService('services_resetter')->reset();

        $clause = $this->getService(ProductVisibility::class)->visibleProductClauseForDefaultVisitor('product.id');

        self::assertNotNull($clause);
        $ids = array_map('intval', ProductQuery::create()->where($clause)->select('Id')->find()->toArray());
        self::assertSame($this->idsOf('inCat2'), array_values(array_intersect($ids, $this->idsOf(...array_keys($this->products)))));
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

    private function product(Category $category, string $title): Product
    {
        return $this->factory->product($category, $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 10, 'title' => 'Restrict '.$title]);
    }

    private function restrict(CustomerGroup $group, CatalogRestrictionMode $mode, ?Category $category = null, ?Brand $brand = null, ?Product $product = null): void
    {
        $this->write((new CatalogRestriction())->setCustomerGroupId($group->getId()), $mode, $category, $brand, $product);
    }

    private function restrictCustomer(Customer $customer, CatalogRestrictionMode $mode, ?Category $category = null, ?Brand $brand = null, ?Product $product = null): void
    {
        $this->write((new CatalogRestriction())->setCustomerId($customer->getId()), $mode, $category, $brand, $product);
    }

    private function write(CatalogRestriction $rule, CatalogRestrictionMode $mode, ?Category $category, ?Brand $brand, ?Product $product): void
    {
        $rule->setMode($mode->value)
            ->setCategoryId($category?->getId())
            ->setBrandId($brand?->getId())
            ->setProductId($product?->getId())
            ->save($this->getPropelConnection());
    }

    private function customerOf(CustomerGroup $group): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['password' => 'password', 'customerGroup' => $group]);
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
        $ids = array_map(static fn (array $member): int => (int) $member['id'], $payload['hydra:member']);
        sort($ids);

        return $ids;
    }
}
