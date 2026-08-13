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

use Thelia\Model\CountryQuery;
use Thelia\Model\StateQuery;
use Thelia\Test\ApiTestCase;

/**
 * Ownership coverage for /api/front/account/addresses/{id}.
 *
 * Guards against the IDOR regression: the Get operation shipped without
 * its security expression, so any authenticated customer could read
 * another customer's address book by enumerating sequential ids.
 */
final class AddressApiTest extends ApiTestCase
{
    public function testCustomerCanReadOwnAddress(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $address = $factory->address($customer);

        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/account/addresses/'.$address->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame($address->getId(), $data['id']);
    }

    public function testCustomerCannotReadAnotherCustomersAddress(): void
    {
        $factory = $this->createFixtureFactory();
        $victim = $factory->customer($factory->customerTitle());
        $victimAddress = $factory->address($victim);
        $attacker = $factory->customer($factory->customerTitle(), ['password' => 'password']);

        $token = $this->authenticateAsCustomer($attacker);

        $response = $this->jsonRequest('GET', '/api/front/account/addresses/'.$victimAddress->getId(), token: $token);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testCollectionOnlyExposesOwnAddresses(): void
    {
        $factory = $this->createFixtureFactory();
        $victim = $factory->customer($factory->customerTitle());
        $factory->address($victim);
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $factory->address($customer);

        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/account/addresses', token: $token);

        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(1, $response);
    }

    public function testAStateFromAnotherCountryIsRejected(): void
    {
        $factory = $this->createFixtureFactory();
        $title = $factory->customerTitle();
        $customer = $factory->customer($title, ['password' => 'password']);

        $country = CountryQuery::create()->findOneByIsoalpha3('FRA');
        $elsewhere = CountryQuery::create()->findOneByIsoalpha3('USA');
        self::assertNotNull($country);
        self::assertNotNull($elsewhere);

        $state = StateQuery::create()->filterByCountryId($elsewhere->getId())->findOne();
        self::assertNotNull($state);

        $response = $this->jsonRequest('POST', '/api/front/account/addresses', [
            'label' => 'Home',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'address1' => '1 Main Street',
            'address2' => '',
            'address3' => '',
            'city' => 'Paris',
            'zipcode' => '75001',
            'country' => '/api/front/countries/'.$country->getId(),
            'state' => '/api/admin/states/'.$state->getId(),
            'customerTitle' => '/api/admin/customer_titles/'.$title->getId(),
        ], $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode(), substr((string) $response->getContent(), 0, 500));
        self::assertStringContainsString('belong to this country', (string) $response->getContent());
    }

    public function testUnauthenticatedReadIsRejected(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $address = $factory->address($customer);

        $response = $this->jsonRequest('GET', '/api/front/account/addresses/'.$address->getId());

        self::assertContains($response->getStatusCode(), [401, 403]);
    }
}
