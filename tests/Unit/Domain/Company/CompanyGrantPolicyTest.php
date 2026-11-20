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

namespace Thelia\Tests\Unit\Domain\Company;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Domain\Company\DTO\CompanyMembership;
use Thelia\Domain\Company\Exception\CompanyGrantRefusedException;
use Thelia\Domain\Company\Service\CompanyGrantPolicy;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Domain\Company\Service\CompanyPermissionChecker;
use Thelia\Model\CompanyRole;

/**
 * Whoever manages the buyers cannot raise anyone above themselves.
 */
final class CompanyGrantPolicyTest extends TestCase
{
    private const int COMPANY = 1;
    private const int ISSUER = 5;

    public function testAnIssuerGivesARoleWithinTheirOwnAndLimitsWithinTheirOwn(): void
    {
        $policy = $this->policyFor($this->issuer(orderLimit: '500', periodLimit: '2000', period: 'month'));

        $policy->assertIssuerMayGrant(self::ISSUER, self::COMPANY, $this->role([CompanyPermission::ORDER_PLACE]), '500.000000', '1999.99', 'month');
        $policy->assertIssuerMayGrant(self::ISSUER, self::COMPANY, $this->role([CompanyPermission::ORDER_PLACE, CompanyPermission::BUYER_MANAGE]), '100', '2000', 'month');

        $this->expectNotToPerformAssertions();
    }

    public function testAnIssuerWithoutTheRightToManageBuyersGivesNothing(): void
    {
        $policy = $this->policyFor($this->issuer(permissions: [CompanyPermission::ORDER_PLACE, CompanyPermission::ORDER_VIEW_OWN]));

        $this->expectExceptionObject(CompanyGrantRefusedException::issuerCannotManageBuyers());
        $policy->assertIssuerMayGrant(self::ISSUER, self::COMPANY, $this->role([CompanyPermission::ORDER_PLACE]), null, null, null);
    }

    public function testAnIssuerOutsideTheCompanyGivesNothing(): void
    {
        $policy = $this->policyFor($this->issuer());

        $this->expectExceptionObject(CompanyGrantRefusedException::issuerCannotManageBuyers());
        $policy->assertIssuerMayGrant(self::ISSUER, 2, $this->role([CompanyPermission::ORDER_PLACE]), null, null, null);
    }

    public function testARoleThatHoldsAPermissionTheIssuerLacksIsRefused(): void
    {
        $policy = $this->policyFor($this->issuer(permissions: [CompanyPermission::BUYER_MANAGE, CompanyPermission::ORDER_PLACE]));

        $this->expectExceptionObject(CompanyGrantRefusedException::roleGoesBeyondTheIssuer());
        $policy->assertIssuerMayGrant(self::ISSUER, self::COMPANY, $this->role([CompanyPermission::ORDER_PLACE, CompanyPermission::COMPANY_EDIT]), null, null, null);
    }

    public function testNoLimitIsHigherThanTheLimitOfTheIssuer(): void
    {
        $policy = $this->policyFor($this->issuer(orderLimit: '500'));

        $this->expectExceptionObject(CompanyGrantRefusedException::limitsGoBeyondTheIssuer());
        $policy->assertIssuerMayGrant(self::ISSUER, self::COMPANY, $this->role([CompanyPermission::ORDER_PLACE]), '500.000001', null, null);
    }

    public function testAnIssuerWithALimitCannotGiveNoLimit(): void
    {
        $policy = $this->policyFor($this->issuer(orderLimit: '500'));

        $this->expectExceptionObject(CompanyGrantRefusedException::limitsGoBeyondTheIssuer());
        $policy->assertIssuerMayGrant(self::ISSUER, self::COMPANY, $this->role([CompanyPermission::ORDER_PLACE]), null, null, null);
    }

    public function testThePeriodLimitIsHeldToTheAmountOfTheIssuer(): void
    {
        $policy = $this->policyFor($this->issuer(periodLimit: '2000', period: 'month'));

        $this->expectExceptionObject(CompanyGrantRefusedException::limitsGoBeyondTheIssuer());
        $policy->assertIssuerMayGrant(self::ISSUER, self::COMPANY, $this->role([CompanyPermission::ORDER_PLACE]), null, '2000.01', 'month');
    }

    public function testAPeriodLimitIsNotComparedWithAnotherPeriod(): void
    {
        $policy = $this->policyFor($this->issuer(periodLimit: '2000', period: 'month'));

        $this->expectExceptionObject(CompanyGrantRefusedException::limitsGoBeyondTheIssuer());
        $policy->assertIssuerMayGrant(self::ISSUER, self::COMPANY, $this->role([CompanyPermission::ORDER_PLACE]), null, '100', 'year');
    }

    public function testAnIssuerWithALimitOverAPeriodCannotGiveNone(): void
    {
        $policy = $this->policyFor($this->issuer(periodLimit: '2000', period: 'month'));

        $this->expectExceptionObject(CompanyGrantRefusedException::limitsGoBeyondTheIssuer());
        $policy->assertIssuerMayGrant(self::ISSUER, self::COMPANY, $this->role([CompanyPermission::ORDER_PLACE]), null, null, null);
    }

    public function testAPeriodWithoutAnAmountIsNoLimitAtAll(): void
    {
        $policy = $this->policyFor($this->issuer(periodLimit: '2000', period: 'month'));

        $this->expectExceptionObject(CompanyGrantRefusedException::limitsGoBeyondTheIssuer());
        $policy->assertIssuerMayGrant(self::ISSUER, self::COMPANY, $this->role([CompanyPermission::ORDER_PLACE]), null, null, 'month');
    }

    public function testAnIssuerWithNoLimitBindsNobodyToOne(): void
    {
        $policy = $this->policyFor($this->issuer());

        $policy->assertIssuerMayGrant(self::ISSUER, self::COMPANY, $this->role([CompanyPermission::ORDER_PLACE]), '999999.5', '5', 'week');

        $this->expectNotToPerformAssertions();
    }

    /**
     * @param list<string>|null $permissions the administrator's by default
     */
    private function issuer(?array $permissions = null, ?string $orderLimit = null, ?string $periodLimit = null, ?string $period = null): CompanyMembership
    {
        return new CompanyMembership(
            companyId: self::COMPANY,
            customerId: self::ISSUER,
            roleCode: CompanyRoleCode::ADMINISTRATOR,
            permissions: $permissions ?? [
                CompanyPermission::ORDER_PLACE,
                CompanyPermission::ORDER_VIEW_OWN,
                CompanyPermission::ORDER_VIEW_COMPANY,
                CompanyPermission::ADDRESS_MANAGE_SHARED,
                CompanyPermission::BUYER_MANAGE,
                CompanyPermission::COMPANY_EDIT,
            ],
            companyActive: true,
            orderLimit: $orderLimit,
            periodLimit: $periodLimit,
            period: $period,
        );
    }

    private function policyFor(CompanyMembership $issuer): CompanyGrantPolicy
    {
        $reader = $this->createMock(CompanyMembershipReader::class);
        $reader->method('membershipOf')->willReturnCallback(
            static fn (mixed $customer): ?CompanyMembership => self::ISSUER === $customer ? $issuer : null,
        );

        return new CompanyGrantPolicy($reader, new CompanyPermissionChecker($reader));
    }

    /**
     * @param list<string> $permissions
     */
    private function role(array $permissions): CompanyRole
    {
        $role = new CompanyRole();
        $role->setCode('custom');
        $role->setPermissions(json_encode($permissions, \JSON_THROW_ON_ERROR));

        return $role;
    }
}
