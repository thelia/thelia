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

namespace Thelia\Tests\Api\Front;

use Thelia\Test\ApiTestCase;

/**
 * Ownership coverage for /api/front/account/customers/{id}.
 *
 * Guards against the IDOR regression: any authenticated customer
 * could previously read or update another customer's account by
 * passing an arbitrary id on Get / Put.
 */
final class CustomerApiTest extends ApiTestCase
{
    public function testCustomerCanReadOwnAccount(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);

        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/account/customers/'.$customer->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame($customer->getId(), $data['id']);
    }

    public function testCustomerCannotReadAnotherCustomersAccount(): void
    {
        $factory = $this->createFixtureFactory();
        $other = $factory->customer($factory->customerTitle());
        $attacker = $factory->customer($factory->customerTitle(), ['password' => 'password']);

        $token = $this->authenticateAsCustomer($attacker);

        $response = $this->jsonRequest('GET', '/api/front/account/customers/'.$other->getId(), token: $token);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testCustomerCannotUpdateAnotherCustomersAccount(): void
    {
        $factory = $this->createFixtureFactory();
        $other = $factory->customer($factory->customerTitle());
        $attacker = $factory->customer($factory->customerTitle(), ['password' => 'password']);

        $token = $this->authenticateAsCustomer($attacker);

        $response = $this->jsonRequest(
            'PUT',
            '/api/front/account/customers/'.$other->getId(),
            payload: ['firstname' => 'Hijacked'],
            token: $token,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testUnauthenticatedReadIsRejected(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());

        $response = $this->jsonRequest('GET', '/api/front/account/customers/'.$customer->getId());

        self::assertContains($response->getStatusCode(), [401, 403]);
    }
}
