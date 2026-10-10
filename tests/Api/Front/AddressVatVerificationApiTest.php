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

use Thelia\Model\Address;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Test\ApiTestCase;

/**
 * A buyer whose address carries a verified number must not keep that verification
 * when he replaces the number (or the number and the country) through the front PUT:
 * otherwise he is exempted on a number nobody ever verified.
 */
final class AddressVatVerificationApiTest extends ApiTestCase
{
    public function testReplacingTheNumberThroughTheFrontPutDropsTheVerification(): void
    {
        [$customer, $address] = $this->verifiedBelgianAddress();

        $this->putAddress($customer, $address, 'BEL', 'BE0987654321', 'Bruxelles', '1000');

        $row = $this->row($address);
        self::assertSame('BE0987654321', $row['vat_number'], 'Control: the PUT went through.');
        self::assertNull($row['vat_verified_at'], 'The verification of BE0123456789 must not vouch for BE0987654321.');
        self::assertNull($row['vat_verified_name']);
    }

    public function testMovingNumberAndCountryThroughTheFrontPutDropsTheVerification(): void
    {
        [$customer, $address] = $this->verifiedBelgianAddress();

        $this->putAddress($customer, $address, 'DEU', 'DE999999999', 'Berlin', '10115');

        $row = $this->row($address);
        self::assertSame('DE999999999', $row['vat_number'], 'Control: the PUT went through.');
        self::assertNull($row['vat_verified_at'], 'A Belgian verification must not vouch for a German number.');
        self::assertNull($row['vat_verified_name']);
    }

    public function testPuttingTheSameNumberThroughTheFrontPutKeepsTheVerification(): void
    {
        [$customer, $address] = $this->verifiedBelgianAddress();

        $this->putAddress($customer, $address, 'BEL', 'BE0123456789', 'Liège', '4000');

        $row = $this->row($address);
        self::assertSame('BE0123456789', $row['vat_number'], 'Control: the number did not change.');
        self::assertNotNull($row['vat_verified_at'], 'The number and the country are the same: the PUT must not blank the verification.');
        self::assertSame('Acme SPRL', $row['vat_verified_name']);
    }

    /**
     * @return array{0: Customer, 1: Address}
     */
    private function verifiedBelgianAddress(): array
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $address = $factory->address($customer);
        $belgium = CountryQuery::create()->findOneByIsoalpha3('BEL');
        self::assertNotNull($belgium);

        // Written straight to the row, as the verification service would leave it.
        $this->getPropelConnection()->prepare(
            'UPDATE `address` SET `company` = ?, `vat_number` = ?, `vat_verified_at` = NOW(), `vat_verified_name` = ?, `country_id` = ? WHERE `id` = ?'
        )->execute(['Acme', 'BE0123456789', 'Acme SPRL', $belgium->getId(), $address->getId()]);

        return [$customer, $address];
    }

    private function putAddress(Customer $customer, Address $address, string $countryIso3, string $vatNumber, string $city, string $zipcode): void
    {
        $country = CountryQuery::create()->findOneByIsoalpha3($countryIso3);
        self::assertNotNull($country);

        $response = $this->jsonRequest(
            'PUT',
            '/api/front/account/addresses/'.$address->getId(),
            [
                'label' => 'Home',
                'firstname' => 'John',
                'lastname' => 'Doe',
                'address1' => '1 Main Street',
                'city' => $city,
                'zipcode' => $zipcode,
                'company' => 'Acme',
                'vatNumber' => $vatNumber,
                'country' => '/api/front/countries/'.$country->getId(),
                'customerTitle' => '/api/admin/customer_titles/'.$customer->getCustomerTitle()->getId(),
            ],
            $this->authenticateAsCustomer($customer),
        );

        self::assertJsonResponseSuccessful($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Address $address): array
    {
        $statement = $this->getPropelConnection()->prepare('SELECT `vat_number`, `vat_verified_at`, `vat_verified_name` FROM `address` WHERE `id` = ?');
        $statement->execute([$address->getId()]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }
}
