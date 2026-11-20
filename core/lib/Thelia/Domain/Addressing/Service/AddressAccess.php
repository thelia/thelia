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

namespace Thelia\Domain\Addressing\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Domain\Company\Service\CompanyPermissionChecker;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\CompanyQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\AddressTableMap;

/**
 * The addresses of the address book a customer may put on a cart: their own, and the ones shared
 * with the company they belong to. What the checkout, the delivery and the account read an
 * address through, so that the rule is the permission checker's and is written nowhere else.
 *
 * A customer who belongs to no company reads their own addresses, with the query they always had.
 */
final readonly class AddressAccess
{
    public function __construct(
        private CompanyPermissionChecker $permissionChecker,
        private CompanyMembershipReader $membershipReader,
    ) {
    }

    /**
     * The address, when the customer may use it, and nothing otherwise: an address that does not
     * exist and one the customer may not use are not told apart.
     */
    public function find(Customer|int|null $customer, ?int $addressId): ?Address
    {
        $customerId = $this->idOf($customer);

        if ($customerId <= 0 || null === $addressId || $addressId <= 0) {
            return null;
        }

        $address = AddressQuery::create()->filterById($addressId)->findOne();

        return null !== $address && $this->permissionChecker->canUseAddress($customerId, $address) ? $address : null;
    }

    public function canUse(Customer|int $customer, Address $address): bool
    {
        return $this->permissionChecker->canUseAddress($customer, $address);
    }

    /**
     * Whether the customer may change or delete the address: the one who created it, or, for an
     * address shared with their company, a buyer whose role manages the shared ones.
     */
    public function canEdit(Customer|int $customer, Address $address): bool
    {
        return $this->permissionChecker->canEditAddress($customer, $address);
    }

    /**
     * Restricts a query to the addresses the customer may use, in the one statement: the ones
     * they created or, when they belong to a company, the ones shared with it.
     */
    public function restrict(AddressQuery $query, Customer|int $customer): AddressQuery
    {
        $customerId = $this->idOf($customer);
        $membership = $this->membershipReader->membershipOf($customerId);

        if (null === $membership) {
            return $query->filterByCustomerId($customerId);
        }

        return $query
            ->condition('own', AddressTableMap::COL_CUSTOMER_ID.' = ?', $customerId, \PDO::PARAM_INT)
            ->condition('shared', AddressTableMap::COL_COMPANY_ID.' = ?', $membership->companyId, \PDO::PARAM_INT)
            ->where(['own', 'shared'], Criteria::LOGICAL_OR);
    }

    /**
     * Keeps the addresses that are, or are not, the default address of the customer. The default
     * address is the one of the account that owns it: an address a colleague shared is never the
     * default one of the customer it is listed for, whatever its own flag says.
     */
    public function filterByDefault(AddressQuery $query, Customer|int $customer, bool $isDefault): AddressQuery
    {
        $customerId = $this->idOf($customer);

        if (null === $this->membershipReader->membershipOf($customerId)) {
            return $query->filterByIsDefault($isDefault ? 1 : 0);
        }

        if ($isDefault) {
            return $query->filterByIsDefault(1)->filterByCustomerId($customerId);
        }

        return $query
            ->condition('not_default', AddressTableMap::COL_IS_DEFAULT.' = ?', 0, \PDO::PARAM_INT)
            ->condition('not_owned', AddressTableMap::COL_CUSTOMER_ID.' <> ?', $customerId, \PDO::PARAM_INT)
            ->where(['not_default', 'not_owned'], Criteria::LOGICAL_OR);
    }

    /**
     * The address a cart that ships nothing is taxed at when the customer has no default one: the
     * first of their own and, failing that, the first one shared with the company they belong to.
     * A shared address is never a default address: the default is the one of the account that
     * owns it.
     */
    public function firstOf(Customer|int $customer): ?Address
    {
        $customerId = $this->idOf($customer);
        $own = AddressQuery::create()->filterByCustomerId($customerId)->findOne();

        if (null !== $own || null === $this->membershipReader->membershipOf($customerId)) {
            return $own;
        }

        return $this->restrict(AddressQuery::create(), $customerId)->findOne();
    }

    /**
     * The address the invoices of a buyer are made out to when the company they belong to has
     * one: it replaces the choice of the buyer. Null for a customer who belongs to no company
     * and for a company with no billing address.
     */
    public function billingAddressImposedOn(Customer|int|null $customer): ?Address
    {
        $customerId = $this->idOf($customer);

        if ($customerId <= 0) {
            return null;
        }

        $membership = $this->membershipReader->membershipOf($customerId);

        if (null === $membership) {
            return null;
        }

        /** @var int|string|null $billingAddressId */
        $billingAddressId = CompanyQuery::create()
            ->filterById($membership->companyId)
            ->select('BillingAddressId')
            ->findOne();

        return null === $billingAddressId || '' === $billingAddressId
            ? null
            : AddressQuery::create()->filterById((int) $billingAddressId)->filterByCompanyId($membership->companyId)->findOne();
    }

    private function idOf(Customer|int|null $customer): int
    {
        return $customer instanceof Customer ? (int) $customer->getId() : (int) $customer;
    }
}
