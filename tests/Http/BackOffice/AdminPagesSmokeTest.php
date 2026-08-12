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
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Smoke tests for the back-office pages.
 *
 * Admin routes are defined in core/lib/Thelia/Config/Resources/routing/admin.xml.
 * Verifies routing, firewall behaviour and authenticated access, using the
 * {@see AdminSessionInjector} to persist the admin session across requests.
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
        // Built without createFixtureFactory(): that helper pushes a synthetic
        // request when the stack is empty, and it would then be the "main"
        // request of the calls below — the one SecurityContext reads the session
        // from. The admin would be injected into a session nobody looks at, and
        // every page below would answer a redirect to the login form.
        $factory = new FixtureFactory($this->getPropelConnection());

        $admin = $factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    // -- Unauthenticated tests --

    public function testAdminLoginPageIsPublic(): void
    {
        $this->assertPageRenders('/admin/login');
    }

    public function testAdminHomeRedirectsToLoginWhenNotLoggedIn(): void
    {
        $this->client->request('GET', '/admin/home');

        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/admin/login', (string) $response->headers->get('Location'));
    }

    public function testAdminCatalogRedirectsToLoginWhenNotLoggedIn(): void
    {
        $this->client->request('GET', '/admin/categories');

        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode(), 'Admin catalog must never be served to an anonymous user');
        self::assertStringContainsString('/admin/login', (string) $response->headers->get('Location'));
    }

    // -- Authenticated tests (admin session injected on every request) --

    public function testAdminHomeRendersWhenLoggedIn(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders('/admin/home');
    }

    public function testAdminCatalogRendersWhenLoggedIn(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders('/admin/categories');
    }

    public function testAdminCustomersRenderWhenLoggedIn(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders('/admin/customers');
    }

    public function testAdminOrdersRenderWhenLoggedIn(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders('/admin/orders');
    }

    public function testAdminConfigurationRendersWhenLoggedIn(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders('/admin/configuration');
    }

    public function testAdminModulesRenderWhenLoggedIn(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders('/admin/modules');
    }

    public function testAdminToolsRenderWhenLoggedIn(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders('/admin/tools');
    }
}
