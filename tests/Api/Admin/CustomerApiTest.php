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

use Thelia\Model\CountryQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Test\ApiTestCase;

final class CustomerApiTest extends ApiTestCase
{
    /**
     * firstname and lastname are NOT NULL columns with no default. A payload
     * that misspells them - an ERP sending firstName rather than firstname -
     * used to reach the database and come back as a 500 carrying the failed
     * INSERT, with nothing naming the field at fault.
     */
    public function testCreateCustomerWithoutANameIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $title = $factory->customerTitle();

        $response = $this->jsonRequest('POST', '/api/admin/customers', [
            'customerTitle' => '/api/admin/customer_titles/'.$title->getId(),
            'email' => 'no-name@example.com',
            'password' => 'Password1!',
        ], $token);

        self::assertSame(422, $response->getStatusCode(), $response->getContent());
        self::assertStringContainsString('firstname', $response->getContent());
        self::assertNull(CustomerQuery::create()->findOneByEmail('no-name@example.com'));
    }

    /**
     * The addresses posted with a customer are validated one by one while they
     * are turned into models. A violation found there must name the address it
     * comes from, otherwise the client reads "label" on a customer, which has
     * no such field, and cannot tell which of its addresses is at fault.
     */
    public function testAViolationInAPostedAddressNamesThatAddress(): void
    {
        $token = $this->authenticateAsAdmin();

        $title = $this->createFixtureFactory()->customerTitle();
        $country = CountryQuery::create()->findOneByIsoalpha3('FRA');
        self::assertNotNull($country);

        $address = [
            'label' => 'Home',
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'address1' => '1 rue de la Paix',
            'zipcode' => '63000',
            'city' => 'Clermont-Ferrand',
            'country' => '/api/admin/countries/'.$country->getId(),
            'customerTitle' => '/api/admin/customer_titles/'.$title->getId(),
        ];

        $response = $this->jsonRequest('POST', '/api/admin/customers', [
            'customerTitle' => '/api/admin/customer_titles/'.$title->getId(),
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'email' => 'nested-address@example.com',
            'password' => 'Password1!',
            'addresses' => [$address, ['label' => ''] + $address],
        ], $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        $error = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['addresses[1].label'], array_column($error['violations'], 'propertyPath'));
        self::assertSame('addresses[1].label: This value should not be blank.', $error['detail']);
        self::assertNull(CustomerQuery::create()->findOneByEmail('nested-address@example.com'));
    }

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

    /**
     * Another system that already knows the customer by a code sets it as the
     * reference instead of receiving a generated one.
     */
    public function testAnAdministrationClientSetsTheReference(): void
    {
        $response = $this->jsonRequest('POST', '/api/admin/customers', $this->customerPayload('ref-set@example.com') + ['ref' => 'C00123'], $this->authenticateAsAdmin());

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('C00123', json_decode((string) $response->getContent(), true)['ref']);

        CustomerTableMap::clearInstancePool();
        self::assertSame('C00123', CustomerQuery::create()->findOneByEmail('ref-set@example.com')?->getRef());
    }

    public function testTheReferenceIsGeneratedWhenLeftOut(): void
    {
        $response = $this->jsonRequest('POST', '/api/admin/customers', $this->customerPayload('ref-generated@example.com'), $this->authenticateAsAdmin());

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $ref = json_decode((string) $response->getContent(), true)['ref'];
        self::assertIsString($ref);
        self::assertNotSame('', $ref);
    }

    /**
     * The reference is unique in the database. A duplicate used to be the
     * failed INSERT of the unique index; it is a violation on the field.
     */
    public function testADuplicateReferenceIsRefusedOnCreation(): void
    {
        $factory = $this->createFixtureFactory();
        $factory->customer($factory->customerTitle())->setRef('C00124')->save($this->getPropelConnection());

        $response = $this->jsonRequest('POST', '/api/admin/customers', $this->customerPayload('ref-duplicate@example.com') + ['ref' => 'C00124'], $this->authenticateAsAdmin());

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        $error = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['ref'], array_column($error['violations'], 'propertyPath'));
        self::assertNull(CustomerQuery::create()->findOneByEmail('ref-duplicate@example.com'));
    }

    public function testThePatchSetsTheReferenceAndRefusesADuplicate(): void
    {
        $factory = $this->createFixtureFactory();
        $title = $factory->customerTitle();
        $factory->customer($title)->setRef('C00125')->save($this->getPropelConnection());
        $customerId = (int) $factory->customer($title)->getId();
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('PATCH', '/api/admin/customers/'.$customerId, ['ref' => 'C00126'], $token, 'merge-patch+json');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        CustomerTableMap::clearInstancePool();
        self::assertSame('C00126', CustomerQuery::create()->findPk($customerId)?->getRef());

        $response = $this->jsonRequest('PATCH', '/api/admin/customers/'.$customerId, ['ref' => 'C00125'], $token, 'merge-patch+json');

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        $error = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['ref'], array_column($error['violations'], 'propertyPath'));
        CustomerTableMap::clearInstancePool();
        self::assertSame('C00126', CustomerQuery::create()->findPk($customerId)?->getRef());
    }

    /**
     * Saving its own reference again is not a duplicate.
     */
    public function testThePatchKeepsItsOwnReference(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $customer->setRef('C00127')->save($this->getPropelConnection());
        $customerId = (int) $customer->getId();

        $response = $this->jsonRequest('PATCH', '/api/admin/customers/'.$customerId, ['ref' => 'C00127', 'lastname' => 'Same-Ref'], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * @return array<string, mixed>
     */
    private function customerPayload(string $email): array
    {
        return [
            'customerTitle' => '/api/admin/customer_titles/'.$this->createFixtureFactory()->customerTitle()->getId(),
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'email' => $email,
            'password' => 'Password1!',
        ];
    }
}
