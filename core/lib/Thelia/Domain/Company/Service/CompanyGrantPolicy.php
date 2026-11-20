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

namespace Thelia\Domain\Company\Service;

use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\Exception\CompanyGrantRefusedException;
use Thelia\Model\CompanyRole;
use Thelia\Model\Customer;

/**
 * What a buyer of a company may hand to another: a role and limits that stay within their own.
 *
 * Whoever manages the buyers cannot raise anyone above themselves: the role grants no
 * permission they lack, and the limits are no higher than theirs. A limit they have none of
 * binds nobody; one they hold must be matched by a limit of the same period, since a weekly
 * and a monthly amount cannot be compared. The merchant, who is no buyer, is not bound.
 */
final readonly class CompanyGrantPolicy
{
    public function __construct(
        private CompanyMembershipReader $membershipReader,
        private CompanyPermissionChecker $permissionChecker,
    ) {
    }

    /**
     * Whoever changes or detaches a colleague holds, in their own role, every permission of the one
     * they touch: a role cannot be taken apart from below, by detaching the colleague or by giving
     * them a lesser role first.
     *
     * @throws CompanyGrantRefusedException
     */
    public function assertIssuerMayManageTheHolderOf(Customer|int $issuer, int $companyId, ?CompanyRole $heldRole): void
    {
        $membership = $this->membershipReader->membershipOf($issuer);

        if (null === $membership || !$this->permissionChecker->can($issuer, CompanyPermission::BUYER_MANAGE, $companyId)) {
            throw CompanyGrantRefusedException::issuerCannotManageBuyers();
        }

        if ([] !== array_diff($heldRole?->getPermissionList() ?? [], $membership->permissions)) {
            throw CompanyGrantRefusedException::roleGoesBeyondTheIssuer();
        }
    }

    /**
     * @throws CompanyGrantRefusedException
     */
    public function assertIssuerMayGrant(
        Customer|int $issuer,
        int $companyId,
        CompanyRole $role,
        ?string $orderLimit,
        ?string $periodLimit,
        ?string $period,
    ): void {
        $membership = $this->membershipReader->membershipOf($issuer);

        if (null === $membership || !$this->permissionChecker->can($issuer, CompanyPermission::BUYER_MANAGE, $companyId)) {
            throw CompanyGrantRefusedException::issuerCannotManageBuyers();
        }

        if ([] !== array_diff($role->getPermissionList(), $membership->permissions)) {
            throw CompanyGrantRefusedException::roleGoesBeyondTheIssuer();
        }

        if (null !== $membership->orderLimit
            && (null === $orderLimit || CompanyAmount::compare($orderLimit, $membership->orderLimit) > 0)) {
            throw CompanyGrantRefusedException::limitsGoBeyondTheIssuer();
        }

        if (null !== $membership->periodLimit
            && (null === $periodLimit || $period !== $membership->period || CompanyAmount::compare($periodLimit, $membership->periodLimit) > 0)) {
            throw CompanyGrantRefusedException::limitsGoBeyondTheIssuer();
        }
    }
}
