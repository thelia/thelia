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
use Thelia\Model\StateQuery;
use Thelia\Test\ApiTestCase;

/**
 * State coverage for /api/admin/addresses.
 *
 * A country requiring a state must answer a validation error rather than crash,
 * and a state must always belong to the country it is posted with, even when the
 * country leaves the choice optional.
 */
final class AddressApiTest extends ApiTestCase
{
    public function testMissingStateOnACountryRequiringOneIsAValidationError(): void
    {
        $country = CountryQuery::create()->findOneByIsoalpha3('USA');
        self::assertNotNull($country);

        $response = $this->jsonRequest('POST', '/api/admin/addresses', $this->payload($country->getId()), $this->authenticateAsAdmin());

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('You should select a state', (string) $response->getContent());
    }

    public function testAStateFromAnotherCountryIsRejectedWhenTheStateIsOptional(): void
    {
        $optional = CountryQuery::create()->findOneByIsoalpha3('FRA');
        $elsewhere = CountryQuery::create()->findOneByIsoalpha3('USA');
        self::assertNotNull($optional);
        self::assertNotNull($elsewhere);
        self::assertFalse((bool) $optional->getHasStates(), 'France leaves the department optional');

        $state = StateQuery::create()->filterByCountryId($elsewhere->getId())->findOne();
        self::assertNotNull($state);

        $payload = $this->payload($optional->getId());
        $payload['state'] = '/api/admin/states/'.$state->getId();

        $response = $this->jsonRequest('POST', '/api/admin/addresses', $payload, $this->authenticateAsAdmin());

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('belong to this country', (string) $response->getContent());
    }

    public function testTheOptionalAddressLinesMayBeOmitted(): void
    {
        $country = CountryQuery::create()->findOneByIsoalpha3('FRA');
        self::assertNotNull($country);

        $payload = $this->payload($country->getId());
        unset($payload['address2'], $payload['address3']);

        $response = $this->jsonRequest('POST', '/api/admin/addresses', $payload, $this->authenticateAsAdmin());

        self::assertJsonResponseSuccessful($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $countryId): array
    {
        $factory = $this->createFixtureFactory();
        $title = $factory->customerTitle();
        $customer = $factory->customer($title);

        return [
            'label' => 'Home',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'address1' => '1 Main Street',
            'address2' => '',
            'address3' => '',
            'city' => 'Springfield',
            'zipcode' => '12345',
            'country' => '/api/admin/countries/'.$countryId,
            'customer' => '/api/admin/customers/'.$customer->getId(),
            'customerTitle' => '/api/admin/customer_titles/'.$title->getId(),
        ];
    }
}
