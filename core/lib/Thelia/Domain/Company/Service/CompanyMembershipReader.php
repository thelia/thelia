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

use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Company\DTO\CompanyMembership;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyRole;
use Thelia\Model\Customer;

/**
 * The company a customer belongs to and what the customer holds there: the one answer the
 * permission checker, the checkout and the API read.
 *
 * It is read once per customer and request, from one query on the three tables, never from the
 * models in the Propel pool: a buyer moved by another process is not read as it was. A customer
 * who belongs to no company is remembered too, so that a shop without companies pays for one
 * indexed lookup of an empty table. The operations of {@see \Thelia\Domain\Company\CompanyFacade}
 * drop what they change; a long-lived worker is emptied between requests by {@see reset()}.
 */
class CompanyMembershipReader implements ResetInterface
{
    /** @var array<int, CompanyMembership|null> */
    private array $membershipByCustomer = [];

    public function membershipOf(Customer|int $customer): ?CompanyMembership
    {
        $customerId = $customer instanceof Customer ? (int) $customer->getId() : $customer;

        if ($customerId <= 0) {
            return null;
        }

        if (!\array_key_exists($customerId, $this->membershipByCustomer)) {
            $this->membershipByCustomer[$customerId] = $this->read($customerId);
        }

        return $this->membershipByCustomer[$customerId];
    }

    public function forget(int $customerId): void
    {
        unset($this->membershipByCustomer[$customerId]);
    }

    public function reset(): void
    {
        $this->membershipByCustomer = [];
    }

    private function read(int $customerId): ?CompanyMembership
    {
        /** @var array{CompanyId: int|string, OrderLimit: string|null, PeriodLimit: string|null, Period: string|null, RoleCode: string, RolePermissions: string, CompanyActive: int|string, CompanyBillingAddressId: int|string|null}|null $row */
        $row = CompanyCustomerQuery::create()
            ->filterByCustomerId($customerId)
            ->joinCompany()
            ->joinCompanyRole()
            ->withColumn('CompanyRole.Code', 'RoleCode')
            ->withColumn('CompanyRole.Permissions', 'RolePermissions')
            ->withColumn('Company.Active', 'CompanyActive')
            ->withColumn('Company.BillingAddressId', 'CompanyBillingAddressId')
            ->select(['CompanyId', 'OrderLimit', 'PeriodLimit', 'Period', 'RoleCode', 'RolePermissions', 'CompanyActive', 'CompanyBillingAddressId'])
            ->findOne();

        if (null === $row) {
            return null;
        }

        return new CompanyMembership(
            companyId: (int) $row['CompanyId'],
            customerId: $customerId,
            roleCode: (string) $row['RoleCode'],
            permissions: CompanyRole::decodePermissions((string) $row['RolePermissions']),
            companyActive: 1 === (int) $row['CompanyActive'],
            companyHasBillingAddress: null !== $row['CompanyBillingAddressId'],
            orderLimit: null === $row['OrderLimit'] ? null : (string) $row['OrderLimit'],
            periodLimit: null === $row['PeriodLimit'] ? null : (string) $row['PeriodLimit'],
            period: null === $row['Period'] ? null : (string) $row['Period'],
        );
    }
}
