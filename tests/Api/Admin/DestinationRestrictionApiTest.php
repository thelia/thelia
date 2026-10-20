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
use Thelia\Model\CountryQuery;
use Thelia\Model\DestinationRestrictionCountryQuery;
use Thelia\Model\DestinationRestrictionQuery;
use Thelia\Model\ProductDestinationRestrictionQuery;
use Thelia\Model\StateQuery;
use Thelia\Test\ApiTestCase;

/**
 * Destination restriction groups through the admin API, the way an integration or a
 * catalogue import writes them.
 */
final class DestinationRestrictionApiTest extends ApiTestCase
{
    private const ALL_ACCESSES = [
        AccessManager::VIEW,
        AccessManager::CREATE,
        AccessManager::UPDATE,
        AccessManager::DELETE,
    ];

    public function testAGroupIsCreatedWithItsWording(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/destination_restrictions', [
            'code' => 'alcohol-api',
            'mode' => 0,
            'active' => true,
            'i18ns' => ['fr_FR' => ['locale' => 'fr_FR', 'title' => 'Alcools', 'reason' => 'Licence de vente']],
        ], $token);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $group = DestinationRestrictionQuery::create()->findOneByCode('alcohol-api');
        self::assertNotNull($group);
        self::assertSame('Licence de vente', $group->setLocale('fr_FR')->getReason());
    }

    public function testACodeAlreadyTakenIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();
        $payload = ['code' => 'twice', 'mode' => 0, 'active' => true, 'i18ns' => ['en_US' => ['locale' => 'en_US', 'title' => 'Twice']]];
        $this->jsonRequest('POST', '/api/admin/destination_restrictions', $payload, $token);

        self::assertSame(422, $this->jsonRequest('POST', '/api/admin/destination_restrictions', $payload, $token)->getStatusCode());
    }

    public function testARegionOutsideItsCountryIsRefusedAndAProductJoinsTheGroup(): void
    {
        $token = $this->authenticateAsAdmin();
        $created = $this->jsonRequest('POST', '/api/admin/destination_restrictions', ['code' => 'regions', 'mode' => 0, 'active' => true, 'i18ns' => ['en_US' => ['locale' => 'en_US', 'title' => 'Regions']]], $token);
        $groupIri = '/api/admin/destination_restrictions/'.json_decode((string) $created->getContent(), true)['id'];
        $state = StateQuery::create()->orderById()->findOne() ?? self::markTestSkipped('The test shop has no region.');
        $otherCountry = CountryQuery::create()->filterById($state->getCountryId(), \Propel\Runtime\ActiveQuery\Criteria::NOT_EQUAL)->findOne();
        self::assertNotNull($otherCountry);

        $refused = $this->jsonRequest('POST', '/api/admin/destination_restriction_countries', [
            'destinationRestriction' => $groupIri,
            'country' => '/api/admin/countries/'.$otherCountry->getId(),
            'state' => '/api/admin/states/'.$state->getId(),
        ], $token);
        self::assertSame(422, $refused->getStatusCode());

        $listed = $this->jsonRequest('POST', '/api/admin/destination_restriction_countries', [
            'destinationRestriction' => $groupIri,
            'country' => '/api/admin/countries/'.$state->getCountryId(),
            'state' => '/api/admin/states/'.$state->getId(),
        ], $token);
        self::assertSame(201, $listed->getStatusCode(), (string) $listed->getContent());
        self::assertSame(1, DestinationRestrictionCountryQuery::create()->filterByStateId($state->getId())->count());

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $joined = $this->jsonRequest('POST', '/api/admin/product_destination_restrictions', [
            'product' => '/api/admin/products/'.$product->getId(),
            'destinationRestriction' => $groupIri,
        ], $token);
        self::assertSame(201, $joined->getStatusCode(), (string) $joined->getContent());

        $link = ProductDestinationRestrictionQuery::create()->findOneByProductId($product->getId());
        self::assertNotNull($link);
        self::assertSame(204, $this->jsonRequest('DELETE', '/api/admin/product_destination_restrictions/'.$link->getId(), token: $token)->getStatusCode());
        self::assertSame(0, ProductDestinationRestrictionQuery::create()->filterByProductId($product->getId())->count());
    }

    public function testAnAdminWithoutTheRestrictionPermissionCannotListTheGroups(): void
    {
        $token = $this->authenticateAsAdmin($this->catalogueOnlyAdmin());

        self::assertSame(403, $this->jsonRequest('GET', '/api/admin/destination_restrictions', token: $token)->getStatusCode());
    }

    public function testAnAdminWithoutTheRestrictionPermissionCannotCreateAGroupNorListADestination(): void
    {
        $created = $this->jsonRequest('POST', '/api/admin/destination_restrictions', ['code' => 'existing', 'mode' => 0, 'active' => true, 'i18ns' => ['en_US' => ['locale' => 'en_US', 'title' => 'Existing']]], $this->authenticateAsAdmin());
        $groupIri = '/api/admin/destination_restrictions/'.json_decode((string) $created->getContent(), true)['id'];
        $country = CountryQuery::create()->orderById()->findOne();
        self::assertNotNull($country);
        $token = $this->authenticateAsAdmin($this->catalogueOnlyAdmin());

        $group = $this->jsonRequest('POST', '/api/admin/destination_restrictions', ['code' => 'sneaked-in', 'mode' => 0, 'active' => true, 'i18ns' => ['en_US' => ['locale' => 'en_US', 'title' => 'Sneaked in']]], $token);
        $destination = $this->jsonRequest('POST', '/api/admin/destination_restriction_countries', [
            'destinationRestriction' => $groupIri,
            'country' => '/api/admin/countries/'.$country->getId(),
        ], $token);

        self::assertSame(403, $group->getStatusCode());
        self::assertNull(DestinationRestrictionQuery::create()->findOneByCode('sneaked-in'));
        self::assertSame(403, $destination->getStatusCode());
        self::assertSame(0, DestinationRestrictionCountryQuery::create()->filterByCountryId($country->getId())->count());
    }

    public function testAnAdminAllowedToViewTheRestrictionsListsTheGroups(): void
    {
        $token = $this->authenticateAsAdmin($this->createFixtureFactory()->restrictedAdmin([
            AdminResources::DESTINATION_RESTRICTION => [AccessManager::VIEW],
        ]));

        self::assertSame(200, $this->jsonRequest('GET', '/api/admin/destination_restrictions', token: $token)->getStatusCode());
    }

    public function testNoFrontOperationExists(): void
    {
        foreach (['/api/front/destination_restrictions', '/api/front/destination_restriction_countries', '/api/front/product_destination_restrictions'] as $path) {
            self::assertSame(404, $this->jsonRequest('GET', $path)->getStatusCode(), $path);
        }
    }

    /**
     * Allowed on the whole catalogue, not on the restrictions: which destinations a
     * product is kept from is a configuration act of its own.
     */
    private function catalogueOnlyAdmin(): Admin
    {
        return $this->createFixtureFactory()->restrictedAdmin([
            AdminResources::PRODUCT => self::ALL_ACCESSES,
        ]);
    }
}
