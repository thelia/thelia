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

use Thelia\Test\ApiTestCase;

/**
 * The sale units are managed through the admin API: created with their domain
 * and wording, read back, turned on and off.
 */
final class SaleUnitApiTest extends ApiTestCase
{
    public function testASaleUnitIsCreatedReadAndTurnedOff(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/sale_units', [
            'code' => 'shop-lyon',
            'url' => 'lyon.example.com',
            'active' => true,
            'position' => 1,
            'i18ns' => [
                'en_US' => ['title' => 'Lyon store'],
                'fr_FR' => ['title' => 'Magasin de Lyon'],
            ],
        ], $token);
        self::assertJsonResponseSuccessful($response);
        $created = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('shop-lyon', $created['code']);

        $read = $this->jsonRequest('GET', '/api/admin/sale_units/'.$created['id'], token: $token);
        self::assertJsonResponseSuccessful($read);
        $payload = json_decode((string) $read->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('lyon.example.com', $payload['url']);
        self::assertTrue($payload['active']);
        self::assertSame('Magasin de Lyon', $payload['i18ns']['fr_FR']['title']);

        $update = $this->jsonRequest('PUT', '/api/admin/sale_units/'.$created['id'], [
            'code' => 'shop-lyon',
            'url' => 'lyon.example.com',
            'active' => false,
            'position' => 1,
        ], $token);
        self::assertJsonResponseSuccessful($update);
        $updated = json_decode((string) $update->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertFalse($updated['active']);
    }

    public function testTheAdminApiRefusesAnAnonymousWrite(): void
    {
        $response = $this->jsonRequest('POST', '/api/admin/sale_units', ['code' => 'anonymous']);

        self::assertSame(401, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testTheFrontCollectionOnlyServesTheActiveSaleUnits(): void
    {
        $factory = $this->createFixtureFactory();
        $open = $factory->saleUnit(['code' => 'open-shop']);
        $factory->saleUnit(['code' => 'closed-shop', 'active' => false]);

        $response = $this->jsonRequest('GET', '/api/front/sale_units');
        self::assertJsonResponseSuccessful($response);
        $members = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['hydra:member'];

        $codes = array_column($members, 'code');
        self::assertContains('open-shop', $codes);
        self::assertNotContains('closed-shop', $codes, 'a turned-off sale unit must not be offered to the front');

        $item = $this->jsonRequest('GET', '/api/front/sale_units/'.$open->getId());
        self::assertJsonResponseSuccessful($item);
    }

    public function testTheFrontItemReadOfAnInactiveSaleUnitIsNotFound(): void
    {
        $closed = $this->createFixtureFactory()->saleUnit(['active' => false]);

        $response = $this->jsonRequest('GET', '/api/front/sale_units/'.$closed->getId());

        self::assertSame(404, $response->getStatusCode(), (string) $response->getContent());
    }
}
