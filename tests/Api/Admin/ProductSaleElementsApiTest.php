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

use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ApiTestCase;

final class ProductSaleElementsApiTest extends ApiTestCase
{
    /**
     * A PUT replaces the resource, so a payload carrying only the stock leaves
     * every other writable property out. Those properties map to NOT NULL
     * columns, and writing null into them used to reach the database and come
     * back as a 500 carrying the failed SQL statement.
     */
    public function testPartialPutDoesNotEndAsAServerError(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $pse = $factory->productSaleElement($product, ['ref' => 'PSE-PARTIAL-PUT']);

        $response = $this->jsonRequest('PUT', '/api/admin/product_sale_elements/'.$pse->getId(), [
            'quantity' => 42,
            'weight' => 2.5,
        ], $token);

        self::assertSame(422, $response->getStatusCode(), $response->getContent());

        $reloaded = ProductSaleElementsQuery::create()->findPk($pse->getId());
        self::assertNotNull($reloaded);
        self::assertSame($product->getId(), $reloaded->getProductId());
    }

    /**
     * The same payload sent as a merge patch is the documented way to update a
     * single property, and it must keep every other column untouched.
     */
    public function testPartialPatchUpdatesOnlyTheSubmittedProperties(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $pse = $factory->productSaleElement($product, ['ref' => 'PSE-PARTIAL-PATCH']);
        $position = $pse->getPosition();

        $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$pse->getId(), [
            'quantity' => 42,
        ], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);

        $reloaded = ProductSaleElementsQuery::create()->findPk($pse->getId());
        self::assertEqualsWithDelta(42, $reloaded->getQuantity(), 0.001);
        self::assertSame($product->getId(), $reloaded->getProductId());
        self::assertSame($position, $reloaded->getPosition());
        self::assertSame('PSE-PARTIAL-PATCH', $reloaded->getRef());
    }
}
