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

use Thelia\Api\Security\AdminApiResourcePermissions;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\CustomerQuery;
use Thelia\Test\ApiTestCase;

/**
 * The admin API must honour the per-resource permissions of the profile the
 * token was issued to, exactly as the back-office screens do.
 */
final class AdminApiPermissionsApiTest extends ApiTestCase
{
    private const ALL_ACCESSES = [
        AccessManager::VIEW,
        AccessManager::CREATE,
        AccessManager::UPDATE,
        AccessManager::DELETE,
    ];

    public function testRestrictedAdminIsStillIssuedAToken(): void
    {
        $token = $this->authenticateAsAdmin($this->catalogueAdmin());

        self::assertNotSame('', $token);
    }

    public function testRestrictedAdminCannotListCustomers(): void
    {
        $token = $this->authenticateAsAdmin($this->catalogueAdmin());

        $response = $this->jsonRequest('GET', '/api/admin/customers', token: $token);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testRestrictedAdminCannotReadACustomer(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $token = $this->authenticateAsAdmin($this->catalogueAdmin());

        $response = $this->jsonRequest('GET', '/api/admin/customers/'.$customer->getId(), token: $token);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testRestrictedAdminCannotPatchACustomer(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $firstname = $customer->getFirstname();
        $token = $this->authenticateAsAdmin($this->catalogueAdmin());

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/customers/'.$customer->getId(),
            ['firstname' => 'Hijacked'],
            $token,
            'merge-patch+json',
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertSame($firstname, CustomerQuery::create()->findPk($customer->getId())->getFirstname());
    }

    public function testRestrictedAdminCannotListOrders(): void
    {
        $token = $this->authenticateAsAdmin($this->catalogueAdmin());

        $response = $this->jsonRequest('GET', '/api/admin/orders', token: $token);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testRestrictedAdminReachesTheResourcesItsProfileGrants(): void
    {
        $token = $this->authenticateAsAdmin($this->catalogueAdmin());

        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/products', token: $token));
        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/categories', token: $token));
    }

    public function testReadOnlyProfileCannotWriteOnAGrantedResource(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $admin = $factory->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]);
        $token = $this->authenticateAsAdmin($admin);

        self::assertJsonResponseSuccessful(
            $this->jsonRequest('GET', '/api/admin/products/'.$product->getId(), token: $token),
        );

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/products/'.$product->getId(),
            ['visible' => false],
            $token,
            'merge-patch+json',
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testResourceWithoutADeclaredPermissionIsRefused(): void
    {
        $token = $this->authenticateAsAdmin($this->catalogueAdmin());

        // TheliaLibrary publishes admin endpoints but declares no admin resource.
        $response = $this->jsonRequest('GET', '/api/admin/library_images', token: $token);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testSuperAdministratorKeepsAccessToEverything(): void
    {
        $token = $this->authenticateAsAdmin();

        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/customers', token: $token));
        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/orders', token: $token));
        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/products', token: $token));
        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/library_images', token: $token));
    }

    /**
     * Guard for the next resource added to the core API: an admin endpoint whose
     * resource declares no permission is refused to every profile but the
     * superadministrator, which would look like a bug rather than a decision.
     */
    public function testEveryCoreAdminResourceDeclaresItsPermission(): void
    {
        $permissions = new AdminApiResourcePermissions();
        $undeclared = [];

        foreach ($this->getService('router')->getRouteCollection() as $route) {
            $resourceClass = $route->getDefault('_api_resource_class');

            if (!\is_string($resourceClass)
                || !str_starts_with($route->getPath(), '/api/admin')
                || !str_starts_with($resourceClass, 'Thelia\\Api\\Resource\\')
                || null !== $permissions->resolve($resourceClass)
            ) {
                continue;
            }

            $undeclared[$resourceClass] = $resourceClass;
        }

        self::assertSame([], array_values($undeclared));
    }

    private function catalogueAdmin(): Admin
    {
        return $this->createFixtureFactory()->restrictedAdmin([
            AdminResources::PRODUCT => self::ALL_ACCESSES,
            AdminResources::CATEGORY => self::ALL_ACCESSES,
        ]);
    }
}
