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

use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Test\ApiTestCase;

final class ContentFolderApiTest extends ApiTestCase
{
    public function testCreateFolder(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/folders', [
            'visible' => true,
            'position' => 1,
            'parent' => 0,
            'i18ns' => [
                'en_US' => ['title' => 'Blog', 'locale' => 'en_US'],
            ],
        ], $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('id', $data);
        self::assertSame('Blog', FolderQuery::create()->findPk($data['id'])->setLocale('en_US')->getTitle());
    }

    public function testGetFolder(): void
    {
        $token = $this->authenticateAsAdmin();
        $folder = $this->createFixtureFactory()->folder();

        $response = $this->jsonRequest('GET', '/api/admin/folders/'.$folder->getId(), token: $token);
        self::assertJsonResponseSuccessful($response);
    }

    public function testDeleteFolder(): void
    {
        $token = $this->authenticateAsAdmin();
        $folder = $this->createFixtureFactory()->folder();
        $id = $folder->getId();

        $response = $this->jsonRequest('DELETE', '/api/admin/folders/'.$id, token: $token);
        self::assertSame(204, $response->getStatusCode());
        self::assertNull(FolderQuery::create()->findPk($id));
    }

    public function testCreateContent(): void
    {
        $token = $this->authenticateAsAdmin();
        $folder = $this->createFixtureFactory()->folder();

        $response = $this->jsonRequest('POST', '/api/admin/contents', [
            'visible' => true,
            'position' => 1,
            'defaultFolder' => '/api/admin/folders/'.$folder->getId(),
            'i18ns' => [
                'en_US' => ['title' => 'About Us', 'locale' => 'en_US'],
            ],
        ], $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('id', $data);
    }

    public function testGetContent(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $content = $factory->content($factory->folder());

        $response = $this->jsonRequest('GET', '/api/admin/contents/'.$content->getId(), token: $token);
        self::assertJsonResponseSuccessful($response);
    }

    public function testDeleteContent(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $content = $factory->content($factory->folder());
        $id = $content->getId();

        $response = $this->jsonRequest('DELETE', '/api/admin/contents/'.$id, token: $token);
        self::assertSame(204, $response->getStatusCode());
        self::assertNull(ContentQuery::create()->findPk($id));
    }
}
