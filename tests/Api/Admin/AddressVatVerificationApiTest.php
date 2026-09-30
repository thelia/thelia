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

use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Test\ApiTestCase;

final class AddressVatVerificationApiTest extends ApiTestCase
{
    public function testPatchingTheVatNumberDropsThePreviousVerification(): void
    {
        $address = $this->verifiedBelgianAddress();

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/addresses/'.$address->getId(),
            ['vatNumber' => 'BE0987654321'],
            $this->authenticateAsAdmin(),
            'merge-patch+json',
        );

        self::assertJsonResponseSuccessful($response);

        $reloaded = $this->reloaded($address);
        self::assertSame('BE0987654321', $reloaded->getVatNumber());
        self::assertNull($reloaded->getVatVerifiedAt());
        self::assertNull($reloaded->getVatVerifiedName());
    }

    public function testMovingTheAddressToAnotherCountryDropsThePreviousVerification(): void
    {
        $address = $this->verifiedBelgianAddress();
        $germany = CountryQuery::create()->findOneByIsoalpha2('DE');
        self::assertNotNull($germany);

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/addresses/'.$address->getId(),
            ['country' => '/api/admin/countries/'.$germany->getId(), 'zipcode' => '10115', 'city' => 'Berlin', 'vatNumber' => 'DE136695976'],
            $this->authenticateAsAdmin(),
            'merge-patch+json',
        );

        self::assertJsonResponseSuccessful($response);

        $reloaded = $this->reloaded($address);
        self::assertSame($germany->getId(), $reloaded->getCountryId());
        self::assertNull($reloaded->getVatVerifiedAt());
        self::assertNull($reloaded->getVatVerifiedName());
    }

    public function testPatchingAnotherFieldKeepsTheVerification(): void
    {
        $address = $this->verifiedBelgianAddress();

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/addresses/'.$address->getId(),
            ['city' => 'Liège'],
            $this->authenticateAsAdmin(),
            'merge-patch+json',
        );

        self::assertJsonResponseSuccessful($response);

        $reloaded = $this->reloaded($address);
        self::assertSame('Liège', $reloaded->getCity());
        self::assertNotNull($reloaded->getVatVerifiedAt());
        self::assertSame('Acme SPRL', $reloaded->getVatVerifiedName());
    }

    private function verifiedBelgianAddress(): Address
    {
        $belgium = CountryQuery::create()->findOneByIsoalpha2('BE');
        self::assertNotNull($belgium);

        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $address = $factory->address($customer, $belgium, null, ['zipcode' => '1000', 'city' => 'Bruxelles']);

        $this->getPropelConnection()
            ->prepare('UPDATE `address` SET `company` = ?, `vat_number` = ?, `vat_verified_at` = ?, `vat_verified_name` = ? WHERE `id` = ?')
            ->execute(['Acme', 'BE0123456789', (new \DateTime('-1 day'))->format('Y-m-d H:i:s'), 'Acme SPRL', $address->getId()]);
        $address->reload();

        return $address;
    }

    private function reloaded(Address $address): Address
    {
        AddressTableMap::clearInstancePool();
        $reloaded = AddressQuery::create()->findPk($address->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}
