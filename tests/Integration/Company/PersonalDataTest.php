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

use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Domain\Customer\Service\CustomerAnonymizer;
use Thelia\Domain\Customer\Service\CustomerPersonalDataExporter;
use Thelia\Domain\Customer\Service\CustomerPurger;
use Thelia\Model\AddressQuery;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;

/**
 * What a company keeps and what it loses when a buyer is erased, exported or left idle: the shared
 * addresses go to the administrator who stays, the invoices of the company keep its identity, the
 * link with the company goes; the last administrator is purged like any other idle account, and the company passes to the buyer who joined first or is suspended.
 */
final class PersonalDataTest extends AddressTestCase
{
    public function testAnErasedBuyerLeavesTheSharedAddressesToTheAdministratorAndTakesTheLinkAndThePersonalOnes(): void
    {
        $world = $this->twoCompanies();
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $warehouse->makeItDefault();
        $home = $this->addressOf($world['creator'], null, 'Home');

        $this->anonymize($world['creator']);

        $kept = AddressQuery::create()->findPk($warehouse->getId());
        self::assertNotNull($kept, 'The address the company shares survives its creator.');
        self::assertSame($world['administratorOfFirst']->getId(), $kept->getCustomerId());
        self::assertSame($world['first']->getId(), $kept->getCompanyId());
        self::assertSame(0, (int) $kept->getIsDefault(), 'The default address of the creator is not the one of the administrator.');
        self::assertSame('Warehouse', $kept->getLabel());
        self::assertNull(AddressQuery::create()->findPk($home->getId()), 'The personal address goes with the buyer.');
        self::assertNull($this->buyerRow($world['creator']), 'The link with the company is cut.');
        self::assertNotNull(CustomerQuery::create()->findPk($world['creator']->getId())?->getAnonymizedAt());
        self::assertSame($warehouse->getId(), $this->access->find($world['colleague'], $warehouse->getId())?->getId());
    }

    public function testAnErasedBuyerWithNobodyToTakeTheirAddressesStillIsErased(): void
    {
        $company = $this->company('Orphan');
        $creator = $this->buyerOf($company);
        $shared = $this->addressOf($creator, $company, 'Shared');
        $billing = $this->addressOf($creator, $company, 'Billing');
        CompanyQuery::create()->filterById($company->getId())->update(['BillingAddressId' => $billing->getId()]);
        $home = $this->addressOf($creator, null, 'Home');

        $this->anonymize($creator);

        self::assertNotNull(CustomerQuery::create()->findPk($creator->getId())?->getAnonymizedAt());
        self::assertNull($this->buyerRow($creator));

        foreach ([$shared, $billing, $home] as $address) {
            self::assertNull(AddressQuery::create()->findPk($address->getId()), $address->getLabel().' is erased with the address book.');
        }

        self::assertNull(CompanyQuery::create()->findPk($company->getId())?->getBillingAddressId());
        self::assertNotNull(CompanyQuery::create()->findPk($company->getId()), 'The company stays.');
    }

    public function testErasingTheLastAdministratorMakesTheOldestActiveBuyerAdministratorAndGivesThemTheSharedAddresses(): void
    {
        $company = $this->company('Alone');
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $disabled = $this->buyerOf($company);
        $disabled->setEnable(0)->save($this->getPropelConnection());
        $first = $this->buyerOf($company);
        $second = $this->buyerOf($company);
        $shared = $this->addressOf($administrator, $company, 'Shared');
        $billing = $this->addressOf($administrator, $company, 'Billing');
        CompanyQuery::create()->filterById($company->getId())->update(['BillingAddressId' => $billing->getId()]);
        $home = $this->addressOf($administrator, null, 'Home');

        $this->anonymize($administrator);

        self::assertNotNull(CustomerQuery::create()->findPk($administrator->getId())?->getAnonymizedAt());
        self::assertNull($this->buyerRow($administrator));
        self::assertSame(CompanyRoleCode::ADMINISTRATOR, $this->buyerRow($first)?->getCompanyRole()?->getCode(), 'The oldest buyer who is still active takes over.');
        self::assertSame(CompanyRoleCode::BUYER, $this->buyerRow($disabled)?->getCompanyRole()?->getCode(), 'A disabled account is not asked to run the company.');
        self::assertSame(CompanyRoleCode::BUYER, $this->buyerRow($second)?->getCompanyRole()?->getCode());

        foreach ([$shared, $billing] as $address) {
            self::assertSame($first->getId(), AddressQuery::create()->findPk($address->getId())?->getCustomerId(), $address->getLabel().' goes to the new administrator.');
            self::assertSame($company->getId(), AddressQuery::create()->findPk($address->getId())?->getCompanyId());
        }

        self::assertSame($billing->getId(), CompanyQuery::create()->findPk($company->getId())?->getBillingAddressId(), 'The company is still invoiced at its address.');
        self::assertSame(1, CompanyQuery::create()->findPk($company->getId())?->getActive(), 'A company with someone to run it goes on.');
        self::assertNull(AddressQuery::create()->findPk($home->getId()));
        self::assertCount(1, $this->events['action.company.customer.changeRole'] ?? []);
        self::assertSame(CompanyRoleCode::ADMINISTRATOR, $this->events['action.company.customer.changeRole'][0]->getRoleCode());
        self::assertSame([], $this->events['action.company.suspend'] ?? []);
    }

    public function testTheBuyerWhoJoinedFirstIsTheOneWhoTakesOverWhateverTheirNumber(): void
    {
        $company = $this->company('Seniority');
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $later = $this->buyerOf($company);
        $earlier = $this->buyerOf($company);
        $this->getPropelConnection()->exec(\sprintf(
            "UPDATE `company_customer` SET `created_at` = '2020-01-01 10:00:00' WHERE `customer_id` = %d",
            $earlier->getId(),
        ));

        $this->anonymize($administrator);

        self::assertSame(CompanyRoleCode::ADMINISTRATOR, $this->buyerRow($earlier)?->getCompanyRole()?->getCode());
        self::assertSame(CompanyRoleCode::BUYER, $this->buyerRow($later)?->getCompanyRole()?->getCode());
    }

    public function testErasingTheLastAdministratorOfACompanyWithNobodyElseSuspendsItAndErasesItsSharedAddresses(): void
    {
        $company = $this->company('Alone');
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $shared = $this->addressOf($administrator, $company, 'Shared');
        $billing = $this->addressOf($administrator, $company, 'Billing');
        CompanyQuery::create()->filterById($company->getId())->update(['BillingAddressId' => $billing->getId()]);

        $this->anonymize($administrator);

        self::assertNotNull(CustomerQuery::create()->findPk($administrator->getId())?->getAnonymizedAt());
        self::assertNull($this->buyerRow($administrator));
        $stored = CompanyQuery::create()->findPk($company->getId());
        self::assertNotNull($stored, 'The company and its history stay.');
        self::assertSame(0, $stored->getActive(), 'Nobody can run it: it is suspended.');
        self::assertNull($stored->getBillingAddressId());

        foreach ([$shared, $billing] as $address) {
            self::assertNull(AddressQuery::create()->findPk($address->getId()), $address->getLabel().' is erased.');
        }

        self::assertCount(1, $this->events['action.company.suspend'] ?? []);
    }

    public function testErasingTheLastAdministratorWhenOnlyDisabledAccountsRemainSuspendsTheCompany(): void
    {
        $company = $this->company('Dormant');
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $disabled = $this->buyerOf($company);
        $disabled->setEnable(0)->save($this->getPropelConnection());

        $this->anonymize($administrator);

        self::assertSame(0, CompanyQuery::create()->findPk($company->getId())?->getActive());
        self::assertSame(CompanyRoleCode::BUYER, $this->buyerRow($disabled)?->getCompanyRole()?->getCode(), 'The disabled account keeps its link and its role.');
    }

    public function testErasingAnAdministratorWhoIsNotTheLastPromotesNobody(): void
    {
        $company = $this->company('Two admins');
        $leaving = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $staying = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyerOf($company);

        $this->anonymize($leaving);

        self::assertSame(CompanyRoleCode::ADMINISTRATOR, $this->buyerRow($staying)?->getCompanyRole()?->getCode());
        self::assertSame(CompanyRoleCode::BUYER, $this->buyerRow($buyer)?->getCompanyRole()?->getCode());
        self::assertSame(1, CompanyQuery::create()->findPk($company->getId())?->getActive());
        self::assertSame([], $this->events['action.company.customer.changeRole'] ?? []);
    }

    public function testAnErasedAdministratorHandsTheAddressesToTheNextOneInTheOrderTheyJoined(): void
    {
        $company = $this->company('Two admins');
        $first = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $second = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $shared = $this->addressOf($first, $company, 'Shared');

        $this->anonymize($first);

        self::assertSame($second->getId(), AddressQuery::create()->findPk($shared->getId())?->getCustomerId());
    }

    public function testTheInvoiceAddressOfAnOrderPlacedForACompanyIsKeptAndTheDeliveryOneIsNeutralized(): void
    {
        $world = $this->twoCompanies();
        $forCompany = $this->orderOf($world['creator'], $world['first'], $world['creator']);
        $personal = $this->orderOf($world['creator']);

        foreach ([$forCompany, $personal] as $order) {
            OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId())?->setCompany('Acme')->setSiret('12345678901234')->setVatNumber('FR123')->save();
            OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId())?->setCompany('Acme')->save();
        }

        $this->anonymize($world['creator']);

        $invoiceOfCompanyOrder = $this->orderAddress($forCompany, 'invoice');
        self::assertSame('John', $invoiceOfCompanyOrder->getFirstname());
        self::assertSame('Acme', $invoiceOfCompanyOrder->getCompany());
        self::assertSame('12345678901234', $invoiceOfCompanyOrder->getSiret());
        self::assertSame('FR123', $invoiceOfCompanyOrder->getVatNumber());
        self::assertSame(CustomerAnonymizer::ANONYMIZED_VALUE, $this->orderAddress($forCompany, 'delivery')->getFirstname());
        self::assertNull($this->orderAddress($forCompany, 'delivery')->getCompany());

        foreach (['invoice', 'delivery'] as $which) {
            self::assertSame(CustomerAnonymizer::ANONYMIZED_VALUE, $this->orderAddress($personal, $which)->getFirstname(), 'An order without company is neutralized as before: '.$which);
            self::assertNull($this->orderAddress($personal, $which)->getSiret());
        }

        self::assertSame($world['first']->getId(), $this->reload($forCompany)->getCompanyId(), 'The order keeps its company.');
    }

    public function testTheExportCarriesTheMembershipOfTheBuyerAndNothingForAnOrdinaryAccount(): void
    {
        $company = $this->company('Acme');
        $company->setSiret('12345678901234')->setVatNumber('FR123')->setInternalNote('Late payer')->save();
        $buyer = $this->buyerOf($company, CompanyRoleCode::BUYER, null, '500.000000', '2000.000000', 'month');
        $exporter = $this->getService(CustomerPersonalDataExporter::class);

        $section = $exporter->export($buyer)['company'];

        self::assertSame([
            'name' => 'Acme',
            'siret' => '12345678901234',
            'vat_number' => 'FR123',
            'active' => true,
        ], $section['company']);
        self::assertSame(CompanyRoleCode::BUYER, $section['role']);
        self::assertSame(['order.place', 'order.view_own', 'quote.request'], $section['permissions']);
        self::assertSame('500.000000', $section['order_limit']);
        self::assertSame('2000.000000', $section['period_limit']);
        self::assertSame('month', $section['period']);
        self::assertNotNull($section['joined_at']);
        self::assertStringNotContainsString('Late payer', json_encode($exporter->export($buyer), \JSON_THROW_ON_ERROR), 'The note of the administrators is not the customer\'s.');

        self::assertSame([], $exporter->export($this->customer())['company']);
    }

    public function testPurgingTheLastAdministratorMakesTheOldestActiveBuyerAdministratorAndGivesThemTheSharedAddresses(): void
    {
        $company = $this->company('Idle');
        $administrator = $this->idle($this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR));
        $first = $this->buyerOf($company);
        $second = $this->buyerOf($company);
        $shared = $this->addressOf($administrator, $company, 'Shared');
        $billing = $this->addressOf($administrator, $company, 'Billing');
        CompanyQuery::create()->filterById($company->getId())->update(['BillingAddressId' => $billing->getId()]);
        $purger = $this->getService(CustomerPurger::class);

        self::assertSame(1, $purger->countAccountsWithoutOrder(365), 'The administrator is counted.');
        self::assertSame(1, $purger->anonymizeAccountsWithoutOrder(365));

        self::assertNotNull($this->reloadCustomer($administrator)->getAnonymizedAt());
        self::assertNull($this->buyerRow($administrator));
        self::assertSame(CompanyRoleCode::ADMINISTRATOR, $this->buyerRow($first)?->getCompanyRole()?->getCode());
        self::assertSame(CompanyRoleCode::BUYER, $this->buyerRow($second)?->getCompanyRole()?->getCode());

        foreach ([$shared, $billing] as $address) {
            self::assertSame($first->getId(), AddressQuery::create()->findPk($address->getId())?->getCustomerId());
        }

        self::assertSame($billing->getId(), CompanyQuery::create()->findPk($company->getId())?->getBillingAddressId());
        self::assertSame(1, CompanyQuery::create()->findPk($company->getId())?->getActive());
    }

    public function testPurgingTheLastAdministratorWithNobodyElseSuspendsTheCompanyAndErasesItsSharedAddresses(): void
    {
        $company = $this->company('Idle');
        $administrator = $this->idle($this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR));
        $shared = $this->addressOf($administrator, $company, 'Shared');
        $billing = $this->addressOf($administrator, $company, 'Billing');
        CompanyQuery::create()->filterById($company->getId())->update(['BillingAddressId' => $billing->getId()]);
        $disabled = $this->buyerOf($company);
        $disabled->setEnable(0)->save($this->getPropelConnection());
        $purger = $this->getService(CustomerPurger::class);

        self::assertSame(1, $purger->anonymizeAccountsWithoutOrder(365));

        self::assertNotNull($this->reloadCustomer($administrator)->getAnonymizedAt());
        $stored = CompanyQuery::create()->findPk($company->getId());
        self::assertSame(0, $stored?->getActive());
        self::assertNull($stored->getBillingAddressId());
        self::assertSame(CompanyRoleCode::BUYER, $this->buyerRow($disabled)?->getCompanyRole()?->getCode());

        foreach ([$shared, $billing] as $address) {
            self::assertNull(AddressQuery::create()->findPk($address->getId()));
        }
    }

    public function testPurgingAnIdleBuyerChangesNothingForTheCompany(): void
    {
        $company = $this->company('Idle');
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->idle($this->buyerOf($company));
        $shared = $this->addressOf($administrator, $company, 'Shared');
        $purger = $this->getService(CustomerPurger::class);

        self::assertSame(1, $purger->anonymizeAccountsWithoutOrder(365));

        self::assertNotNull($this->reloadCustomer($buyer)->getAnonymizedAt());
        self::assertSame(CompanyRoleCode::ADMINISTRATOR, $this->buyerRow($administrator)?->getCompanyRole()?->getCode());
        self::assertSame(1, CompanyQuery::create()->findPk($company->getId())?->getActive());
        self::assertSame($administrator->getId(), AddressQuery::create()->findPk($shared->getId())?->getCustomerId());
    }

    public function testTwoIdleAdministratorsArePurgedAndTheCompanyEndsSuspended(): void
    {
        $company = $this->company('Idle');
        $first = $this->idle($this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR));
        $second = $this->idle($this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR));
        $purger = $this->getService(CustomerPurger::class);

        self::assertSame(2, $purger->anonymizeAccountsWithoutOrder(365));

        self::assertNotNull($this->reloadCustomer($first)->getAnonymizedAt());
        self::assertNotNull($this->reloadCustomer($second)->getAnonymizedAt());
        self::assertSame(0, CompanyCustomerQuery::create()->filterByCompanyId($company->getId())->count());
        self::assertSame(0, CompanyQuery::create()->findPk($company->getId())?->getActive());
    }

    public function testAnIdleAccountWithoutCompanyIsPurgedAsBefore(): void
    {
        $account = $this->idle($this->customer());
        $company = $this->company('Idle');
        $this->idle($this->buyerOf($company, CompanyRoleCode::APPROVER));
        $purger = $this->getService(CustomerPurger::class);

        self::assertSame(2, $purger->anonymizeAccountsWithoutOrder(365));
        self::assertNotNull($this->reloadCustomer($account)->getAnonymizedAt());
    }

    private function anonymize(Customer $customer): void
    {
        $this->getService(CustomerAnonymizer::class)->anonymize($customer);
    }

    private function idle(Customer $customer): Customer
    {
        $customer->setCreatedAt(new \DateTime('-400 days'))->save($this->getPropelConnection());

        return $customer;
    }

    private function reloadCustomer(Customer $customer): Customer
    {
        return CustomerQuery::create()->findPk($customer->getId()) ?? throw new \LogicException('The customer is gone.');
    }

    private function reload(Order $order): Order
    {
        return \Thelia\Model\OrderQuery::create()->findPk($order->getId()) ?? throw new \LogicException('The order is gone.');
    }

    private function orderAddress(Order $order, string $which): \Thelia\Model\OrderAddress
    {
        $order = $this->reload($order);
        $id = 'invoice' === $which ? $order->getInvoiceOrderAddressId() : $order->getDeliveryOrderAddressId();

        return OrderAddressQuery::create()->findPk($id) ?? throw new \LogicException('The order address is gone.');
    }
}
