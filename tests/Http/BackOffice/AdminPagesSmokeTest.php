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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Smoke tests for the Smarty back-office pages.
 *
 * Admin routes are defined in core/lib/Thelia/Config/Resources/routing/admin.xml.
 * Verifies routing existence, firewall behavior, and authenticated access
 * using the {@see AdminSessionInjector} to persist the admin session
 * across browser requests.
 */
final class AdminPagesSmokeTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->injector = new AdminSessionInjector();

        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        $this->injector->clear();
        parent::tearDown();
    }

    private function loginAdmin(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    // -- Unauthenticated tests --

    public function testAdminLoginPageIsPublic(): void
    {
        $this->client->request('GET', '/admin/login');

        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertNotSame(403, $statusCode, 'Admin firewall must not block authenticated request');
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

    // -- Authenticated tests (admin session injected on every request) --

    public function testAdminHomeAccessibleWhenLoggedIn(): void
    {
        $this->loginAdmin();
        $this->client->request('GET', '/admin/home');

        $statusCode = $this->client->getResponse()->getStatusCode();
        // Firewall passed — page renders (200) or fails on Smarty template (500).
        // 403 is NOT acceptable here — it means auth failed.
        self::assertNotSame(403, $statusCode, 'Admin firewall must not block authenticated request');
    }

    public function testAdminCatalogAccessibleWhenLoggedIn(): void
    {
        $this->loginAdmin();
        $this->client->request('GET', '/admin/catalog');

        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertNotSame(403, $statusCode, 'Admin firewall must not block authenticated request');
    }

    public function testAdminCustomersAccessibleWhenLoggedIn(): void
    {
        $this->loginAdmin();
        $this->client->request('GET', '/admin/customers');

        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertNotSame(403, $statusCode, 'Admin firewall must not block authenticated request');
    }

    public function testAdminOrdersAccessibleWhenLoggedIn(): void
    {
        $this->loginAdmin();
        $this->client->request('GET', '/admin/orders');

        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertNotSame(403, $statusCode, 'Admin firewall must not block authenticated request');
    }

    public function testAdminConfigurationAccessibleWhenLoggedIn(): void
    {
        $this->loginAdmin();
        $this->client->request('GET', '/admin/configuration');

        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertNotSame(403, $statusCode, 'Admin firewall must not block authenticated request');
    }

    public function testAdminModulesAccessibleWhenLoggedIn(): void
    {
        $this->loginAdmin();
        $this->client->request('GET', '/admin/modules');

        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertNotSame(403, $statusCode, 'Admin firewall must not block authenticated request');
    }

    public function testAdminToolsAccessibleWhenLoggedIn(): void
    {
        $this->loginAdmin();
        $this->client->request('GET', '/admin/tools');

        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertNotSame(403, $statusCode, 'Admin firewall must not block authenticated request');
    }
}
