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
use Thelia\Model\Address;
use Thelia\Model\Customer;
use Thelia\Model\Order;

/**
 * The one judge of what a customer may do for a company, and of which orders and addresses of a
 * company the customer may reach.
 *
 * A customer who belongs to no company is an ordinary account: it places orders and reads its
 * own, and nothing is asked about a company it has no standing in. A buyer holds what the
 * permissions of the role grant, no more; a suspended company places no order, but what its
 * buyers could read they still read.
 */
final readonly class CompanyPermissionChecker
{
    public function __construct(private CompanyMembershipReader $membershipReader)
    {
    }

    /**
     * Whether the customer holds the permission, for the company they belong to or, when one is
     * named, for that company only: a buyer of another company, and an account with no company,
     * hold nothing there.
     */
    public function can(Customer|int $customer, string $permission, ?int $companyId = null): bool
    {
        $membership = $this->membershipReader->membershipOf($customer);

        if (null === $membership) {
            return null === $companyId && \in_array($permission, CompanyPermission::WITHOUT_COMPANY, true);
        }

        if (null !== $companyId && $companyId !== $membership->companyId) {
            return false;
        }

        if (CompanyPermission::ORDER_PLACE === $permission && !$membership->companyActive) {
            return false;
        }

        return $membership->has($permission);
    }

    /**
     * Whether the customer reads this order: the one they placed, or, with the permission to read
     * the orders of the company, any order placed for the company they belong to.
     */
    public function canAccessOrder(Customer|int $customer, Order $order): bool
    {
        $customerId = $this->idOf($customer);
        $placedBy = $order->getPlacedByCustomerId() ?? $order->getCustomerId();

        if (null !== $placedBy && (int) $placedBy === $customerId && $this->can($customerId, CompanyPermission::ORDER_VIEW_OWN)) {
            return true;
        }

        $orderCompanyId = $order->getCompanyId();

        return null !== $orderCompanyId
            && $this->can($customerId, CompanyPermission::ORDER_VIEW_COMPANY, (int) $orderCompanyId);
    }

    /**
     * Whether the customer may read and choose this address: their own, or one shared with the
     * company they belong to.
     */
    public function canUseAddress(Customer|int $customer, Address $address): bool
    {
        $customerId = $this->idOf($customer);

        if ((int) $address->getCustomerId() === $customerId) {
            return true;
        }

        $sharedWith = $address->getCompanyId();

        return null !== $sharedWith
            && $this->membershipReader->membershipOf($customerId)?->companyId === (int) $sharedWith;
    }

    /**
     * Whether the customer may change or delete this address: their own, or one shared with the
     * company they belong to when the role lets them manage the shared ones.
     */
    public function canEditAddress(Customer|int $customer, Address $address): bool
    {
        $customerId = $this->idOf($customer);

        if ((int) $address->getCustomerId() === $customerId) {
            return true;
        }

        $sharedWith = $address->getCompanyId();

        return null !== $sharedWith
            && $this->can($customerId, CompanyPermission::ADDRESS_MANAGE_SHARED, (int) $sharedWith);
    }

    /**
     * The company whose objects the customer may see under this permission, to filter a query
     * with, or null when there is none.
     */
    public function visibleCompanyIdFor(Customer|int $customer, string $permission): ?int
    {
        $companyId = $this->membershipReader->membershipOf($customer)?->companyId;

        return null !== $companyId && $this->can($customer, $permission, $companyId) ? $companyId : null;
    }

    private function idOf(Customer|int $customer): int
    {
        return $customer instanceof Customer ? (int) $customer->getId() : $customer;
    }
}
