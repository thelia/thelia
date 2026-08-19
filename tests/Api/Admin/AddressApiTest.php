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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\AddressQuery;
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

    public function testTheLegalIdentifiersAreReadBack(): void
    {
        $country = CountryQuery::create()->findOneByIsoalpha3('FRA');
        self::assertNotNull($country);

        $payload = $this->payload($country->getId());
        $payload['company'] = 'Acme SAS';
        $payload['siret'] = '303 265 045 00003';
        $payload['vatNumber'] = 'fr 40 303 265 045';

        $response = $this->jsonRequest('POST', '/api/admin/addresses', $payload, $this->authenticateAsAdmin());

        self::assertJsonResponseSuccessful($response);

        $created = json_decode((string) $response->getContent(), true);

        // Stored in the canonical form, not as they were typed.
        self::assertSame('30326504500003', $created['siret']);
        self::assertSame('FR40303265045', $created['vatNumber']);
    }

    public function testACompanyAddressWithoutAVatNumberIsAValidationError(): void
    {
        $country = CountryQuery::create()->findOneByIsoalpha3('FRA');
        self::assertNotNull($country);

        $payload = $this->payload($country->getId());
        $payload['company'] = 'Acme SAS';
        $payload['siret'] = '30326504500003';

        $response = $this->jsonRequest('POST', '/api/admin/addresses', $payload, $this->authenticateAsAdmin());

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('VAT number is required', (string) $response->getContent());
    }

    public function testAnAddressWithoutACompanyNameNeedsNoIdentifier(): void
    {
        $country = CountryQuery::create()->findOneByIsoalpha3('FRA');
        self::assertNotNull($country);

        $response = $this->jsonRequest('POST', '/api/admin/addresses', $this->payload($country->getId()), $this->authenticateAsAdmin());

        self::assertJsonResponseSuccessful($response);
        $this->assertStoredIdentifiersAreNull($response);
    }

    /**
     * A payload that skips the browser cannot smuggle identifiers onto an address that
     * carries no company name: they are dropped rather than stored.
     */
    public function testIdentifiersPostedWithoutACompanyNameAreDropped(): void
    {
        $country = CountryQuery::create()->findOneByIsoalpha3('FRA');
        self::assertNotNull($country);

        $payload = $this->payload($country->getId());
        $payload['siret'] = '30326504500003';
        $payload['vatNumber'] = 'FR40303265045';

        $response = $this->jsonRequest('POST', '/api/admin/addresses', $payload, $this->authenticateAsAdmin());

        self::assertJsonResponseSuccessful($response);
        $this->assertStoredIdentifiersAreNull($response);
    }

    /**
     * Asserted on the stored row rather than on the response body: the serializer leaves a
     * null property out of the payload, so an absent key would satisfy assertNull by accident.
     */
    private function assertStoredIdentifiersAreNull(Response $response): void
    {
        $created = json_decode((string) $response->getContent(), true);
        self::assertIsArray($created);
        self::assertArrayHasKey('id', $created);

        $address = AddressQuery::create()->findPk($created['id']);
        self::assertNotNull($address);
        self::assertNull($address->getSiret());
        self::assertNull($address->getVatNumber());
    }

    public function testASiretWithAnInvalidChecksumIsAValidationError(): void
    {
        $country = CountryQuery::create()->findOneByIsoalpha3('FRA');
        self::assertNotNull($country);

        $payload = $this->payload($country->getId());
        $payload['company'] = 'Acme SAS';
        $payload['siret'] = '12345678900011';
        $payload['vatNumber'] = 'FR40303265045';

        $response = $this->jsonRequest('POST', '/api/admin/addresses', $payload, $this->authenticateAsAdmin());

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('checksum of this SIRET', (string) $response->getContent());
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
