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

namespace Thelia\Tests\Api\Contract;

use Thelia\Test\ApiTestCase;

/**
 * Verifies that serialization groups enforce the intended contract:
 * - Fields in `admin:*:write` groups must NOT appear in GET responses
 * - Fields in `*:single` groups must NOT appear in collection responses
 * - The `id` field must always be present
 */
final class SerializationGroupsContractTest extends ApiTestCase
{
    public function testProductCollectionDoesNotExposeWriteOnlyFields(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );

        $response = $this->jsonRequest('GET', '/api/admin/products', token: $token);
        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);
        $firstItem = $data['hydra:member'][0] ?? null;
        self::assertNotNull($firstItem);

        // `id` and `ref` are in admin:product:read — must be present.
        self::assertArrayHasKey('id', $firstItem);
        self::assertArrayHasKey('ref', $firstItem);

        // `taxRule` is only in admin:product:read:single — must NOT appear in collection.
        self::assertArrayNotHasKey('taxRule', $firstItem);

        // `createdAt` is only in admin:product:read:single — must NOT appear.
        self::assertArrayNotHasKey('createdAt', $firstItem);
    }

    public function testProductSingleExposesReadSingleFields(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );

        $response = $this->jsonRequest('GET', '/api/admin/products/'.$product->getId(), token: $token);
        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);

        // Single item should have both read and read:single groups.
        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('ref', $data);
        self::assertArrayHasKey('taxRule', $data);
        self::assertArrayHasKey('productCategories', $data);
    }

    public function testCategoryCollectionDoesNotExposeSingleOnlyRelations(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $factory->category();

        $response = $this->jsonRequest('GET', '/api/admin/categories', token: $token);
        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);
        $firstItem = $data['hydra:member'][0] ?? null;
        self::assertNotNull($firstItem);

        self::assertArrayHasKey('id', $firstItem);
        // Category collection includes scalar fields like createdAt,
        // but complex relations like contentCategories are single-only.
        self::assertArrayNotHasKey('contentCategories', $firstItem);
    }

    public function testFrontProductCollectionDoesNotExposeAdminFields(): void
    {
        $factory = $this->createFixtureFactory();
        $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );

        $response = $this->jsonRequest('GET', '/api/front/products');
        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);
        $firstItem = $data['hydra:member'][0] ?? null;
        self::assertNotNull($firstItem);

        // Front read must have id and ref.
        self::assertArrayHasKey('id', $firstItem);
        self::assertArrayHasKey('ref', $firstItem);
    }
}
