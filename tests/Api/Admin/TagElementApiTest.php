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

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\TagElement;
use Thelia\Model\TagElementQuery;
use Thelia\Test\ApiTestCase;

/**
 * Attaching a tag to a customer through the admin API, so an external tool can
 * mark a customer.
 */
final class TagElementApiTest extends ApiTestCase
{
    private const ALL_ACCESSES = [
        AccessManager::VIEW,
        AccessManager::CREATE,
        AccessManager::UPDATE,
        AccessManager::DELETE,
    ];

    public function testPostAttachesATagToACustomer(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $tag = $factory->tag();
        $customer = $factory->customer($factory->customerTitle());

        $response = $this->jsonRequest('POST', '/api/admin/tag-elements', [
            'tag' => '/api/admin/tags/'.$tag->getId(),
            'elementKey' => TagElement::ELEMENT_KEY_CUSTOMER,
            'elementId' => $customer->getId(),
        ], $token);

        self::assertJsonResponseSuccessful($response);
        self::assertSame(
            1,
            TagElementQuery::create()
                ->filterByTagId($tag->getId())
                ->filterByElementKey(TagElement::ELEMENT_KEY_CUSTOMER)
                ->filterByElementId($customer->getId())
                ->count(),
        );
    }

    public function testTheSameAttachmentCannotBeCreatedTwice(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $tag = $factory->tag();
        $customer = $factory->customer($factory->customerTitle());
        $factory->tagElement($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        $response = $this->jsonRequest('POST', '/api/admin/tag-elements', [
            'tag' => '/api/admin/tags/'.$tag->getId(),
            'elementKey' => TagElement::ELEMENT_KEY_CUSTOMER,
            'elementId' => $customer->getId(),
        ], $token);

        self::assertGreaterThanOrEqual(400, $response->getStatusCode(), 'The unique triplet must refuse the duplicate.');
        self::assertSame(1, TagElementQuery::create()->filterByTagId($tag->getId())->count());
    }

    /**
     * The scalar filters are the ones the resource declares, so they are the
     * ones asserted. Filtering by tag is deliberately not declared: see the
     * class comment of the resource.
     */
    public function testListCanBeFilteredByTheTaggedObject(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $tag = $factory->tag();
        $wantedCustomer = $factory->customer($factory->customerTitle());
        $otherCustomer = $factory->customer($factory->customerTitle());
        $factory->tagElement($tag, TagElement::ELEMENT_KEY_CUSTOMER, $wantedCustomer->getId());
        $factory->tagElement($tag, TagElement::ELEMENT_KEY_CUSTOMER, $otherCustomer->getId());

        $response = $this->jsonRequest(
            'GET',
            '/api/admin/tag-elements?elementKey='.TagElement::ELEMENT_KEY_CUSTOMER.'&elementId='.$wantedCustomer->getId(),
            token: $token,
        );

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame(1, $data['hydra:totalItems']);
    }

    public function testDeleteDetachesTheTag(): void
    {
        $token = $this->authenticateAsAdmin();
        $factory = $this->createFixtureFactory();
        $tag = $factory->tag();
        $customer = $factory->customer($factory->customerTitle());
        $link = $factory->tagElement($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        $response = $this->jsonRequest('DELETE', '/api/admin/tag-elements/'.$link->getId(), token: $token);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame(0, TagElementQuery::create()->filterByTagId($tag->getId())->count());
    }

    public function testNoFrontOperationExists(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/tag-elements');

        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * The attachment answers to the customer resource, not to the tag one:
     * knowing who carries which tag is reading customer data.
     */
    public function testAnAdminWithoutTheCustomerResourceCannotListAttachments(): void
    {
        $token = $this->authenticateAsAdmin($this->tagOnlyAdmin());

        $response = $this->jsonRequest('GET', '/api/admin/tag-elements', token: $token);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAnAdminWithoutTheCustomerResourceCannotAttachATag(): void
    {
        $factory = $this->createFixtureFactory();
        $tag = $factory->tag();
        $customer = $factory->customer($factory->customerTitle());
        $token = $this->authenticateAsAdmin($this->tagOnlyAdmin());

        $response = $this->jsonRequest('POST', '/api/admin/tag-elements', [
            'tag' => '/api/admin/tags/'.$tag->getId(),
            'elementKey' => TagElement::ELEMENT_KEY_CUSTOMER,
            'elementId' => $customer->getId(),
        ], $token);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(0, TagElementQuery::create()->filterByTagId($tag->getId())->count());
    }

    private function tagOnlyAdmin(): Admin
    {
        return $this->createFixtureFactory()->restrictedAdmin([
            AdminResources::TAG => self::ALL_ACCESSES,
        ]);
    }
}
