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

use Thelia\Model\AttributeQuery;
use Thelia\Test\ApiTestCase;

final class AttributeApiTest extends ApiTestCase
{
    public function testCreateAttribute(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/attributes', [
            'position' => 1,
            'i18ns' => [
                'en_US' => ['title' => 'Color', 'locale' => 'en_US'],
            ],
        ], $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('id', $data);
    }

    public function testGetAttribute(): void
    {
        $token = $this->authenticateAsAdmin();
        $attr = $this->createFixtureFactory()->attribute(['title' => 'Size']);

        $response = $this->jsonRequest('GET', '/api/admin/attributes/'.$attr->getId(), token: $token);
        self::assertJsonResponseSuccessful($response);
        self::assertSame($attr->getId(), json_decode($response->getContent(), true)['id']);
    }

    public function testDeleteAttribute(): void
    {
        $token = $this->authenticateAsAdmin();
        $attr = $this->createFixtureFactory()->attribute();
        $id = $attr->getId();

        $response = $this->jsonRequest('DELETE', '/api/admin/attributes/'.$id, token: $token);
        self::assertSame(204, $response->getStatusCode());
        self::assertNull(AttributeQuery::create()->findPk($id));
    }

    public function testCreateAttributeAv(): void
    {
        $token = $this->authenticateAsAdmin();
        $attr = $this->createFixtureFactory()->attribute();

        $response = $this->jsonRequest('POST', '/api/admin/attribute_avs', [
            'attribute' => '/api/admin/attributes/'.$attr->getId(),
            'position' => 1,
            'i18ns' => [
                'en_US' => ['title' => 'Red', 'locale' => 'en_US'],
            ],
        ], $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('id', $data);
    }
}
