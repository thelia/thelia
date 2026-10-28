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

use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Test\ApiTestCase;

/**
 * A customer updates their own account with `PUT /api/front/account/customers/{id}`.
 *
 * The front write group carries the title, the language and the names only. What
 * the customer cannot write (e-mail, reference, discount, reseller flag) has to
 * keep its value: replacing the whole resource used to set the e-mail to NULL,
 * after which the customer could no longer sign in.
 */
final class AccountCustomerUpdateApiTest extends ApiTestCase
{
    public function testUpdatingTheNamesKeepsWhatTheCustomerCannotWrite(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password', 'email' => 'keeps-email@test.com']);
        $customer->setRef('CUST-KEEP')->setDiscount('5.000000')->save($this->getPropelConnection());
        $customerId = $customer->getId();

        $token = $this->authenticateAsCustomer($customer);
        $current = json_decode($this->jsonRequest('GET', '/api/front/account/customers/'.$customerId, token: $token)->getContent(), true);

        $response = $this->jsonRequest('PUT', '/api/front/account/customers/'.$customerId, [
            'customerTitle' => $current['customerTitle']['@id'] ?? $current['customerTitle'],
            'firstname' => 'Jane',
            'lastname' => 'Updated',
        ], token: $token);

        self::assertJsonResponseSuccessful($response);

        CustomerTableMap::clearInstancePool();
        $saved = CustomerQuery::create()->findPk($customerId);

        self::assertSame('Jane', $saved->getFirstname());
        self::assertSame('Updated', $saved->getLastname());
        self::assertSame('keeps-email@test.com', $saved->getEmail());
        self::assertSame('CUST-KEEP', $saved->getRef());
        self::assertEquals(5.0, (float) $saved->getDiscount());
        self::assertNotEmpty($this->authenticateAsCustomer($saved), 'The customer must still be able to sign in.');
    }

    /**
     * The reference is written by the administration only.
     */
    public function testTheCustomerCannotWriteTheReference(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $customer->setRef('CUST-OWN')->save($this->getPropelConnection());
        $customerId = (int) $customer->getId();

        $token = $this->authenticateAsCustomer($customer);
        $current = json_decode($this->jsonRequest('GET', '/api/front/account/customers/'.$customerId, token: $token)->getContent(), true);

        $response = $this->jsonRequest('PUT', '/api/front/account/customers/'.$customerId, [
            'customerTitle' => $current['customerTitle']['@id'] ?? $current['customerTitle'],
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'ref' => 'CUST-CHOSEN',
        ], token: $token);

        self::assertJsonResponseSuccessful($response);

        CustomerTableMap::clearInstancePool();
        self::assertSame('CUST-OWN', CustomerQuery::create()->findPk($customerId)?->getRef());
    }

    public function testTheAccountReadNeverExposesTheEmailConfirmationToken(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password', 'email' => 'token-hidden@test.com']);
        $customer->setConfirmationToken('secret-confirmation-token')->save($this->getPropelConnection());

        $token = $this->authenticateAsCustomer($customer);
        $response = $this->jsonRequest('GET', '/api/front/account/customers/'.$customer->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        self::assertArrayNotHasKey('confirmationToken', json_decode((string) $response->getContent(), true));
        self::assertStringNotContainsString('secret-confirmation-token', (string) $response->getContent());
    }

    public function testTheOpenApiFrontReadSchemaOfTheCustomerHasNoConfirmationToken(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        $schemas = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['components']['schemas'] ?? [];

        $frontCustomerReads = array_filter(
            array_keys($schemas),
            static fn (string $name): bool => str_contains($name, 'front') && str_contains($name, 'customer') && str_contains($name, 'read') && !str_contains($name, 'group'),
        );
        self::assertNotEmpty($frontCustomerReads, 'No front customer read schema found: the check below proves nothing.');

        foreach ($frontCustomerReads as $name) {
            self::assertArrayNotHasKey('confirmationToken', $schemas[$name]['properties'] ?? [], $name);
        }
    }
}
