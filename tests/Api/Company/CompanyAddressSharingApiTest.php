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

namespace Thelia\Tests\Api\Company;

use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Company;
use Thelia\Model\Customer;

/**
 * The addresses a company shares over the account API: the colleagues read and choose them, the
 * creator and the administrators of the company change them, and another company reaches none.
 */
final class CompanyAddressSharingApiTest extends CompanyApiTestCase
{
    private Company $first;

    private Company $second;

    private Customer $creator;

    private Customer $colleague;

    private Customer $administrator;

    private Customer $administratorOfSecond;

    private Address $warehouse;

    private Address $home;

    protected function setUp(): void
    {
        parent::setUp();

        $this->first = $this->company('Acme');
        $this->second = $this->company('Globex');
        $this->creator = $this->buyerOf($this->first);
        $this->colleague = $this->buyerOf($this->first);
        $this->administrator = $this->buyerOf($this->first, CompanyRoleCode::ADMINISTRATOR);
        $this->administratorOfSecond = $this->buyerOf($this->second, CompanyRoleCode::ADMINISTRATOR);
        $this->warehouse = $this->addressOf($this->creator, $this->first, 'Warehouse');
        $this->home = $this->addressOf($this->creator, null, 'Home');
    }

    public function testAColleagueListsTheSharedAddressAndNotThePersonalOneOfTheCreator(): void
    {
        $ids = $this->memberIds($this->jsonRequest('GET', '/api/front/account/addresses', token: $this->token($this->colleague)));

        self::assertSame([(int) $this->warehouse->getId()], $ids);
    }

    public function testTheCreatorListsBoth(): void
    {
        $ids = $this->memberIds($this->jsonRequest('GET', '/api/front/account/addresses', token: $this->token($this->creator)));

        self::assertEqualsCanonicalizing([(int) $this->warehouse->getId(), (int) $this->home->getId()], $ids);
    }

    public function testAColleagueReadsTheSharedAddressAndFindsNoSuchPersonalOne(): void
    {
        $token = $this->token($this->colleague);

        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/front/account/addresses/'.$this->warehouse->getId(), token: $token));
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/addresses/'.$this->home->getId(), token: $token)->getStatusCode());
    }

    public function testTheOtherCompanyReachesNeitherAddress(): void
    {
        $token = $this->token($this->administratorOfSecond);

        self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/addresses/'.$this->warehouse->getId(), token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/addresses/'.$this->home->getId(), token: $token)->getStatusCode());
        self::assertSame([], $this->memberIds($this->jsonRequest('GET', '/api/front/account/addresses', token: $token)));
    }

    public function testAColleagueMayNeitherChangeNorDeleteTheSharedAddress(): void
    {
        $token = $this->token($this->colleague);

        self::assertSame(403, $this->jsonRequest('PUT', '/api/front/account/addresses/'.$this->warehouse->getId(), $this->payload(), $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('DELETE', '/api/front/account/addresses/'.$this->warehouse->getId(), token: $token)->getStatusCode());
        self::assertNotNull(AddressQuery::create()->findPk($this->warehouse->getId()));
    }

    public function testTheAdministratorOfTheCompanyDeletesTheSharedAddress(): void
    {
        $response = $this->jsonRequest('DELETE', '/api/front/account/addresses/'.$this->warehouse->getId(), token: $this->token($this->administrator));

        self::assertSame(204, $response->getStatusCode());
        self::assertNull(AddressQuery::create()->findPk($this->warehouse->getId()));
    }

    public function testTheAdministratorOfTheCompanyChangesTheSharedAddressWithoutTakingItOver(): void
    {
        $response = $this->jsonRequest('PUT', '/api/front/account/addresses/'.$this->warehouse->getId(), $this->payload(), $this->token($this->administrator));

        self::assertJsonResponseSuccessful($response);

        $address = AddressQuery::create()->findPk($this->warehouse->getId());
        self::assertNotNull($address);
        self::assertSame('Dock 9', $address->getAddress1());
        self::assertSame($this->creator->getId(), $address->getCustomerId(), 'The creator stays the owner of the address.');
        self::assertSame($this->first->getId(), $address->getCompanyId(), 'The address stays shared.');
    }

    public function testTheCreatorChangesTheSharedAddressTheyCreated(): void
    {
        $response = $this->jsonRequest('PUT', '/api/front/account/addresses/'.$this->warehouse->getId(), $this->payload(), $this->token($this->creator));

        self::assertJsonResponseSuccessful($response);
        self::assertSame($this->first->getId(), AddressQuery::create()->findPk($this->warehouse->getId())?->getCompanyId());
    }

    public function testTheAdministratorOfAnotherCompanyCannotChangeOrDeleteThem(): void
    {
        $token = $this->token($this->administratorOfSecond);

        self::assertSame(404, $this->jsonRequest('PUT', '/api/front/account/addresses/'.$this->warehouse->getId(), $this->payload(), $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('DELETE', '/api/front/account/addresses/'.$this->warehouse->getId(), token: $token)->getStatusCode());
        self::assertNotNull(AddressQuery::create()->findPk($this->warehouse->getId()));
    }

    public function testAnAccountWithoutCompanyKeepsItsOwnAddressesOnly(): void
    {
        $loner = $this->customer();
        $own = $this->addressOf($loner);
        $token = $this->token($loner);

        self::assertSame([(int) $own->getId()], $this->memberIds($this->jsonRequest('GET', '/api/front/account/addresses', token: $token)));
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/addresses/'.$this->warehouse->getId(), token: $token)->getStatusCode());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $country = \Thelia\Model\CountryQuery::create()->findOneByIsoalpha3('FRA');
        self::assertNotNull($country);

        return [
            'label' => 'Warehouse',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'address1' => 'Dock 9',
            'address2' => '',
            'address3' => '',
            'city' => 'Paris',
            'zipcode' => '75001',
            'country' => '/api/front/countries/'.$country->getId(),
            'customerTitle' => '/api/admin/customer_titles/'.$this->creator->getTitleId(),
        ];
    }
}
