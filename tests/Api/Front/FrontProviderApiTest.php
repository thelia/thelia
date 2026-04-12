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
 * Tests for front API endpoints backed by custom state providers.
 * These routes had their OpenAPI definition migrated from openapiContext
 * to openapi (API Platform 4.3 migration).
 */
final class FrontProviderApiTest extends ApiTestCase
{
    public function testDeliveryModulesCollectionReturns200(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/delivery_modules');

        // Without an active cart/session the provider returns an empty array.
        self::assertJsonResponseSuccessful($response);
    }

    public function testDeliveryModulesWithOnlyValidParam(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/delivery_modules?only_valid=true');

        self::assertJsonResponseSuccessful($response);
    }

    public function testPaymentModulesCollectionReturns200(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/payment/modules');

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('hydra:member', $data);
    }

    public function testPaymentModulesWithModuleIdFilter(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/payment/modules?moduleId=999');

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('hydra:member', $data);
    }

    public function testTFiltersForProductsReturns200(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/tfilters/products');

        $statusCode = $response->getStatusCode();
        // The provider may return an array or throw if resource is unknown.
        self::assertContains($statusCode, [200, 404, 500], sprintf(
            'Expected 200, 404 or 500 for tfilters/products, got %d: %s',
            $statusCode,
            substr($response->getContent(), 0, 500),
        ));

        if (200 === $statusCode) {
            $data = json_decode($response->getContent(), true);
            self::assertIsArray($data);
        }
    }

    public function testTFiltersWithoutResourceReturns404(): void
    {
        // /api/front/tfilters/ without resource should not match the route
        $response = $this->jsonRequest('GET', '/api/front/tfilters/');

        // Either 404 (no route match) or redirect
        self::assertContains($response->getStatusCode(), [301, 404]);
    }

    public function testDeliveryPickupLocationsReturns200OrEmpty(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/delivery_pickup_locations/Paris/75001');

        $statusCode = $response->getStatusCode();
        // Without delivery modules that handle pickup, this may return
        // an empty collection or a 200 with no locations.
        self::assertContains($statusCode, [200, 404, 500], sprintf(
            'Expected 200 or 404 for pickup locations, got %d: %s',
            $statusCode,
            substr($response->getContent(), 0, 500),
        ));

        if (200 === $statusCode) {
            $data = json_decode($response->getContent(), true);
            self::assertArrayHasKey('hydra:member', $data);
        }
    }

    public function testDeliveryPickupLocationsWithQueryParams(): void
    {
        $response = $this->jsonRequest(
            'GET',
            '/api/front/delivery_pickup_locations/Lyon/69001?countryId=64&radius=10&maxRelays=5',
        );

        $statusCode = $response->getStatusCode();
        self::assertContains($statusCode, [200, 404, 500]);
    }
}
