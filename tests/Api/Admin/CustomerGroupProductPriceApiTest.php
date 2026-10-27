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

namespace Thelia\Tests\Api\Admin;

use Thelia\Model\CustomerGroupProductPriceQuery;
use Thelia\Test\ApiTestCase;

/**
 * A group price emptied of its last amount through the API is no price any more: the row
 * is dropped by the model, not left behind for every reader of the prices of a group.
 */
final class CustomerGroupProductPriceApiTest extends ApiTestCase
{
    public function testAPriceEmptiedThroughThePatchIsDeleted(): void
    {
        $factory = $this->createFixtureFactory();
        $currency = $factory->currency();
        $product = $factory->product($factory->category(), $factory->taxRule(), $currency);
        $price = $factory->customerGroupProductPrice($factory->customerGroup(), $product->getDefaultSaleElements(), $currency, 42.5);
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('PATCH', '/api/admin/customer_group_product_prices/'.$price->getId(), ['price' => null], $token, 'merge-patch+json');

        self::assertSame(204, $response->getStatusCode(), 'the write is a deletion, answered as one: '.$response->getContent());
        self::assertSame('', (string) $response->getContent());
        self::assertNull(CustomerGroupProductPriceQuery::create()->findPk($price->getId()));
        self::assertSame(404, $this->jsonRequest('GET', '/api/admin/customer_group_product_prices/'.$price->getId(), token: $token)->getStatusCode());
    }

    public function testAPriceEmptiedThroughThePutIsDeleted(): void
    {
        $factory = $this->createFixtureFactory();
        $currency = $factory->currency();
        $product = $factory->product($factory->category(), $factory->taxRule(), $currency);
        $group = $factory->customerGroup();
        $price = $factory->customerGroupProductPrice($group, $product->getDefaultSaleElements(), $currency, 42.5);

        $response = $this->jsonRequest('PUT', '/api/admin/customer_group_product_prices/'.$price->getId(), [
            'customerGroup' => '/api/admin/customer_groups/'.$group->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$product->getDefaultSaleElements()->getId(),
            'currency' => '/api/admin/currencies/'.$currency->getId(),
        ], $this->authenticateAsAdmin());

        self::assertSame(204, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(CustomerGroupProductPriceQuery::create()->findPk($price->getId()));
    }

    public function testAPriceIsDeletedOnceAndTheSecondDeletionIsNotFound(): void
    {
        $factory = $this->createFixtureFactory();
        $currency = $factory->currency();
        $product = $factory->product($factory->category(), $factory->taxRule(), $currency);
        $price = $factory->customerGroupProductPrice($factory->customerGroup(), $product->getDefaultSaleElements(), currency: $currency, price: 42.5);
        $token = $this->authenticateAsAdmin();

        self::assertSame(204, $this->jsonRequest('DELETE', '/api/admin/customer_group_product_prices/'.$price->getId(), token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('DELETE', '/api/admin/customer_group_product_prices/'.$price->getId(), token: $token)->getStatusCode());
    }

    public function testAPriceCreatedEmptyIsNotKept(): void
    {
        $factory = $this->createFixtureFactory();
        $currency = $factory->currency();
        $product = $factory->product($factory->category(), $factory->taxRule(), $currency);
        $group = $factory->customerGroup();

        $response = $this->jsonRequest('POST', '/api/admin/customer_group_product_prices', [
            'customerGroup' => '/api/admin/customer_groups/'.$group->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$product->getDefaultSaleElements()->getId(),
            'currency' => '/api/admin/currencies/'.$currency->getId(),
        ], $this->authenticateAsAdmin());

        self::assertSame(422, $response->getStatusCode(), 'a creation with no amount is a violation, not a 500 nor a resource with no id: '.$response->getContent());
        self::assertStringContainsString('needs a price or a promo price', (string) $response->getContent());
        self::assertSame(0, CustomerGroupProductPriceQuery::create()->filterByCustomerGroupId($group->getId())->count());
    }
}
