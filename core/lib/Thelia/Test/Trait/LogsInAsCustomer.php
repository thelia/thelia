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
use Thelia\Model\Customer;

/**
 * Customer authentication helpers. Mirrors {@see LogsInAsAdmin}:
 *   - {@see loginAsCustomerInSession()} — direct injection in the session.
 *   - {@see authenticateAsCustomer()} — POST /api/front/login, returns JWT.
 */
trait LogsInAsCustomer
{
    protected function loginAsCustomerInSession(?Customer $customer = null): Customer
    {
        if (null === $customer) {
            $factory = $this->createFixtureFactory();
            $customer = $factory->customer($factory->customerTitle());
        }

        $requestStack = static::getContainer()->get('request_stack');
        if (null === $requestStack->getMainRequest()) {
            $requestStack->push(Request::create('http://localhost'));
        }

        $this->getService(SecurityContext::class)->setCustomerUser($customer);

        return $customer;
    }

    protected function logoutCustomer(): void
    {
        $this->getService(SecurityContext::class)->clearCustomerUser();
    }

    protected function authenticateAsCustomer(?Customer $customer = null, string $password = 'password'): string
    {
        if (null === $customer) {
            $factory = $this->createFixtureFactory();
            $customer = $factory->customer($factory->customerTitle(), ['password' => $password]);
        }

        $this->client->request(
            'POST',
            '/api/front/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'username' => $customer->getEmail(),
                'password' => $password,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();
        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(\sprintf('Customer JWT login failed (%d): %s', $response->getStatusCode(), (string) $response->getContent()));
        }

        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        if (!\is_array($payload) || !\array_key_exists('token', $payload)) {
            throw new \RuntimeException('Customer JWT login response did not contain a "token" key.');
        }

        return (string) $payload['token'];
    }
}
