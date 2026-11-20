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
use Thelia\Domain\Company\Exception\CompanyBuyerNotFoundException;
use Thelia\Domain\Company\Exception\CompanyGrantRefusedException;
use Thelia\Domain\Company\Service\CompanyAccountWriter;
use Thelia\Domain\Company\Service\CompanyGrantPolicy;
use Thelia\Domain\Company\Service\CompanyRoleResolver;
use Thelia\Model\Company;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyQuery;

/**
 * The writes a buyer makes about their company from their account: the caller is judged by the
 * domain, whichever front asks.
 */
final class CompanyAccountWriterTest extends CompanyDomainTestCase
{
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->company('Acme');
    }

    public function testTheAdministratorChangesTheDetailsAndNothingMore(): void
    {
        $this->company->setParentId(7)->setInternalNote('slow payer')->save($this->getPropelConnection());
        $administrator = $this->buyerOf($this->company, CompanyRoleCode::ADMINISTRATOR);

        $this->writer()->updateCompany($administrator, 'Acme Group', '12345678901234', 'FR12345678901', 'orders@acme.test');

        $stored = CompanyQuery::create()->findPk($this->company->getId());
        self::assertSame('Acme Group', $stored?->getName());
        self::assertSame('12345678901234', $stored?->getSiret());
        self::assertSame('orders@acme.test', $stored?->getNotificationEmail());
        self::assertSame(7, $stored?->getParentId());
        self::assertSame('slow payer', $stored?->getInternalNote());
    }

    public function testOnlyABuyerWhoseRoleEditsTheCompanyChangesItsDetails(): void
    {
        $buyer = $this->buyerOf($this->company);

        foreach ([$buyer, $this->customer()] as $caller) {
            try {
                $this->writer()->updateCompany($caller, 'Mine', null, null, null);
                self::fail('A caller with no right to edit the company edited it.');
            } catch (CompanyGrantRefusedException $exception) {
                self::assertSame(CompanyGrantRefusedException::issuerCannotEditTheCompany()->getMessage(), $exception->getMessage());
            }
        }

        self::assertSame('Acme', CompanyQuery::create()->findPk($this->company->getId())?->getName());
    }

    public function testAColleagueOfAnotherCompanyOrOneThatCannotBeManagedIsNotThere(): void
    {
        $administrator = $this->buyerOf($this->company, CompanyRoleCode::ADMINISTRATOR);
        $simple = $this->buyerOf($this->company);
        $stranger = $this->buyerOf($this->company('Globex'));
        $strangerLink = CompanyCustomerQuery::create()->findOneByCustomerId($stranger->getId());
        $colleagueLink = CompanyCustomerQuery::create()->findOneByCustomerId($this->buyerOf($this->company)->getId());

        foreach ([
            'another company' => [$administrator, (int) $strangerLink->getId()],
            'a caller who manages no buyer' => [$simple, (int) $colleagueLink->getId()],
            'no such link' => [$administrator, 99999999],
        ] as $label => [$caller, $buyerId]) {
            try {
                $this->writer()->changeBuyer($caller, $buyerId, CompanyRoleCode::BUYER, null, null, null);
                self::fail('Changed: '.$label);
            } catch (CompanyBuyerNotFoundException) {
            }

            try {
                $this->writer()->detachBuyer($caller, $buyerId);
                self::fail('Detached: '.$label);
            } catch (CompanyBuyerNotFoundException) {
            }
        }

        self::assertNotNull(CompanyCustomerQuery::create()->findPk($strangerLink->getId()));
    }

    public function testTheRoleOfAColleagueCannotBeTakenApartFromBelow(): void
    {
        $administrator = $this->buyerOf($this->company, CompanyRoleCode::ADMINISTRATOR);
        $approver = $this->buyerOf($this->company, 'approver');
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($approver->getId());

        try {
            $this->writer()->changeBuyer($administrator, (int) $link->getId(), CompanyRoleCode::BUYER, null, null, null);
            self::fail('An approver was demoted by an administrator, who lacks order.approve.');
        } catch (CompanyGrantRefusedException $exception) {
            self::assertSame(CompanyGrantRefusedException::roleGoesBeyondTheIssuer()->getMessage(), $exception->getMessage());
        }

        try {
            $this->writer()->detachBuyer($administrator, (int) $link->getId());
            self::fail('An approver was detached by an administrator.');
        } catch (CompanyGrantRefusedException) {
        }

        self::assertSame('approver', CompanyCustomerQuery::create()->findPk($link->getId())?->getCompanyRole()?->getCode());
    }

    public function testTheMerchantChangesAnyBuyerWhateverTheirRole(): void
    {
        $this->buyerOf($this->company, CompanyRoleCode::ADMINISTRATOR);
        $approver = $this->buyerOf($this->company, 'approver');
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($approver->getId());

        $this->writer()->applyChanges($link, CompanyRoleCode::BUYER, '100', null, null, null);

        $stored = CompanyCustomerQuery::create()->findPk($link->getId());
        self::assertSame('buyer', $stored?->getCompanyRole()?->getCode());
        self::assertSame(0, bccomp('100', (string) $stored?->getOrderLimit(), 6));
    }

    public function testAnAdministratorChangesAndDetachesABuyer(): void
    {
        $this->roleOf('purchaser', $this->company, ['order.place', 'order.view_own', 'order.view_company']);
        $administrator = $this->buyerOf($this->company, CompanyRoleCode::ADMINISTRATOR);
        $buyer = $this->buyerOf($this->company, 'purchaser');
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($buyer->getId());

        $this->writer()->changeBuyer($administrator, (int) $link->getId(), null, '250', '1000', 'month');

        $stored = CompanyCustomerQuery::create()->findPk($link->getId());
        self::assertSame('purchaser', $stored?->getCompanyRole()?->getCode(), 'A role left out is the current one.');
        self::assertSame(0, bccomp('250', (string) $stored?->getOrderLimit(), 6));
        self::assertSame('month', $stored?->getPeriod());

        $this->writer()->detachBuyer($administrator, (int) $link->getId());

        self::assertNull($this->buyerRow($buyer));
    }

    private function writer(): CompanyAccountWriter
    {
        return new CompanyAccountWriter(
            $this->facade,
            $this->reader,
            $this->checker,
            new CompanyRoleResolver(),
            new CompanyGrantPolicy($this->reader, $this->checker),
        );
    }
}
