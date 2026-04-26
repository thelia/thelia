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

namespace Thelia\Tests\Integration\Core\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessMapInterface;
use Thelia\Test\IntegrationTestCase;

/**
 * Guard-rail for the path-based access_control rules injected via
 * Reflection in {@see \Thelia\Core\TheliaKernel::processSecurity()}.
 *
 * If a future Symfony bump silently changes the SecurityBundle config
 * shape, the Reflection injection will no-op and these assertions
 * will catch it before production deploy.
 */
final class AccessControlRulesTest extends IntegrationTestCase
{
    private AccessMapInterface $accessMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessMap = static::getContainer()->get('security.access_map');
    }

    public function testAdminApiRequiresRoleAdmin(): void
    {
        [$roles] = $this->accessMap->getPatterns(Request::create('/api/admin/customers'));

        self::assertNotNull($roles, 'No access_control rule matched /api/admin/customers');
        self::assertContains('ROLE_ADMIN', $roles);
    }

    public function testFrontAccountApiRequiresRoleCustomer(): void
    {
        [$roles] = $this->accessMap->getPatterns(Request::create('/api/front/account/customers/1'));

        self::assertNotNull($roles, 'No access_control rule matched /api/front/account/*');
        self::assertContains('ROLE_CUSTOMER', $roles);
    }

    public function testLoginEndpointsArePublic(): void
    {
        [$adminLoginRoles] = $this->accessMap->getPatterns(Request::create('/api/admin/login'));
        [$frontLoginRoles] = $this->accessMap->getPatterns(Request::create('/api/front/login'));

        self::assertNotNull($adminLoginRoles);
        self::assertContains('PUBLIC_ACCESS', $adminLoginRoles);
        self::assertNotNull($frontLoginRoles);
        self::assertContains('PUBLIC_ACCESS', $frontLoginRoles);
    }

    public function testApiDocsIsPublic(): void
    {
        [$roles] = $this->accessMap->getPatterns(Request::create('/api/docs'));

        self::assertNotNull($roles);
        self::assertContains('PUBLIC_ACCESS', $roles);
    }
}
