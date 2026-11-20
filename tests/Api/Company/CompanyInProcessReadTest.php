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

use Thelia\Api\Service\API\ResourceMemoizer;
use Thelia\Api\Service\DataAccess\DataAccessService;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\Company;
use Thelia\Model\Customer;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Tests\Integration\Company\AddressTestCase;

/**
 * A front page has no firewall token: its customer lives in the Thelia session. The orders and the
 * addresses it reads in process (`resources()`) are bounded the way the API bounds them, by what the
 * session customer reaches through their company, and to nobody when there is no customer, or only a
 * guest. It is the only barrier of that read, so each case fails if the scope goes.
 */
final class CompanyInProcessReadTest extends AddressTestCase
{
    use LogsInAsCustomer {
        loginAsCustomerInSession as private logsInAsCustomerInSession;
    }

    private DataAccessService $dataAccess;

    private Company $company;

    private Customer $creator;

    private Customer $colleague;

    private Customer $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataAccess = $this->getService(DataAccessService::class);
        $this->company = $this->company();
        $this->creator = $this->buyerOf($this->company);
        $this->colleague = $this->buyerOf($this->company);
        $this->approver = $this->buyerOf($this->company, CompanyRoleCode::APPROVER);
    }

    public function testTheOrdersOfTheCompanyAreListedForTheCustomerWhoReadsThemOnly(): void
    {
        $order = $this->orderOf($this->creator, $this->company, $this->creator);
        $own = $this->orderOf($this->colleague, $this->company, $this->colleague);

        $this->loginAsCustomerInSession($this->approver);
        self::assertEqualsCanonicalizing([$order->getId(), $own->getId()], $this->idsOf($this->dataAccess->resources('/api/front/account/orders')));
        self::assertEqualsCanonicalizing([$order->getId(), $own->getId()], $this->idsOf($this->dataAccess->resources('/api/front/account/orders', ['scope' => 'company'])));

        $this->loginAsCustomerInSession($this->colleague);
        self::assertSame([$own->getId()], $this->idsOf($this->dataAccess->resources('/api/front/account/orders')));
        self::assertSame([], $this->idsOf($this->dataAccess->resources('/api/front/account/orders', ['scope' => 'company'])));
    }

    public function testTheOrderOfAColleagueIsAbsentFromAnInProcessItemReadUnlessTheRoleReadsIt(): void
    {
        $order = $this->orderOf($this->creator, $this->company, $this->creator);

        $this->loginAsCustomerInSession($this->colleague);
        self::assertNull($this->dataAccess->resources('/api/front/account/orders/'.$order->getId()));

        $this->loginAsCustomerInSession($this->approver);
        $read = $this->dataAccess->resources('/api/front/account/orders/'.$order->getId());
        self::assertIsArray($read);
        self::assertSame($order->getId(), $read['id']);
    }

    public function testTheSharedAddressesAreListedForTheColleaguesAndNotThePersonalOnes(): void
    {
        $shared = $this->addressOf($this->creator, $this->company, 'Warehouse');
        $personal = $this->addressOf($this->creator, null, 'Home');

        $this->loginAsCustomerInSession($this->colleague);
        self::assertSame([$shared->getId()], $this->idsOf($this->dataAccess->resources('/api/front/account/addresses')));
        self::assertNull($this->dataAccess->resources('/api/front/account/addresses/'.$personal->getId()));
        self::assertNotNull($this->dataAccess->resources('/api/front/account/addresses/'.$shared->getId()));
    }

    public function testNothingIsReadWithoutASessionCustomerOrWithAGuest(): void
    {
        $order = $this->orderOf($this->creator, $this->company, $this->creator);
        $address = $this->addressOf($this->creator, $this->company);

        self::assertSame([], $this->idsOf($this->dataAccess->resources('/api/front/account/orders')));
        self::assertNull($this->dataAccess->resources('/api/front/account/orders/'.$order->getId()));
        self::assertSame([], $this->idsOf($this->dataAccess->resources('/api/front/account/addresses')));
        self::assertNull($this->dataAccess->resources('/api/front/account/addresses/'.$address->getId()));

        $guest = $this->fixtures->guestCustomer($this->fixtures->customerTitle());
        $this->orderOf($guest);
        $this->addressOf($guest);
        $this->loginAsCustomerInSession($guest);
        self::assertSame([], $this->idsOf($this->dataAccess->resources('/api/front/account/orders')));
        self::assertSame([], $this->idsOf($this->dataAccess->resources('/api/front/account/addresses')));
    }

    private function loginAsCustomerInSession(?Customer $customer = null): Customer
    {
        // What a page read is remembered for the rest of the request, whoever asked for it.
        $this->getService(ResourceMemoizer::class)->clear();

        return $this->logsInAsCustomerInSession($customer);
    }

    /**
     * @return list<int>
     */
    private function idsOf(mixed $collection): array
    {
        if (null === $collection) {
            return [];
        }

        self::assertIsArray($collection);

        return array_values(array_map(static fn (array $row): int => (int) $row['id'], $collection));
    }
}
