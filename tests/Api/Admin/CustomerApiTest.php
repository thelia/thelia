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

use Thelia\Model\CustomerQuery;
use Thelia\Test\ApiTestCase;

final class CustomerApiTest extends ApiTestCase
{
    public function testGetCustomerReturnsResource(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());

        $response = $this->jsonRequest('GET', '/api/admin/customers/'.$customer->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame($customer->getId(), $data['id']);
        self::assertSame($customer->getFirstname(), $data['firstname']);
        self::assertSame($customer->getLastname(), $data['lastname']);
    }

    public function testListCustomersReturnsCollection(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $factory->customer($factory->customerTitle());
        $factory->customer($factory->customerTitle());

        $response = $this->jsonRequest('GET', '/api/admin/customers', token: $token);

        self::assertJsonResponseSuccessful($response);
        // At least 2 fixture customers + the admin's implicit customer (if any).
        $data = json_decode($response->getContent(), true);
        self::assertGreaterThanOrEqual(2, $data['hydra:totalItems']);
    }

    // Customer PATCH via API is blocked by a schema mismatch:
    // customer_version.confirmation_token_expires_at column is missing
    // from the test DB (migration not applied). Propel's versionable
    // behavior tries to read it on save → Column not found.

    public function testDeleteCustomerRemovesResource(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $id = $customer->getId();

        $response = $this->jsonRequest('DELETE', '/api/admin/customers/'.$id, token: $token);

        self::assertSame(204, $response->getStatusCode());
        self::assertNull(CustomerQuery::create()->findPk($id));
    }

    public function testGetCustomerReturns404ForNonExistent(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('GET', '/api/admin/customers/999999', token: $token);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testFilterByEmailReturnsMatchingCustomer(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['email' => 'filter-test@example.com']);

        $response = $this->jsonRequest('GET', '/api/admin/customers?email=filter-test@example.com', token: $token);

        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(1, $response);
    }
}
