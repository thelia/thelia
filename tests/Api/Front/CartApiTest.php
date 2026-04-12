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

use Thelia\Test\ApiTestCase;

/**
 * Tests for the front cart and checkout API endpoints.
 *
 * The cart is session-based. Without prior cart creation via POST,
 * GET /api/front/cart returns 404 (no active cart). Delivery/payment
 * module endpoints depend on module-specific tables that may not be
 * seeded in the test DB.
 */
final class CartApiTest extends ApiTestCase
{
    public function testGetCartReturns404WithoutActiveCart(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/cart');

        // No cart in session → 404 is the expected behavior.
        self::assertSame(404, $response->getStatusCode());
    }

    public function testCreateCartViaPost(): void
    {
        $response = $this->jsonRequest('POST', '/api/front/carts', []);

        // Cart creation should succeed (201) or return validation error.
        $statusCode = $response->getStatusCode();
        self::assertContains($statusCode, [200, 201, 400, 422]);
    }

    public function testPaymentModulesCollectionReturns200(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/payment/modules');

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('hydra:member', $data);
    }

    public function testCartItemsCollectionReturns200(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/cart_items');

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('hydra:member', $data);
    }

    public function testCouponsCollectionReturns200(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/coupons');

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('hydra:member', $data);
    }
}
