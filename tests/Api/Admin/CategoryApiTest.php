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

use Thelia\Model\CategoryQuery;
use Thelia\Model\Map\CategoryI18nTableMap;
use Thelia\Model\Map\CategoryTableMap;
use Thelia\Test\ApiTestCase;

final class CategoryApiTest extends ApiTestCase
{
    public function testCreateCategoryViaPost(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/categories', [
            'visible' => true,
            'position' => 1,
            'parent' => 0,
            'i18ns' => [
                'en_US' => [
                    'title' => 'API Category',
                    'locale' => 'en_US',
                ],
            ],
        ], $token);

        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('id', $data);
        self::assertTrue($data['visible']);

        $category = CategoryQuery::create()->findPk($data['id']);
        self::assertNotNull($category);
        self::assertSame('API Category', $category->setLocale('en_US')->getTitle());
    }

    public function testGetCategoryReturnsResource(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $category = $factory->category();

        $response = $this->jsonRequest('GET', '/api/admin/categories/'.$category->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame($category->getId(), $data['id']);
    }

    public function testPatchCategoryUpdatesFields(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $category = $factory->category();

        $response = $this->jsonRequest('PATCH', '/api/admin/categories/'.$category->getId(), [
            'visible' => false,
        ], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertSame(0, (int) CategoryQuery::create()->findPk($category->getId())->getVisible());
    }

    public function testPatchCategoryAnswersWithTheSavedTranslation(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $category->setLocale('en_US')->setTitle('Title before the patch');
        $category->setLocale('fr_FR')->setTitle('Titre Français');
        $category->save();

        $response = $this->jsonRequest('PATCH', '/api/admin/categories/'.$category->getId(), [
            'i18ns' => [
                'en_US' => [
                    'title' => 'Title after the patch',
                ],
            ],
        ], $token, 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Title after the patch', $data['i18ns']['en_US']['title']);
        self::assertSame('Titre Français', $data['i18ns']['fr_FR']['title']);

        CategoryTableMap::clearInstancePool();
        CategoryI18nTableMap::clearInstancePool();

        self::assertSame(
            'Title after the patch',
            CategoryQuery::create()->findPk($category->getId())->setLocale('en_US')->getTitle(),
        );
    }

    public function testDeleteCategoryRemovesResource(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $id = $category->getId();

        $response = $this->jsonRequest('DELETE', '/api/admin/categories/'.$id, token: $token);

        self::assertSame(204, $response->getStatusCode());
        self::assertNull(CategoryQuery::create()->findPk($id));
    }

    public function testI18nRoundTripViaApi(): void
    {
        $token = $this->authenticateAsAdmin();

        // Create with English title.
        $response = $this->jsonRequest('POST', '/api/admin/categories', [
            'visible' => true,
            'position' => 1,
            'parent' => 0,
            'i18ns' => [
                'en_US' => [
                    'title' => 'English Title',
                    'locale' => 'en_US',
                ],
                'fr_FR' => [
                    'title' => 'Titre Français',
                    'locale' => 'fr_FR',
                ],
            ],
        ], $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);

        // Verify both locales in DB.
        $category = CategoryQuery::create()->findPk($data['id']);
        self::assertSame('English Title', $category->setLocale('en_US')->getTitle());
        self::assertSame('Titre Français', $category->setLocale('fr_FR')->getTitle());
    }
}
