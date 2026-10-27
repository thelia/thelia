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

use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Api\Trait\RegistersGuestCustomers;

/**
 * The storefront knows the group of a customer by its code and its title, and
 * can never move the customer to another group.
 */
final class CustomerGroupFrontApiTest extends ApiTestCase
{
    use RegistersGuestCustomers;

    public function testACustomerReadsItsOwnGroupAsCodeAndTitleOnly(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup('pro', discount: 15.0, displayTaxedPrice: false, title: 'Professionals');
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password', 'customerGroup' => $group]);
        $token = $this->authenticateAsCustomer($customer);

        $account = $this->jsonRequest('GET', '/api/front/account/customers/'.$customer->getId(), token: $token);
        self::assertJsonResponseSuccessful($account);
        $body = json_decode((string) $account->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('/api/front/customer_groups/'.$group->getId(), $body['customerGroup']['@id']);
        self::assertSame('pro', $body['customerGroup']['code']);
        self::assertSame('Professionals', $body['customerGroup']['i18ns']['en_US']['title']);
        self::assertEqualsCanonicalizing(['@id', '@type', 'id', 'code', 'i18ns'], array_keys($body['customerGroup']));

        $item = $this->jsonRequest('GET', '/api/front/customer_groups/'.$group->getId(), token: $token);
        self::assertJsonResponseSuccessful($item);
        $payload = json_decode((string) $item->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('pro', $payload['code']);

        foreach (['discount', 'displayTaxedPrice', 'isDefault', 'position', 'paymentModules', 'categories', 'description'] as $secret) {
            self::assertArrayNotHasKey($secret, $payload, $secret.' is not for the storefront');
            self::assertArrayNotHasKey($secret, $body['customerGroup'], $secret.' is not for the storefront');
        }
        self::assertArrayNotHasKey('description', $payload['i18ns']['en_US'] ?? []);
    }

    public function testACustomerCannotReadAnotherGroupNorListThem(): void
    {
        $factory = $this->createFixtureFactory();
        $mine = $factory->customerGroup('mine');
        $theirs = $factory->customerGroup('theirs', discount: 30.0);
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password', 'customerGroup' => $mine]);
        $token = $this->authenticateAsCustomer($customer);

        self::assertSame(403, $this->jsonRequest('GET', '/api/front/customer_groups/'.$theirs->getId(), token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/customer_groups', token: $token)->getStatusCode());
        self::assertContains($this->jsonRequest('GET', '/api/front/customer_groups/'.$theirs->getId())->getStatusCode(), [401, 403], 'a visitor reads no group');
    }

    public function testTwoCustomersOnlySeeTheirOwnGroup(): void
    {
        $factory = $this->createFixtureFactory();
        $groupA = $factory->customerGroup('group-a');
        $groupB = $factory->customerGroup('group-b');
        $customerA = $factory->customer($factory->customerTitle(), ['password' => 'password', 'customerGroup' => $groupA]);
        $customerB = $factory->customer($factory->customerTitle(), ['password' => 'password', 'customerGroup' => $groupB]);

        $tokenA = $this->authenticateAsCustomer($customerA);
        $tokenB = $this->authenticateAsCustomer($customerB);

        self::assertSame(200, $this->jsonRequest('GET', '/api/front/customer_groups/'.$groupA->getId(), token: $tokenA)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('GET', '/api/front/customer_groups/'.$groupB->getId(), token: $tokenA)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/customer_groups/'.$groupB->getId(), token: $tokenB)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('GET', '/api/front/customer_groups/'.$groupA->getId(), token: $tokenB)->getStatusCode());

        self::assertSame(403, $this->jsonRequest('GET', '/api/front/account/customers/'.$customerB->getId(), token: $tokenA)->getStatusCode());
    }

    public function testAFrontPutNeverMovesTheCustomerToAnotherGroup(): void
    {
        $factory = $this->createFixtureFactory();
        $mine = $factory->customerGroup('mine');
        $wanted = $factory->customerGroup('wanted', discount: 50.0);
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password', 'customerGroup' => $mine]);
        $token = $this->authenticateAsCustomer($customer);
        $current = json_decode((string) $this->jsonRequest('GET', '/api/front/account/customers/'.$customer->getId(), token: $token)->getContent(), true);
        $title = $current['customerTitle']['@id'] ?? $current['customerTitle'];

        foreach ([
            '/api/front/customer_groups/'.$wanted->getId(),
            '/api/admin/customer_groups/'.$wanted->getId(),
            ['id' => $wanted->getId(), 'code' => 'wanted'],
        ] as $attempt) {
            $response = $this->jsonRequest('PUT', '/api/front/account/customers/'.$customer->getId(), [
                'customerTitle' => $title,
                'firstname' => 'Jane',
                'lastname' => 'Doe',
                'customerGroup' => $attempt,
            ], token: $token);

            self::assertJsonResponseSuccessful($response);
            CustomerTableMap::clearInstancePool();
            $stored = CustomerQuery::create()->findPk($customer->getId());
            self::assertSame($mine->getId(), $stored->getCustomerGroupId(), 'the PUT carrying a group must leave the group alone');
            self::assertSame('Jane', $stored->getFirstname(), 'the rest of the PUT is still applied');
        }
    }

    public function testNoFrontPatchExistsOnTheAccount(): void
    {
        $factory = $this->createFixtureFactory();
        $mine = $factory->customerGroup('mine-patch');
        $wanted = $factory->customerGroup('wanted-patch');
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password', 'customerGroup' => $mine]);
        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest(
            'PATCH',
            '/api/front/account/customers/'.$customer->getId(),
            ['customerGroup' => '/api/front/customer_groups/'.$wanted->getId()],
            $token,
            'merge-patch+json',
        );

        self::assertContains($response->getStatusCode(), [404, 405], (string) $response->getContent());
        CustomerTableMap::clearInstancePool();
        self::assertSame($mine->getId(), CustomerQuery::create()->findPk($customer->getId())->getCustomerGroupId());
    }

    /**
     * The guest registration is the account the storefront opens: whatever the body
     * says about a group, the account joins the default one.
     */
    public function testAGuestRegistrationThatNamesAGroupJoinsTheDefaultGroup(): void
    {
        $this->enableGuestCheckout();
        $wanted = $this->createFixtureFactory()->customerGroup('wanted-signup', discount: 50.0);

        [$response, $body] = $this->registerGuest(['customerGroup' => '/api/admin/customer_groups/'.$wanted->getId(), 'customerGroupId' => $wanted->getId()]);

        self::assertJsonResponseSuccessful($response);
        CustomerTableMap::clearInstancePool();
        $guest = CustomerQuery::create()->findPk($body['id']);
        self::assertSame(CustomerGroupQuery::findDefault()->getId(), $guest->getCustomerGroupId());
        self::assertNotSame($wanted->getId(), $guest->getCustomerGroupId());
    }

    public function testTheOpenApiDocumentNeverOffersTheGroupInAFrontWrite(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode(), substr((string) $response->getContent(), 0, 300));
        $document = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $schemas = $document['components']['schemas'] ?? [];
        $frontWrite = array_filter(
            $schemas,
            static fn (string $name): bool => str_contains($name, 'front:customer:write') || str_contains($name, 'front.customer.write'),
            \ARRAY_FILTER_USE_KEY,
        );
        self::assertNotEmpty($frontWrite, 'the front write schema of the customer is documented: '.implode(', ', \array_slice(array_keys($schemas), 0, 40)));
        foreach ($frontWrite as $name => $schema) {
            self::assertArrayNotHasKey('customerGroup', $schema['properties'] ?? [], $name);
        }

        $adminWrite = array_filter(
            $schemas,
            static fn (string $name): bool => str_contains($name, 'admin:customer:write') || str_contains($name, 'admin.customer.write'),
            \ARRAY_FILTER_USE_KEY,
        );
        $offered = array_filter($adminWrite, static fn (array $schema): bool => isset($schema['properties']['customerGroup']));
        self::assertNotEmpty($offered, 'the admin write schema offers the group, which proves the check above can fail');
    }
}
