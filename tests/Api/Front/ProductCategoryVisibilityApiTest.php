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
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The `productCategories` a product embeds on the front must not reveal a category closed
 * to the group of the reader: a product kept visible by a listed category does not hand out
 * the closed one it also sits in. The passing category, an ancestor of a listed one, is
 * revealed: the group browses it. The default group has no list and reads every link.
 */
final class ProductCategoryVisibilityApiTest extends ApiTestCase
{
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

    public function testAClosedCategoryIsNotRevealedByTheProductCategoriesOfAProduct(): void
    {
        [$passing, $listed, $closed, $pro, $product] = $this->productInAListedAPassingAndAClosedCategory();

        foreach (['/api/front/products/'.$product->getId(), '/api/front/products?id='.$product->getId()] as $uri) {
            $revealed = $this->categoryIdsEmbeddedBy($uri, $this->tokenOf($pro));

            self::assertContains((int) $listed->getId(), $revealed, $uri);
            self::assertContains((int) $passing->getId(), $revealed, $uri);
            self::assertNotContains((int) $closed->getId(), $revealed, $uri);
        }
    }

    public function testTheDefaultGroupWithNoListSeesEveryCategoryInTheProductCategories(): void
    {
        [$passing, $listed, $closed, , $product] = $this->productInAListedAPassingAndAClosedCategory();

        $revealed = $this->categoryIdsEmbeddedBy('/api/front/products/'.$product->getId(), $this->tokenOf(null));

        self::assertEqualsCanonicalizing([(int) $passing->getId(), (int) $listed->getId(), (int) $closed->getId()], $revealed);
    }

    public function testTheLinkToAClosedCategoryIsNotFoundForTheGroupAndReadByTheDefaultGroup(): void
    {
        [$passing, $listed, $closed, $pro, $product] = $this->productInAListedAPassingAndAClosedCategory();
        $uri = static fn (Category $category): string => \sprintf('/api/front/product_categories/%d/categories/%d', $product->getId(), $category->getId());
        $consumerToken = $this->tokenOf(null);
        $proToken = $this->tokenOf($pro);

        self::assertSame(404, $this->jsonRequest('GET', $uri($closed), token: $proToken)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', $uri($listed), token: $proToken)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', $uri($passing), token: $proToken)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', $uri($closed), token: $consumerToken)->getStatusCode());
    }

    /**
     * @return array{0: Category, 1: Category, 2: Category, 3: CustomerGroup, 4: Product}
     */
    private function productInAListedAPassingAndAClosedCategory(): array
    {
        $passing = $this->factory->category();
        $listed = $this->factory->category(['parent' => $passing->getId()]);
        $closed = $this->factory->category();
        $pro = $this->factory->customerGroup('pro', categoryIds: [(int) $listed->getId()]);
        $this->getService('services_resetter')->reset();

        $product = $this->factory->product($listed, $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 10]);
        $this->factory->productCategory($product, $passing);
        $this->factory->productCategory($product, $closed);

        return [$passing, $listed, $closed, $pro, $product];
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
     * @return list<int> the ids of the categories the first product of the answer embeds
     */
    private function categoryIdsEmbeddedBy(string $uri, string $token): array
    {
        $response = $this->jsonRequest('GET', $uri, token: $token);
        self::assertJsonResponseSuccessful($response);

        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $product = $payload['hydra:member'][0] ?? $payload;

        $ids = [];
        foreach ($product['productCategories'] ?? [] as $productCategory) {
            $category = $productCategory['category'] ?? null;
            $iri = \is_array($category) ? ($category['@id'] ?? '') : (string) $category;
            $ids[] = (int) basename($iri);
        }

        return $ids;
    }
}
