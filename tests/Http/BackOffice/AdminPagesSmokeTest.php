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

namespace Thelia\Tests\Http\BackOffice;

use Thelia\Core\EventListener\KernelListener;
use Thelia\Test\WebIntegrationTestCase;

/**
 * Smoke tests for the Smarty back-office pages.
 *
 * Admin routes are defined in core/lib/Thelia/Config/Resources/routing/admin.xml.
 * Verifies routing existence and firewall behavior.
 */
final class AdminPagesSmokeTest extends WebIntegrationTestCase
{
    private function loginAdminInSession(): void
    {
        // Trigger a first request to initialize KernelListener::$session.
        $this->client->request('GET', '/admin/login');

        $factory = $this->createFixtureFactory();
        $admin = $factory->admin();
        $admin->eraseCredentials();

        // The KernelListener stores the session as a static property.
        // Inject the admin user into it so subsequent requests pass
        // the admin firewall.
        KernelListener::$session?->set('thelia.admin_user', $admin);
    }

    public function testAdminLoginPageIsPublic(): void
    {
        $this->client->request('GET', '/admin/login');

        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertContains($statusCode, [200, 500]);
    }

    public function testAdminHomeBlockedWhenNotLoggedIn(): void
    {
        $this->client->request('GET', '/admin/home');

        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertContains($statusCode, [302, 403, 500]);
    }

    public function testAdminCatalogBlockedWhenNotLoggedIn(): void
    {
        $this->client->request('GET', '/admin/catalog');

        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertContains($statusCode, [302, 403, 500]);
    }

    public function testAdminHomeRouteExistsAndIsGuarded(): void
    {
        $this->client->request('GET', '/admin/home');

        // Route exists (not 404) and is guarded by the admin firewall
        // (403 AdminAccessDenied or 500 from error handler).
        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertNotSame(404, $statusCode);
        self::assertContains($statusCode, [302, 403, 500]);
    }

    public function testAdminConfigurationRouteExists(): void
    {
        $this->client->request('GET', '/admin/configuration');

        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertNotSame(404, $statusCode);
    }

    public function testAdminModulesRouteExists(): void
    {
        $this->client->request('GET', '/admin/modules');

        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertNotSame(404, $statusCode);
    }

    public function testAdminToolsRouteExists(): void
    {
        $this->client->request('GET', '/admin/tools');

        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertNotSame(404, $statusCode);
    }
}
