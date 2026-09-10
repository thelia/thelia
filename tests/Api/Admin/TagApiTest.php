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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\TagElement;
use Thelia\Model\TagElementQuery;
use Thelia\Model\TagQuery;
use Thelia\Test\ApiTestCase;

final class TagApiTest extends ApiTestCase
{
    private const ALL_ACCESSES = [
        AccessManager::VIEW,
        AccessManager::CREATE,
        AccessManager::UPDATE,
        AccessManager::DELETE,
    ];

    public function testPostCreatesATag(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/tags', [
            'label' => 'Salon 2026',
            'colorCode' => '#1A2B3C',
        ], $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame('Salon 2026', $data['label']);
        self::assertSame('#1A2B3C', $data['colorCode']);
        self::assertNotNull(TagQuery::create()->findPk($data['id']));
    }

    public function testGetReturnsTheTag(): void
    {
        $token = $this->authenticateAsAdmin();
        $tag = $this->createFixtureFactory()->tag(['label' => 'VIP api read']);

        $response = $this->jsonRequest('GET', '/api/admin/tags/'.$tag->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame($tag->getId(), $data['id']);
        self::assertSame('VIP api read', $data['label']);
    }

    public function testListReturnsACollection(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $factory->tag();
        $factory->tag();

        $response = $this->jsonRequest('GET', '/api/admin/tags', token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertGreaterThanOrEqual(2, $data['hydra:totalItems']);
    }

    public function testDeleteRemovesTheTagAndItsAttachments(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $tag = $factory->tag();
        $customer = $factory->customer($factory->customerTitle());
        $factory->tagElement($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        $tagId = $tag->getId();
        $response = $this->jsonRequest('DELETE', '/api/admin/tags/'.$tagId, token: $token);

        self::assertSame(204, $response->getStatusCode());
        self::assertNull(TagQuery::create()->findPk($tagId));
        self::assertSame(0, TagElementQuery::create()->filterByTagId($tagId)->count());
    }

    /**
     * The colour reaches a style attribute in the back-office, so a value that
     * is not a hexadecimal code has to be refused at the door.
     *
     * The value is deliberately seven characters long, the width of the column:
     * a long payload is refused for its length alone, which proves nothing about
     * the format check.
     */
    #[DataProvider('invalidColourProvider')]
    public function testAnInvalidColourIsRefused(string $invalidColour): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/tags', [
            'label' => 'Bad colour '.$invalidColour,
            'colorCode' => $invalidColour,
        ], $token);

        self::assertSame(422, $response->getStatusCode());
        self::assertNull(TagQuery::create()->findOneByLabel('Bad colour '.$invalidColour));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidColourProvider(): iterable
    {
        yield 'no leading hash' => ['1A2B3C7'];
        yield 'not hexadecimal' => ['#GGGGGG'];
        yield 'too short' => ['#1A2B3'];
        yield 'three-digit shorthand' => ['#1A2'];
        yield 'a colour name' => ['red'];
    }

    public function testAnEmptyLabelIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/tags', ['label' => ''], $token);

        self::assertSame(422, $response->getStatusCode());
    }

    /**
     * A label of nothing but whitespace is the same thing as an empty one, and it
     * must be refused at the door rather than saved: Tag::preSave() would reduce it
     * to an empty string and leave a tag nobody can name in the table.
     */
    public function testALabelOfOnlyWhitespaceIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/tags', ['label' => "  \t "], $token);

        self::assertSame(422, $response->getStatusCode());
        self::assertNull(TagQuery::create()->findOneByLabel(''), 'No tag with an empty label may reach the table.');
    }

    /**
     * Tags are internal, so the API is administration only. A shop must not be
     * able to reach them from the front under any route.
     */
    public function testNoFrontOperationExists(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/tags');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testAnAdminWithoutTheTagResourceCannotListTags(): void
    {
        $token = $this->authenticateAsAdmin($this->customerOnlyAdmin());

        $response = $this->jsonRequest('GET', '/api/admin/tags', token: $token);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAnAdminWithoutTheTagResourceCannotCreateATag(): void
    {
        $token = $this->authenticateAsAdmin($this->customerOnlyAdmin());

        $response = $this->jsonRequest('POST', '/api/admin/tags', ['label' => 'Sneaked in'], $token);

        self::assertSame(403, $response->getStatusCode());
        self::assertNull(TagQuery::create()->findOneByLabel('Sneaked in'));
    }

    /**
     * Allowed on customers, not on the tag vocabulary: renaming or merging tags
     * is a configuration act of its own, and this profile has no business doing
     * it.
     */
    private function customerOnlyAdmin(): Admin
    {
        return $this->createFixtureFactory()->restrictedAdmin([
            AdminResources::CUSTOMER => self::ALL_ACCESSES,
        ]);
    }
}
