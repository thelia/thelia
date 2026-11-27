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

use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Domain\Company\DTO\CompanyData;
use Thelia\Domain\Company\Event\CompanyCustomerDetachedEvent;
use Thelia\Domain\Company\Event\CompanyCustomerEvent;
use Thelia\Domain\Company\Event\CompanyCustomerRoleChangedEvent;
use Thelia\Domain\Company\Event\CompanyEvent;
use Thelia\Domain\Company\Exception\CompanyHasOrdersException;
use Thelia\Domain\Company\Exception\CustomerAlreadyInCompanyException;
use Thelia\Domain\Company\Exception\CustomerNotInCompanyException;
use Thelia\Domain\Company\Exception\GuestCannotJoinCompanyException;
use Thelia\Domain\Company\Exception\InvalidCompanyDataException;
use Thelia\Domain\Company\Exception\InvalidCompanyIdentifiersException;
use Thelia\Domain\Company\Exception\LastCompanyAdministratorException;
use Thelia\Domain\Company\Exception\UnknownCompanyException;
use Thelia\Domain\Company\Exception\UnknownCompanyRoleException;
use Thelia\Model\AddressQuery;
use Thelia\Model\Company;
use Thelia\Model\CompanyCustomer;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyQuery;
use Thelia\Model\CompanyRoleQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\OrderQuery;

/**
 * Everything the facade does to a company and its buyers is one transaction that tells the shop
 * once it is committed, and refuses what would leave a company without anyone to run it.
 */
final class CompanyFacadeTest extends CompanyDomainTestCase
{
    public function testACompanyIsCreatedActiveAndTheShopIsTold(): void
    {
        $company = $this->facade->create(new CompanyData(
            name: '  Acme Industries ',
            siret: '12345678901234',
            vatNumber: 'FR12345678901',
            parentId: 7,
            notificationEmail: 'purchasing@acme.test',
            internalNote: 'Pays at 60 days',
        ));

        $stored = CompanyQuery::create()->findPk($company->getId());

        self::assertNotNull($stored);
        self::assertSame('Acme Industries', $stored->getName());
        self::assertSame(1, $stored->getActive());
        self::assertSame('12345678901234', $stored->getSiret());
        self::assertSame('FR12345678901', $stored->getVatNumber());
        self::assertSame(7, $stored->getParentId());
        self::assertSame('purchasing@acme.test', $stored->getNotificationEmail());
        self::assertSame('Pays at 60 days', $stored->getInternalNote());

        self::assertCount(1, $this->events['action.company.create'] ?? []);
        $event = $this->events['action.company.create'][0];
        self::assertInstanceOf(CompanyEvent::class, $event);
        self::assertSame($company->getId(), $event->getCompany()->getId());
    }

    public function testACompanyWithoutANameOrWithAnInvalidDetailIsRefusedAndNothingIsTold(): void
    {
        foreach ([
            new CompanyData(name: '  '),
            new CompanyData(name: 'Acme', siret: str_repeat('1', 21)),
            new CompanyData(name: 'Acme', vatNumber: str_repeat('1', 21)),
            new CompanyData(name: 'Acme', notificationEmail: 'not-an-address'),
            new CompanyData(name: 'Acme', parentId: 0),
        ] as $data) {
            try {
                $this->facade->create($data);
                self::fail('The company was created.');
            } catch (InvalidCompanyDataException) {
            }
        }

        self::assertSame([], $this->events);
    }

    public function testANewCompanyHasNoSharedAddressYetSoItCannotHaveABillingAddress(): void
    {
        $address = $this->addressOf($this->customer());

        $this->expectException(InvalidCompanyDataException::class);

        try {
            $this->facade->create(new CompanyData(name: 'Acme', billingAddressId: (int) $address->getId()));
        } finally {
            self::assertSame(0, CompanyQuery::create()->filterByName('Acme')->count());
        }
    }

    public function testUpdatingACompanyCopiesItsNameSiretAndVatNumberOntoItsBillingAddress(): void
    {
        $company = $this->company('Acme');
        $creator = $this->customer();
        $billing = $this->addressOf($creator, $company, 'Billing');
        $other = $this->addressOf($creator, $company, 'Warehouse');

        $this->facade->update($company, new CompanyData(
            name: 'Acme Industries',
            siret: '30326504500003',
            vatNumber: 'FR40303265045',
            notificationEmail: 'purchasing@acme.test',
            billingAddressId: (int) $billing->getId(),
        ));

        $billing = AddressQuery::create()->findPk($billing->getId());
        $other = AddressQuery::create()->findPk($other->getId());

        self::assertSame('Acme Industries', $billing->getCompany());
        self::assertSame('30326504500003', $billing->getSiret());
        self::assertSame('FR40303265045', $billing->getVatNumber());
        self::assertNull($other->getCompany(), 'Only the billing address carries the identity of the company.');
        self::assertSame($billing->getId(), CompanyQuery::create()->findPk($company->getId())->getBillingAddressId());
        self::assertCount(1, $this->events['action.company.update'] ?? []);

        // Saved again, the copy follows what the company says now.
        $this->facade->update($company, new CompanyData(name: 'Acme Group', siret: null, vatNumber: 'FR40303265045', billingAddressId: (int) $billing->getId()));

        $billing = AddressQuery::create()->findPk($billing->getId());
        self::assertSame('Acme Group', $billing->getCompany());
        self::assertNull($billing->getSiret());
        self::assertSame('FR40303265045', $billing->getVatNumber());
    }

    public function testIdentifiersThatTheRulesOfAnAddressRefuseAreRefusedWhenACompanyIsSavedAndNothingChanges(): void
    {
        $company = $this->company('Acme');
        $billing = $this->addressOf($this->customer(), $company, 'Billing');
        $this->locate($billing, 'FR');

        foreach ([
            'checksum of the SIRET' => [new CompanyData(name: 'Acme', siret: '12345678900011', billingAddressId: (int) $billing->getId()), 'siret'],
            'key of the VAT number' => [new CompanyData(name: 'Acme', vatNumber: 'FR12345678901', billingAddressId: (int) $billing->getId()), 'vatNumber'],
            'SIREN of the VAT number' => [new CompanyData(name: 'Acme', siret: '30326504500003', vatNumber: 'FR83404833048', billingAddressId: (int) $billing->getId()), 'vatNumber'],
        ] as $label => [$data, $field]) {
            try {
                $this->facade->update($company, $data);
                self::fail('A wrong '.$label.' was accepted.');
            } catch (InvalidCompanyIdentifiersException $refusal) {
                self::assertSame($field, $refusal->violations()[0]->field, $label);
                self::assertSame($refusal->violations()[0]->message, $refusal->getMessage());
            }
        }

        $stored = CompanyQuery::create()->findPk($company->getId());
        self::assertNull($stored?->getSiret());
        self::assertNull($stored->getBillingAddressId());
        self::assertNull(AddressQuery::create()->findPk($billing->getId())?->getSiret(), 'The billing address was not written.');
        self::assertArrayNotHasKey('action.company.update', $this->events);
    }

    public function testTheIdentifiersOfACompanyAreJudgedForTheCountryOfItsBillingAddress(): void
    {
        $company = $this->company('Acme');
        $billing = $this->addressOf($this->customer(), $company, 'Billing');
        $this->locate($billing, 'BE');

        $this->facade->update($company, new CompanyData(name: 'Acme', siret: '12345678900011', vatNumber: 'BE0123456789', billingAddressId: (int) $billing->getId()));

        self::assertSame('12345678900011', CompanyQuery::create()->findPk($company->getId())?->getSiret());
    }

    public function testACompanyCreatedWithAnIdentifierNoCountryAcceptsIsRefused(): void
    {
        $this->expectException(InvalidCompanyIdentifiersException::class);

        try {
            $this->facade->create(new CompanyData(name: 'Acme', siret: 'AB#12'));
        } finally {
            self::assertSame(0, CompanyQuery::create()->filterByName('Acme')->count());
            self::assertArrayNotHasKey('action.company.create', $this->events);
        }
    }

    public function testACompanyStoredWithIdentifiersTheRulesRefuseIsNotRewrittenUntilItsIdentifiersOrItsBillingAddressChange(): void
    {
        $company = $this->company('Acme');
        $creator = $this->customer();
        $billing = $this->addressOf($creator, $company, 'Billing');
        $other = $this->addressOf($creator, $company, 'Warehouse');
        $this->locate($billing, 'FR');
        $this->locate($other, 'FR');
        $company->setSiret('12345678900011')->setBillingAddressId((int) $billing->getId())->save($this->getPropelConnection());

        $this->facade->update($company, new CompanyData(
            name: 'Acme',
            siret: '12345678900011',
            notificationEmail: 'purchasing@acme.test',
            billingAddressId: (int) $billing->getId(),
        ));

        $stored = CompanyQuery::create()->findPk($company->getId());
        self::assertSame('purchasing@acme.test', $stored?->getNotificationEmail());
        self::assertSame('12345678900011', $stored->getSiret());

        try {
            $this->facade->update($company, new CompanyData(name: 'Acme', siret: '12345678900011', billingAddressId: (int) $other->getId()));
            self::fail('A new billing address was given identifiers the rules refuse.');
        } catch (InvalidCompanyIdentifiersException) {
        }

        self::assertSame($billing->getId(), CompanyQuery::create()->findPk($company->getId())?->getBillingAddressId());
    }

    private function locate(\Thelia\Model\Address $address, string $isoCode): void
    {
        $country = CountryQuery::create()->filterByIsoalpha2($isoCode)->findOne() ?? throw new \LogicException($isoCode);
        $address->setCountryId($country->getId())->save($this->getPropelConnection());
    }

    public function testTheBillingAddressMustBeSharedWithThatCompany(): void
    {
        $company = $this->company('Acme');
        $otherCompany = $this->company('Globex');
        $creator = $this->customer();

        foreach ([
            'personal' => $this->addressOf($creator),
            'of another company' => $this->addressOf($creator, $otherCompany),
        ] as $label => $address) {
            try {
                $this->facade->update($company, new CompanyData(name: 'Acme', billingAddressId: (int) $address->getId()));
                self::fail('An address '.$label.' was accepted.');
            } catch (InvalidCompanyDataException) {
            }
        }

        try {
            $this->facade->update($company, new CompanyData(name: 'Acme', billingAddressId: 999999));
            self::fail('An address that does not exist was accepted.');
        } catch (InvalidCompanyDataException) {
        }

        self::assertNull(CompanyQuery::create()->findPk($company->getId())->getBillingAddressId());
        self::assertArrayNotHasKey('action.company.update', $this->events);
    }

    public function testACustomerIsAttachedWithARoleAndLimitsAndTheCachedAnswerIsDropped(): void
    {
        $company = $this->company();
        $customer = $this->customer();

        self::assertNull($this->reader->membershipOf($customer), 'Read once, and remembered as none.');

        $this->facade->attach($company, $customer, CompanyRoleCode::BUYER, '500', '2000.5', 'month');

        $membership = $this->reader->membershipOf($customer);

        self::assertNotNull($membership, 'The answer of before the attachment is not the one given now.');
        self::assertSame($company->getId(), $membership->companyId);
        self::assertSame(CompanyRoleCode::BUYER, $membership->roleCode);
        self::assertSame([CompanyPermission::ORDER_PLACE, CompanyPermission::ORDER_VIEW_OWN, CompanyPermission::QUOTE_REQUEST], $membership->permissions);
        self::assertSame('500.000000', $membership->orderLimit);
        self::assertSame('2000.500000', $membership->periodLimit);
        self::assertSame('month', $membership->period);

        self::assertCount(1, $this->events['action.company.customer.attach'] ?? []);
        $event = $this->events['action.company.customer.attach'][0];
        self::assertInstanceOf(CompanyCustomerEvent::class, $event);
        self::assertSame($customer->getId(), $event->getBuyer()->getCustomerId());
    }

    public function testAGuestIsNeverAttached(): void
    {
        $company = $this->company();
        $guest = $this->guest();

        try {
            $this->facade->attach($company, $guest, CompanyRoleCode::BUYER, null, null, null);
            self::fail('A guest joined a company.');
        } catch (GuestCannotJoinCompanyException) {
        }

        self::assertNull($this->buyerRow($guest));
        self::assertArrayNotHasKey('action.company.customer.attach', $this->events);
    }

    public function testAnAccountBelongsToOneCompanyAtMost(): void
    {
        $company = $this->company('Acme');
        $other = $this->company('Globex');
        $customer = $this->buyerOf($company);

        foreach ([$company, $other] as $target) {
            try {
                $this->facade->attach($target, $customer, CompanyRoleCode::APPROVER, null, null, null);
                self::fail('The account joined a second time.');
            } catch (CustomerAlreadyInCompanyException) {
            }
        }

        self::assertSame($company->getId(), $this->buyerRow($customer)->getCompanyId());
        self::assertSame(CompanyRoleCode::BUYER, $this->reader->membershipOf($customer)->roleCode);
        self::assertCount(1, $this->events['action.company.customer.attach']);
    }

    public function testARoleThatDoesNotExistOrBelongsToAnotherCompanyIsRefused(): void
    {
        $company = $this->company('Acme');
        $other = $this->company('Globex');
        $this->roleOf('globex_only', $other, [CompanyPermission::ORDER_PLACE]);
        $this->roleOf('acme_only', $company, [CompanyPermission::ORDER_PLACE]);

        foreach (['no_such_role', 'globex_only'] as $code) {
            $customer = $this->customer();

            try {
                $this->facade->attach($company, $customer, $code, null, null, null);
                self::fail('The role '.$code.' was given.');
            } catch (UnknownCompanyRoleException) {
            }

            self::assertNull($this->buyerRow($customer));
        }

        $customer = $this->customer();
        $this->facade->attach($company, $customer, 'acme_only', null, null, null);
        self::assertSame('acme_only', $this->reader->membershipOf($customer)->roleCode, 'The role of the company itself is given.');
    }

    public function testALimitThatIsNotAnAmountOrAPeriodThatIsNotOneIsRefused(): void
    {
        $company = $this->company();

        foreach ([
            ['-1', null, null],
            ['abc', null, null],
            [null, '1e3', 'month'],
            [null, '2000', 'fortnight'],
            [null, '2000', null],
            ['', null, null],
            ['12345678901', null, null],
        ] as [$orderLimit, $periodLimit, $period]) {
            $customer = $this->customer();

            try {
                $this->facade->attach($company, $customer, CompanyRoleCode::BUYER, $orderLimit, $periodLimit, $period);
                self::fail('Accepted: '.var_export([$orderLimit, $periodLimit, $period], true));
            } catch (InvalidCompanyDataException) {
            }

            self::assertNull($this->buyerRow($customer));
        }

        $customer = $this->customer();
        $this->facade->attach($company, $customer, CompanyRoleCode::BUYER, '0', '0.000001', 'week');
        self::assertSame('0.000000', $this->reader->membershipOf($customer)->orderLimit, 'Zero is a limit, not an absence of one.');
    }

    public function testABuyerWhoLeavesHandsTheSharedAddressesTheyCreatedToTheFirstAdministratorWhoStays(): void
    {
        $company = $this->company();
        $leaving = $this->buyerOf($company);
        $buyerOrder = $this->orderOf($leaving, $company, $leaving);

        // The first administrator is disabled, the second is the one who takes over, the third came later.
        $disabled = $this->customer();
        $disabled->setEnable(0)->save($this->getPropelConnection());
        $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR, $disabled);
        $successor = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $latecomer = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);

        $warehouse = $this->addressOf($leaving, $company, 'Warehouse');
        $home = $this->addressOf($leaving, null, 'Home');
        $others = $this->addressOf($successor, $company, 'Head office');

        self::assertNotNull($this->reader->membershipOf($leaving), 'Read once, and remembered.');

        $this->facade->detach($leaving);

        self::assertSame($successor->getId(), AddressQuery::create()->findPk($warehouse->getId())->getCustomerId());
        self::assertSame($company->getId(), AddressQuery::create()->findPk($warehouse->getId())->getCompanyId(), 'It stays shared.');
        self::assertSame($leaving->getId(), AddressQuery::create()->findPk($home->getId())->getCustomerId(), 'A personal address stays with its owner.');
        self::assertSame($successor->getId(), AddressQuery::create()->findPk($others->getId())->getCustomerId());
        self::assertNull($this->buyerRow($leaving));
        self::assertNull($this->reader->membershipOf($leaving));
        self::assertNotNull($this->buyerRow($latecomer));

        $order = OrderQuery::create()->findPk($buyerOrder->getId());
        self::assertSame($company->getId(), $order->getCompanyId(), 'The history keeps the company the order was placed for.');
        self::assertSame($leaving->getId(), $order->getPlacedByCustomerId());

        self::assertCount(1, $this->events['action.company.customer.detach'] ?? []);
        $event = $this->events['action.company.customer.detach'][0];
        self::assertInstanceOf(CompanyCustomerDetachedEvent::class, $event);
        self::assertSame([$warehouse->getId()], $event->getTransferredAddressIds());
        self::assertSame($successor->getId(), $event->getTransferredToCustomerId());
        self::assertSame($leaving->getId(), $event->getCustomerId());
    }

    public function testTheAddressHandedOverIsNoLongerTheDefaultOfTheOneWhoLeft(): void
    {
        $company = $this->company();
        $leaving = $this->buyerOf($company);
        $successor = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $warehouse = $this->addressOf($leaving, $company, 'Warehouse');
        $warehouse->setIsDefault(1)->save($this->getPropelConnection());

        $this->facade->detach($leaving);

        $handedOver = AddressQuery::create()->findPk($warehouse->getId());
        self::assertSame($successor->getId(), $handedOver->getCustomerId());
        self::assertSame(0, $handedOver->getIsDefault(), 'A customer has one default address, and it is not the one they were handed.');
    }

    public function testTheLastAdministratorCannotLeaveAndNothingChanges(): void
    {
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $this->buyerOf($company);
        $address = $this->addressOf($administrator, $company);

        try {
            $this->facade->detach($administrator);
            self::fail('The last administrator left.');
        } catch (LastCompanyAdministratorException) {
        }

        self::assertNotNull($this->buyerRow($administrator));
        self::assertSame($administrator->getId(), AddressQuery::create()->findPk($address->getId())->getCustomerId());
        self::assertSame(CompanyRoleCode::ADMINISTRATOR, $this->reader->membershipOf($administrator)->roleCode);
        self::assertArrayNotHasKey('action.company.customer.detach', $this->events);
    }

    public function testAnAdministratorWhoIsNotTheLastCanLeave(): void
    {
        $company = $this->company();
        $first = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $second = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $address = $this->addressOf($first, $company);

        $this->facade->detach($first);

        self::assertNull($this->buyerRow($first));
        self::assertSame($second->getId(), AddressQuery::create()->findPk($address->getId())->getCustomerId());
    }

    public function testABuyerWhoCreatedSharedAddressesCannotLeaveWhenNoAdministratorStaysToTakeThem(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $address = $this->addressOf($buyer, $company);

        try {
            $this->facade->detach($buyer);
            self::fail('The shared address was left with nobody.');
        } catch (LastCompanyAdministratorException) {
        }

        self::assertNotNull($this->buyerRow($buyer));
        self::assertSame($buyer->getId(), AddressQuery::create()->findPk($address->getId())->getCustomerId());
    }

    public function testABuyerWithNoSharedAddressLeavesAnAdministratorlessCompanyFreely(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);

        $this->facade->detach($buyer);

        self::assertNull($this->buyerRow($buyer));
    }

    public function testTheLastAdministratorCannotLeaveEvenWithoutSharedAddresses(): void
    {
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $this->buyerOf($company);

        $this->expectException(LastCompanyAdministratorException::class);

        $this->facade->detach($administrator);
    }

    public function testTheAddressesAreHandedToAnAdministratorOfTheSameCompanyOnly(): void
    {
        $other = $this->company('Globex');
        $this->buyerOf($other, CompanyRoleCode::ADMINISTRATOR);

        $company = $this->company('Acme');
        $leaving = $this->buyerOf($company);
        $successor = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $warehouse = $this->addressOf($leaving, $company);

        $this->facade->detach($leaving);

        self::assertSame($successor->getId(), AddressQuery::create()->findPk($warehouse->getId())->getCustomerId());
    }

    public function testARoleOfACompanyThatBorrowsTheCodeOfTheAdministratorIsNotTheAdministrator(): void
    {
        $company = $this->company();
        $borrowed = $this->roleOf(CompanyRoleCode::ADMINISTRATOR, $company, [CompanyPermission::ORDER_PLACE]);
        $customer = $this->customer();
        $buyer = new CompanyCustomer();
        $buyer->setCompanyId((int) $company->getId());
        $buyer->setCustomerId((int) $customer->getId());
        $buyer->setCompanyRoleId((int) $borrowed->getId());
        $buyer->save($this->getPropelConnection());

        $this->facade->detach($customer);

        self::assertNull($this->buyerRow($customer), 'It is a role like another: its holder leaves freely.');
    }

    public function testACustomerThatIsNotStoredIsNotAttached(): void
    {
        $company = $this->company();

        $this->expectException(InvalidCompanyDataException::class);

        $this->facade->attach($company, new Customer(), CompanyRoleCode::BUYER, null, null, null);
    }

    public function testACompanyThatDoesNotExistIsRefusedByEveryOperationOnIt(): void
    {
        $gone = $this->company('Gone');
        $this->facade->delete($gone);
        $customer = $this->customer();

        foreach ([
            'update' => fn () => $this->facade->update($gone, new CompanyData(name: 'Gone')),
            'attach' => fn () => $this->facade->attach($gone, $customer, CompanyRoleCode::BUYER, null, null, null),
            'suspend' => fn () => $this->facade->suspend($gone),
            'reactivate' => fn () => $this->facade->reactivate($gone),
            'delete' => fn () => $this->facade->delete($gone),
            'never stored' => fn () => $this->facade->suspend(new Company()),
        ] as $operation => $call) {
            try {
                $call();
                self::fail('The operation '.$operation.' went through.');
            } catch (UnknownCompanyException) {
            }
        }

        self::assertNull($this->buyerRow($customer));
    }

    public function testDetachingACustomerWhoBelongsToNoCompanyIsRefused(): void
    {
        $this->expectException(CustomerNotInCompanyException::class);

        $this->facade->detach($this->customer());
    }

    public function testTheRoleOfABuyerChangesAndTheShopIsTold(): void
    {
        $company = $this->company();
        $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyerOf($company);

        $this->facade->changeRole($buyer, CompanyRoleCode::APPROVER);

        self::assertSame(CompanyRoleCode::APPROVER, $this->reader->membershipOf($buyer)->roleCode);
        self::assertCount(1, $this->events['action.company.customer.changeRole'] ?? []);
        $event = $this->events['action.company.customer.changeRole'][0];
        self::assertInstanceOf(CompanyCustomerRoleChangedEvent::class, $event);
        self::assertSame(CompanyRoleCode::BUYER, $event->getPreviousRoleCode());
        self::assertSame(CompanyRoleCode::APPROVER, $event->getRoleCode());

        $this->facade->changeRole($buyer, CompanyRoleCode::APPROVER);
        self::assertCount(1, $this->events['action.company.customer.changeRole'], 'The same role again changes nothing and tells nobody.');
    }

    public function testTheLastAdministratorKeepsTheRoleButAnotherOneMayLoseIt(): void
    {
        $company = $this->company();
        $first = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);

        try {
            $this->facade->changeRole($first, CompanyRoleCode::BUYER);
            self::fail('The last administrator lost the role.');
        } catch (LastCompanyAdministratorException) {
        }

        self::assertSame(CompanyRoleCode::ADMINISTRATOR, $this->reader->membershipOf($first)->roleCode);
        self::assertArrayNotHasKey('action.company.customer.changeRole', $this->events);

        $second = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $this->facade->changeRole($first, CompanyRoleCode::BUYER);

        self::assertSame(CompanyRoleCode::BUYER, $this->reader->membershipOf($first)->roleCode);

        try {
            $this->facade->changeRole($second, CompanyRoleCode::BUYER);
            self::fail('The administrator who stayed lost the role.');
        } catch (LastCompanyAdministratorException) {
        }
    }

    public function testARoleThatTheCompanyCannotGiveIsRefusedWhenChangingARole(): void
    {
        $company = $this->company();
        $other = $this->company('Globex');
        $this->roleOf('globex_only', $other, [CompanyPermission::ORDER_PLACE]);
        $buyer = $this->buyerOf($company);

        $this->expectException(UnknownCompanyRoleException::class);

        $this->facade->changeRole($buyer, 'globex_only');
    }

    public function testTheLimitsOfABuyerChangeAndAreValidated(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company, CompanyRoleCode::BUYER, null, '100', '1000', 'week');

        self::assertSame('100.000000', $this->reader->membershipOf($buyer)->orderLimit, 'Read once, and remembered.');

        $this->facade->changeLimits($buyer, null, '3000', 'year');

        $membership = $this->reader->membershipOf($buyer);
        self::assertNull($membership->orderLimit);
        self::assertSame('3000.000000', $membership->periodLimit);
        self::assertSame('year', $membership->period);
        self::assertCount(1, $this->events['action.company.customer.changeLimits'] ?? []);
        self::assertInstanceOf(CompanyCustomerEvent::class, $this->events['action.company.customer.changeLimits'][0]);

        try {
            $this->facade->changeLimits($buyer, '-5', null, null);
            self::fail('A negative limit was accepted.');
        } catch (InvalidCompanyDataException) {
        }

        self::assertSame('3000.000000', $this->reader->membershipOf($buyer)->periodLimit);
        self::assertCount(1, $this->events['action.company.customer.changeLimits']);
    }

    public function testACompanyIsSuspendedAndReactivatedAndEveryBuyerSeesIt(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);

        self::assertTrue($this->checker->can($buyer, CompanyPermission::ORDER_PLACE));

        $this->facade->suspend($company);

        self::assertSame(0, $company->getActive(), 'The model the caller holds is up to date.');
        self::assertSame(0, CompanyQuery::create()->findPk($company->getId())->getActive());
        self::assertFalse($this->reader->membershipOf($buyer)->companyActive);
        self::assertFalse($this->checker->can($buyer, CompanyPermission::ORDER_PLACE));
        self::assertCount(1, $this->events['action.company.suspend'] ?? []);

        $this->facade->suspend($company);
        self::assertCount(1, $this->events['action.company.suspend'], 'Suspending a suspended company tells nobody.');

        $this->facade->reactivate($company);

        self::assertSame(1, CompanyQuery::create()->findPk($company->getId())->getActive());
        self::assertTrue($this->checker->can($buyer, CompanyPermission::ORDER_PLACE));
        self::assertCount(1, $this->events['action.company.reactivate'] ?? []);
        self::assertInstanceOf(CompanyEvent::class, $this->events['action.company.reactivate'][0]);
    }

    public function testACompanyThatOrdersWerePlacedForIsNotDeleted(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $this->orderOf($buyer, $company, $buyer);

        try {
            $this->facade->delete($company);
            self::fail('A company with orders was deleted.');
        } catch (CompanyHasOrdersException) {
        }

        self::assertNotNull(CompanyQuery::create()->findPk($company->getId()));
        self::assertNotNull($this->buyerRow($buyer));
        self::assertArrayNotHasKey('action.company.delete', $this->events);
    }

    public function testACompanyWhoseBuyerHoldsARoleOfItsOwnIsDeletedWithBoth(): void
    {
        $company = $this->company();
        $role = $this->roleOf('acme_only', $company, [CompanyPermission::ORDER_PLACE]);
        $buyer = $this->buyerOf($company, 'acme_only');
        $other = $this->company('Globex');
        $sharedRole = $this->buyerOf($other);

        self::assertSame((int) $role->getId(), $this->buyerRow($buyer)?->getCompanyRoleId());

        $this->facade->delete($company);

        self::assertNull(CompanyRoleQuery::create()->findPk($role->getId()), 'The role of the company goes with it, though a buyer held it.');
        self::assertNull($this->buyerRow($buyer));
        self::assertNotNull($this->buyerRow($sharedRole), 'Another company is left alone.');
        self::assertNotNull(CustomerQuery::create()->findPk($buyer->getId()), 'The account stays.');
    }

    public function testACompanyWithoutOrdersIsDeletedWithItsBuyersAndItsRolesAndItsAddressesBecomePersonal(): void
    {
        $company = $this->company();
        $administrator = $this->buyerOf($company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyerOf($company);
        $role = $this->roleOf('acme_only', $company, [CompanyPermission::ORDER_PLACE]);
        $address = $this->addressOf($buyer, $company);
        $this->orderOf($this->customer());
        $companyId = (int) $company->getId();

        self::assertNotNull($this->reader->membershipOf($administrator));
        self::assertNotNull($this->reader->membershipOf($buyer));

        $this->facade->delete($company);

        self::assertNull(CompanyQuery::create()->findPk($companyId));
        self::assertSame(0, CompanyCustomerQuery::create()->filterByCompanyId($companyId)->count());
        self::assertNull(CompanyRoleQuery::create()->findPk($role->getId()));
        self::assertNull($this->reader->membershipOf($administrator));
        self::assertNull($this->reader->membershipOf($buyer));

        $address = AddressQuery::create()->findPk($address->getId());
        self::assertNull($address->getCompanyId());
        self::assertSame($buyer->getId(), $address->getCustomerId());
        self::assertCount(1, $this->events['action.company.delete'] ?? []);
        self::assertSame($companyId, $this->events['action.company.delete'][0]->getCompany()->getId());
    }
}
