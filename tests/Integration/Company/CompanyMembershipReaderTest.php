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
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyRoleQuery;

final class CompanyMembershipReaderTest extends CompanyDomainTestCase
{
    public function testAMemberIsReadWithItsCompanyItsRoleAndItsLimits(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company, CompanyRoleCode::APPROVER, null, '500', '2000', 'month');

        $membership = $this->reader->membershipOf($buyer);

        self::assertNotNull($membership);
        self::assertSame($company->getId(), $membership->companyId);
        self::assertSame($buyer->getId(), $membership->customerId);
        self::assertSame(CompanyRoleCode::APPROVER, $membership->roleCode);
        self::assertSame([CompanyPermission::ORDER_VIEW_OWN, CompanyPermission::ORDER_VIEW_COMPANY, CompanyPermission::ORDER_APPROVE], $membership->permissions);
        self::assertTrue($membership->companyActive);
        self::assertSame('500.000000', $membership->orderLimit);
        self::assertSame('2000.000000', $membership->periodLimit);
        self::assertSame('month', $membership->period);
        self::assertEquals($membership, $this->reader->membershipOf((int) $buyer->getId()), 'A customer or its id are the same question.');
    }

    public function testACustomerWithoutACompanyAndOneWhoIsNotStoredBelongToNone(): void
    {
        self::assertNull($this->reader->membershipOf($this->customer()));
        self::assertNull($this->reader->membershipOf(0));
        self::assertNull($this->reader->membershipOf(new \Thelia\Model\Customer()));
    }

    public function testTheAnswerIsReadOncePerCustomerUntilItIsForgottenOrTheReaderIsReset(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);

        self::assertSame(CompanyRoleCode::BUYER, $this->reader->membershipOf($buyer)->roleCode);

        // Written behind the reader's back, as another process would.
        CompanyCustomerQuery::create()->filterByCustomerId($buyer->getId())->update([
            'CompanyRoleId' => $this->sharedRole(CompanyRoleCode::APPROVER)->getId(),
        ]);

        self::assertSame(CompanyRoleCode::BUYER, $this->reader->membershipOf($buyer)->roleCode, 'Remembered for the request.');

        $this->reader->forget((int) $buyer->getId());
        self::assertSame(CompanyRoleCode::APPROVER, $this->reader->membershipOf($buyer)->roleCode);

        CompanyCustomerQuery::create()->filterByCustomerId($buyer->getId())->delete();
        self::assertNotNull($this->reader->membershipOf($buyer));

        $this->reader->reset();
        self::assertNull($this->reader->membershipOf($buyer));
    }

    public function testAMemberIsReadFromTheRowsNotFromTheModelsThePoolKept(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);

        \Propel\Runtime\Propel::enableInstancePooling();

        try {
            $row = CompanyCustomerQuery::create()->filterByCustomerId($buyer->getId())->findOne();
            self::assertNotNull($row);
            CompanyCustomerQuery::create()->filterById($row->getId())->update(['OrderLimit' => '42']);

            self::assertSame('42.000000', $this->reader->membershipOf($buyer)->orderLimit);
        } finally {
            \Propel\Runtime\Propel::disableInstancePooling();
        }
    }

    public function testASuspendedCompanyIsReadAsSuch(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $this->facade->suspend($company);

        self::assertFalse($this->reader->membershipOf($buyer)->companyActive);
    }

    public function testARoleWhoseStoredPermissionsAreNotAListOfStringsGrantsNothing(): void
    {
        $company = $this->company();

        foreach (['not json', '{"order.place":true}', '"order.place"', '[1,2]', ''] as $stored) {
            $role = $this->roleOf('broken'.md5($stored), $company, []);
            $role->setPermissions($stored)->save($this->getPropelConnection());
            $buyer = $this->buyerOf($company, $role->getCode());

            self::assertSame([], $this->reader->membershipOf($buyer)->permissions, var_export($stored, true));
            self::assertFalse($this->checker->can($buyer, CompanyPermission::ORDER_PLACE), var_export($stored, true));
        }

        $role = $this->roleOf('mixed', $company, []);
        $role->setPermissions('["order.place", 3, null, "quote.request"]')->save($this->getPropelConnection());
        self::assertSame(['order.place', 'quote.request'], CompanyRoleQuery::create()->findPk($role->getId())->getPermissionList());
    }
}
