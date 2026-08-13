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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Test\ApiTestCase;

/**
 * An answer names its members and its relations on the surface it was served
 * from. A resource declares its admin and its front side on the same class, and
 * the IRI of an item the serializer has no operation for used to fall back to
 * the first item operation of the class — the admin one — so a front client was
 * handed links it cannot follow.
 */
final class SurfaceIriContractTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function frontCollections(): iterable
    {
        yield 'languages' => ['/api/front/languages'];
        yield 'customer titles' => ['/api/front/customer_titles'];
    }

    #[DataProvider('frontCollections')]
    public function testAFrontCollectionNamesItsMembersWithFrontIris(string $endpoint): void
    {
        $members = $this->members($this->jsonRequest('GET', $endpoint));

        self::assertNotEmpty($members, $endpoint.' returned no member to name');

        foreach ($members as $member) {
            self::assertStringStartsWith('/api/front/', (string) $member['@id']);
        }
    }

    public function testAFrontItemNamesItsRelationsWithFrontIris(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        $response = $this->jsonRequest('GET', '/api/front/products/'.$product->getId());
        self::assertJsonResponseSuccessful($response);

        $data = json_decode((string) $response->getContent(), true);

        self::assertStringStartsWith('/api/front/', (string) $data['@id']);
        self::assertStringStartsWith('/api/front/', (string) $data['taxRule']['@id']);
        self::assertStringStartsWith('/api/front/', (string) $data['productCategories'][0]['category']['@id']);
    }

    public function testAnAdminCollectionKeepsAdminIris(): void
    {
        $factory = $this->createFixtureFactory();
        $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        $members = $this->members($this->jsonRequest('GET', '/api/admin/products', token: $this->authenticateAsAdmin()));

        self::assertNotEmpty($members);

        foreach ($members as $member) {
            self::assertStringStartsWith('/api/admin/', (string) $member['@id']);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function members(\Symfony\Component\HttpFoundation\Response $response): array
    {
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true)['hydra:member'] ?? [];
    }
}
