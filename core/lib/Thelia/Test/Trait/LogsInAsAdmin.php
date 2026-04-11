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

namespace Thelia\Test\Trait;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Admin;

/**
 * Admin authentication helpers for {@see \Thelia\Test\WebIntegrationTestCase}
 * and its subclasses.
 *
 * Exposes two complementary flows:
 *   - {@see loginAsAdminInSession()} — direct injection via SecurityContext
 *     for tests that need an authenticated admin session without going
 *     through the HTTP login form. Fast, bypasses the form authenticator.
 *   - {@see authenticateAsAdmin()} — real POST on /api/admin/login to get a
 *     JWT token, used by API tests.
 *
 * The full HTTP login flow of the SessionController is covered by dedicated
 * tests in tests/Http/BackOffice/Auth/, not by this helper.
 */
trait LogsInAsAdmin
{
    protected function loginAsAdminInSession(?Admin $admin = null): Admin
    {
        $admin ??= $this->createFixtureFactory()->admin();

        // Ensure the RequestStack carries a request — getSession() reads it
        // from the main request. On a fresh client nothing has been pushed
        // yet so we push a synthetic one.
        $requestStack = static::getContainer()->get('request_stack');
        if (null === $requestStack->getMainRequest()) {
            $requestStack->push(Request::create('http://localhost'));
        }

        $this->getService(SecurityContext::class)->setAdminUser($admin);

        return $admin;
    }

    protected function logoutAdmin(): void
    {
        $this->getService(SecurityContext::class)->clearAdminUser();
    }

    protected function authenticateAsAdmin(?Admin $admin = null, string $password = 'password'): string
    {
        $admin ??= $this->createFixtureFactory()->admin(['password' => $password]);

        $this->client->request(
            'POST',
            '/api/admin/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'username' => $admin->getLogin(),
                'password' => $password,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();
        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(\sprintf('Admin JWT login failed (%d): %s', $response->getStatusCode(), (string) $response->getContent()));
        }

        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        if (!\is_array($payload) || !\array_key_exists('token', $payload)) {
            throw new \RuntimeException('Admin JWT login response did not contain a "token" key.');
        }

        return (string) $payload['token'];
    }
}
