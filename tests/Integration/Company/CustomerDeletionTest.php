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

namespace Thelia\Tests\Integration\Company;

use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\AddressQuery;
use Thelia\Model\CompanyQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Event\CustomerEvent;

/**
 * Deleting the account of a buyer is the same departure as erasing it: the shared addresses
 * go to the administrator who stays, a company never loses the address it invoices while a
 * buyer remains, and a company with nobody left to run it is suspended.
 */
final class CustomerDeletionTest extends AddressTestCase
{
    public function testTheBuyerWhoCreatedTheBillingAddressLeavesItToTheAdministrator(): void
    {
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyerOf($company);
        $billing = $this->addressOf($buyer, $company, 'Billing');
        $personal = $this->addressOf($buyer, null, 'Home');
        CompanyQuery::create()->filterById($company->getId())->update(['BillingAddressId' => $billing->getId()]);

        $this->deleteAccount($buyer);

        self::assertNull(CustomerQuery::create()->findPk($buyer->getId()));
        self::assertSame($administrator->getId(), AddressQuery::create()->findPk($billing->getId())?->getCustomerId());
        self::assertSame($billing->getId(), CompanyQuery::create()->findPk($company->getId())?->getBillingAddressId());
        self::assertNull(AddressQuery::create()->findPk($personal->getId()), 'The personal address goes with the account.');
        self::assertSame(1, CompanyQuery::create()->findPk($company->getId())?->getActive());
    }

    public function testTheLastAdministratorDeletedLeavesTheCompanyToTheOldestBuyer(): void
    {
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $first = $this->buyerOf($company);
        $second = $this->buyerOf($company);
        $billing = $this->addressOf($administrator, $company, 'Billing');
        CompanyQuery::create()->filterById($company->getId())->update(['BillingAddressId' => $billing->getId()]);

        $this->deleteAccount($administrator);

        self::assertNull(CustomerQuery::create()->findPk($administrator->getId()));
        self::assertSame(CompanyRoleCode::ADMINISTRATOR, $this->buyerRow($first)?->getCompanyRole()?->getCode());
        self::assertSame(CompanyRoleCode::BUYER, $this->buyerRow($second)?->getCompanyRole()?->getCode());
        self::assertSame($first->getId(), AddressQuery::create()->findPk($billing->getId())?->getCustomerId());
        self::assertSame($billing->getId(), CompanyQuery::create()->findPk($company->getId())?->getBillingAddressId());
        self::assertSame(1, CompanyQuery::create()->findPk($company->getId())?->getActive());
    }

    public function testTheLastBuyerDeletedSuspendsTheCompanyAndErasesItsSharedAddresses(): void
    {
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $shared = $this->addressOf($administrator, $company, 'Shared');
        $billing = $this->addressOf($administrator, $company, 'Billing');
        CompanyQuery::create()->filterById($company->getId())->update(['BillingAddressId' => $billing->getId()]);

        $this->deleteAccount($administrator);

        $stored = CompanyQuery::create()->findPk($company->getId());
        self::assertNotNull($stored, 'The company stays, with its history.');
        self::assertSame(0, $stored->getActive());
        self::assertNull($stored->getBillingAddressId());

        foreach ([$shared, $billing] as $address) {
            self::assertNull(AddressQuery::create()->findPk($address->getId()));
        }
    }

    public function testAnAccountOfNoCompanyIsDeletedAsBefore(): void
    {
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $shared = $this->addressOf($administrator, $company, 'Shared');
        $customer = $this->customer();
        $home = $this->addressOf($customer, null, 'Home');

        $this->deleteAccount($customer);

        self::assertNull(CustomerQuery::create()->findPk($customer->getId()));
        self::assertNull(AddressQuery::create()->findPk($home->getId()));
        self::assertSame($administrator->getId(), AddressQuery::create()->findPk($shared->getId())?->getCustomerId());
        self::assertSame(CompanyRoleCode::ADMINISTRATOR, $this->buyerRow($administrator)?->getCompanyRole()?->getCode());
        self::assertSame(1, CompanyQuery::create()->findPk($company->getId())?->getActive());
    }

    private function deleteAccount(Customer $customer): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');
        \assert($dispatcher instanceof \Symfony\Contracts\EventDispatcher\EventDispatcherInterface);
        $dispatcher->dispatch(new CustomerEvent($customer), TheliaEvents::CUSTOMER_DELETEACCOUNT);
    }
}
