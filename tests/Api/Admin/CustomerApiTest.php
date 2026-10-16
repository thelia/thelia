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

use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Model\CountryQuery;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;

final class CustomerApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        StoredMethodsPaymentModule::reset();
    }

    protected function tearDown(): void
    {
        StoredMethodsPaymentModule::reset();

        parent::tearDown();
    }

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

    /**
     * The admin API deletes a customer through the same action as the back office,
     * so the provider is told to forget the stored methods the cascade drops.
     */
    public function testDeleteCustomerTellsTheProviderToForgetTheirStoredMethods(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $method = $this->getService(CustomerPaymentMethodService::class)
            ->register($customer, $this->paymentModuleRow(), 'pm_1', 'Visa ending 1111', null);
        $methodId = (int) $method->getId();

        $response = $this->jsonRequest('DELETE', '/api/admin/customers/'.$customer->getId(), token: $token);

        self::assertSame(204, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(CustomerQuery::create()->findPk($customer->getId()));
        self::assertNull(CustomerPaymentMethodQuery::create()->findPk($methodId));
        self::assertSame([$methodId], StoredMethodsPaymentModule::$forgottenMethodIds, 'The provider was asked to forget it.');
    }

    public function testDeleteCustomerWithOrdersIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $factory->order($customer);

        $response = $this->jsonRequest('DELETE', '/api/admin/customers/'.$customer->getId(), token: $token);

        self::assertSame(409, $response->getStatusCode(), (string) $response->getContent());
        self::assertNotNull(CustomerQuery::create()->findPk($customer->getId()));
    }

    public function testDeleteCustomerReturns404ForNonExistent(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('DELETE', '/api/admin/customers/999999', token: $token);

        self::assertSame(404, $response->getStatusCode());
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
     * Another system replaces the customer with its full record on every
     * synchronisation, its own reference included.
     */
    public function testThePutKeepsItsOwnReference(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $customer->setRef('C00128')->save($this->getPropelConnection());
        $customerId = (int) $customer->getId();

        $response = $this->jsonRequest('PUT', '/api/admin/customers/'.$customerId, $this->customerPayload((string) $customer->getEmail()) + ['ref' => 'C00128'], $this->authenticateAsAdmin());

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        CustomerTableMap::clearInstancePool();
        self::assertSame('C00128', CustomerQuery::create()->findPk($customerId)?->getRef());
    }

    public function testThePutWithoutAReferenceKeepsTheStoredOne(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $customer->setRef('C00129')->save($this->getPropelConnection());
        $customerId = (int) $customer->getId();

        $response = $this->jsonRequest('PUT', '/api/admin/customers/'.$customerId, $this->customerPayload((string) $customer->getEmail()), $this->authenticateAsAdmin());

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        CustomerTableMap::clearInstancePool();
        self::assertSame('C00129', CustomerQuery::create()->findPk($customerId)?->getRef());
    }

    public function testABlankReferenceIsRefused(): void
    {
        $response = $this->jsonRequest('POST', '/api/admin/customers', $this->customerPayload('ref-blank@example.com') + ['ref' => ' '], $this->authenticateAsAdmin());

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        $error = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['ref'], array_column($error['violations'], 'propertyPath'));
        self::assertNull(CustomerQuery::create()->findOneByEmail('ref-blank@example.com'));
    }

    /**
     * Another system reads the customers changed since its last synchronisation.
     */
    public function testFilterByUpdatedAfterReturnsTheCustomersChangedSince(): void
    {
        $factory = $this->createFixtureFactory();
        $title = $factory->customerTitle();
        $recent = $factory->customer($title);
        $old = $factory->customer($title);
        $recentId = (int) $recent->getId();
        $oldId = (int) $old->getId();
        $this->stampCustomer($recentId, '2099-06-01 10:00:00');
        $this->stampCustomer($oldId, '2001-06-01 10:00:00');

        $ids = $this->customerIds('/api/admin/customers?updatedAt[after]=2099-01-01T00:00:00&itemsPerPage=100');

        self::assertContains($recentId, $ids);
        self::assertNotContains($oldId, $ids);

        $ids = $this->customerIds('/api/admin/customers?updatedAt[before]=2002-01-01T00:00:00&itemsPerPage=100');

        self::assertContains($oldId, $ids);
        self::assertNotContains($recentId, $ids);
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

    /**
     * @return list<int>
     */
    private function customerIds(string $uri): array
    {
        $response = $this->jsonRequest('GET', $uri, token: $this->authenticateAsAdmin());

        self::assertJsonResponseSuccessful($response);

        return array_map(
            static fn (array $item): int => (int) $item['id'],
            json_decode((string) $response->getContent(), true)['hydra:member'],
        );
    }

    private function stampCustomer(int $customerId, string $date): void
    {
        CustomerQuery::create()->filterById($customerId)->update(['UpdatedAt' => $date], $this->getPropelConnection());
        CustomerTableMap::clearInstancePool();
    }

    private function paymentModuleRow(): Module
    {
        $code = StoredMethodsPaymentModule::getModuleCode();
        $existing = ModuleQuery::create()->findOneByCode($code);

        if (null !== $existing) {
            return $existing;
        }

        $module = (new Module())
            ->setCode($code)
            ->setFullNamespace(StoredMethodsPaymentModule::class)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED);
        $module->save($this->getPropelConnection());

        return $module;
    }
}
