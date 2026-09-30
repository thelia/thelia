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

namespace Thelia\Tests\Integration\Model;

use Thelia\Model\Address;
use Thelia\Model\Country;
use Thelia\Model\OrderAddress;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class VatVerificationSubjectTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        Country::resetDefaultCountryCache();

        parent::tearDown();
    }

    public function testAnAddressSavedWithAnotherNumberIsNoLongerVerified(): void
    {
        $address = $this->verifiedAddress();

        $address->setVatNumber('BE0987654321')->save($this->getPropelConnection());

        $address->reload();
        self::assertNull($address->getVatVerifiedAt());
        self::assertNull($address->getVatVerifiedName());
    }

    public function testAnAddressSavedInAnotherCountryIsNoLongerVerified(): void
    {
        $address = $this->verifiedAddress();

        $address->setCountryId($this->countryOf('DE')->getId())->save($this->getPropelConnection());

        $address->reload();
        self::assertNull($address->getVatVerifiedAt());
        self::assertNull($address->getVatVerifiedName());
    }

    public function testAnAddressSavedWithAnotherCityStaysVerified(): void
    {
        $address = $this->verifiedAddress();

        $address->setCity('Liège')->save($this->getPropelConnection());

        $address->reload();
        self::assertNotNull($address->getVatVerifiedAt());
        self::assertSame('ACME SA', $address->getVatVerifiedName());
    }

    public function testANumberSavedTogetherWithItsVerificationStaysVerified(): void
    {
        $address = $this->verifiedAddress();

        $address
            ->setVatNumber('BE0987654321')
            ->setVatVerifiedAt(new \DateTime())
            ->setVatVerifiedName('OTHER SA')
            ->save($this->getPropelConnection());

        $address->reload();
        self::assertNotNull($address->getVatVerifiedAt());
        self::assertSame('OTHER SA', $address->getVatVerifiedName());
    }

    public function testAnOrderAddressSavedWithAnotherNumberIsNoLongerVerified(): void
    {
        $orderAddress = $this->verifiedOrderAddress();

        $orderAddress->setVatNumber('BE0987654321')->save($this->getPropelConnection());

        $orderAddress->reload();
        self::assertNull($orderAddress->getVatVerifiedAt());
        self::assertNull($orderAddress->getVatVerifiedName());
    }

    public function testAnOrderAddressSavedInAnotherCountryKeepsTheProofOfItsVerification(): void
    {
        $orderAddress = $this->verifiedOrderAddress();

        $orderAddress->setCountryId($this->countryOf('DE')->getId())->save($this->getPropelConnection());

        $orderAddress->reload();
        self::assertNotNull($orderAddress->getVatVerifiedAt());
        self::assertSame('ACME SA', $orderAddress->getVatVerifiedName());
    }

    private function verifiedAddress(): Address
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $address = $this->factory->address($customer, $this->countryOf('BE'));
        $address
            ->setCompany('Acme')
            ->setVatNumber('BE0123456789')
            ->setVatVerifiedAt(new \DateTime('-1 day'))
            ->setVatVerifiedName('ACME SA')
            ->save($this->getPropelConnection());

        return $address;
    }

    private function verifiedOrderAddress(): OrderAddress
    {
        $orderAddress = $this->factory->orderAddress($this->countryOf('BE'));
        $orderAddress
            ->setCompany('Acme')
            ->setVatNumber('BE0123456789')
            ->setVatVerifiedAt(new \DateTime('-1 day'))
            ->setVatVerifiedName('ACME SA')
            ->save($this->getPropelConnection());

        return $orderAddress;
    }

    private function countryOf(string $isoAlpha2): Country
    {
        return $this->factory->country([
            'isocode' => $isoAlpha2,
            'isoalpha2' => $isoAlpha2,
            'isoalpha3' => $isoAlpha2.'X',
        ]);
    }
}
