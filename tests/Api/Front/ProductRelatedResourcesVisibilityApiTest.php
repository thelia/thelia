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
use Thelia\Model\ProductAssociatedContent;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * What a product holds besides its images - the price of a sale element, the attribute values
 * of a sale element, its feature values and its associated contents - is hidden from a customer
 * group with the product: 404 on the item, out of the collection. A product is hidden here from
 * group A by the only category it sits in, off the list of group A; the default group reads it.
 */
final class ProductRelatedResourcesVisibilityApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    private CustomerGroup $groupA;

    private Product $hidden;

    private Product $shown;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();

        $listed = $this->factory->category();
        $this->groupA = $this->factory->customerGroup('related-group-a', categoryIds: [(int) $listed->getId()]);
        $this->getService('services_resetter')->reset();

        $this->hidden = $this->productIn($this->factory->category());
        $this->shown = $this->productIn($listed);
    }

    protected function tearDown(): void
    {
        $this->getService('services_resetter')->reset();

        parent::tearDown();
    }

    public function testThePriceOfASaleElementOfAHiddenProductIsNotFound(): void
    {
        $uri = fn (Product $product): string => '/api/front/product_prices/'.$product->getDefaultSaleElements()->getId().'/currencies/'.$this->factory->currency()->getId();
        $tokenA = $this->tokenOf($this->groupA);

        self::assertSame(404, $this->jsonRequest('GET', $uri($this->hidden), token: $tokenA)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', $uri($this->shown), token: $tokenA)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', $uri($this->hidden), token: $this->tokenOf(null))->getStatusCode(), 'the default group has no list');
    }

    public function testTheAttributeValueOfASaleElementOfAHiddenProductIsNotFound(): void
    {
        $attributeAv = $this->factory->attributeAv($this->factory->attribute());
        $uri = function (Product $product) use ($attributeAv): string {
            $saleElements = $product->getDefaultSaleElements();
            $this->factory->attributeCombination($saleElements, $attributeAv);

            return '/api/front/attribute_combinations/'.$saleElements->getId().'/attribute_av/'.$attributeAv->getId();
        };
        $hiddenUri = $uri($this->hidden);
        $shownUri = $uri($this->shown);
        $tokenA = $this->tokenOf($this->groupA);

        self::assertSame(404, $this->jsonRequest('GET', $hiddenUri, token: $tokenA)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', $shownUri, token: $tokenA)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', $hiddenUri, token: $this->tokenOf(null))->getStatusCode());
    }

    public function testTheFeatureValuesOfAHiddenProductLeaveTheCollectionAndTheirItemIsNotFound(): void
    {
        $featureAv = $this->factory->featureAv($this->factory->feature());
        $ofHidden = (int) $this->factory->featureProduct($this->hidden, $featureAv)->getId();
        $ofShown = (int) $this->factory->featureProduct($this->shown, $featureAv)->getId();
        $tokenA = $this->tokenOf($this->groupA);

        $ids = $this->memberIds('/api/front/feature_products', $tokenA);
        self::assertNotContains($ofHidden, $ids);
        self::assertContains($ofShown, $ids);
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/feature_products/'.$ofHidden, token: $tokenA)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/feature_products/'.$ofShown, token: $tokenA)->getStatusCode());

        self::assertContains($ofHidden, $this->memberIds('/api/front/feature_products', $this->tokenOf(null)));
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/feature_products/'.$ofHidden, token: $this->tokenOf(null))->getStatusCode());
    }

    public function testTheAssociatedContentOfAHiddenProductIsNotFound(): void
    {
        $content = $this->factory->content($this->factory->folder());
        $ofHidden = $this->associate($this->hidden, $content->getId());
        $ofShown = $this->associate($this->shown, $content->getId());
        $tokenA = $this->tokenOf($this->groupA);

        self::assertSame(404, $this->jsonRequest('GET', '/api/front/product_associated_contents/'.$ofHidden, token: $tokenA)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/product_associated_contents/'.$ofShown, token: $tokenA)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/product_associated_contents/'.$ofHidden, token: $this->tokenOf(null))->getStatusCode());
    }

    private function associate(Product $product, int $contentId): int
    {
        $link = new ProductAssociatedContent();
        $link->setProductId($product->getId());
        $link->setContentId($contentId);
        $link->setPosition(1);
        $link->save($this->getPropelConnection());

        return (int) $link->getId();
    }

    private function productIn(Category $category): Product
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
    private function memberIds(string $uri, string $token): array
    {
        $response = $this->jsonRequest('GET', $uri, token: $token);
        self::assertJsonResponseSuccessful($response);

        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return array_map(static fn (array $member): int => (int) $member['id'], $payload['hydra:member']);
    }
}
