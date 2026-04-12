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

use Thelia\Model\FeatureQuery;
use Thelia\Test\ApiTestCase;

final class FeatureApiTest extends ApiTestCase
{
    public function testCreateFeature(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/features', [
            'position' => 1,
            'i18ns' => [
                'en_US' => ['title' => 'Material', 'locale' => 'en_US'],
            ],
        ], $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('id', $data);
    }

    public function testGetFeature(): void
    {
        $token = $this->authenticateAsAdmin();
        $feature = $this->createFixtureFactory()->feature(['title' => 'Weight']);

        $response = $this->jsonRequest('GET', '/api/admin/features/'.$feature->getId(), token: $token);
        self::assertJsonResponseSuccessful($response);
        self::assertSame($feature->getId(), json_decode($response->getContent(), true)['id']);
    }

    public function testDeleteFeature(): void
    {
        $token = $this->authenticateAsAdmin();
        $feature = $this->createFixtureFactory()->feature();
        $id = $feature->getId();

        $response = $this->jsonRequest('DELETE', '/api/admin/features/'.$id, token: $token);
        self::assertSame(204, $response->getStatusCode());
        self::assertNull(FeatureQuery::create()->findPk($id));
    }

    public function testCreateFeatureAv(): void
    {
        $token = $this->authenticateAsAdmin();
        $feature = $this->createFixtureFactory()->feature();

        $response = $this->jsonRequest('POST', '/api/admin/feature_avs', [
            'feature' => '/api/admin/features/'.$feature->getId(),
            'position' => 1,
            'i18ns' => [
                'en_US' => ['title' => 'Cotton', 'locale' => 'en_US'],
            ],
        ], $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('id', $data);
    }
}
