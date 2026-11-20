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

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\FrontOperation;
use Thelia\Api\Resource\Address;
use Thelia\Api\Resource\Order;
use Thelia\Api\Security\AccountCaller;
use Thelia\Domain\Addressing\Service\AddressAccess;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Domain\Company\Service\CompanyPermissionChecker;
use Thelia\Model\AddressQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderQuery;

/**
 * Scopes the orders and the addresses of the account endpoints to what the caller may reach:
 * their own and, for a buyer of a company, what the company shares with them.
 *
 * It replaces the generic customer filter on these two resources, which would answer an empty
 * list to a buyer asking for what the company holds: the collection, the item read and the read
 * step of PUT and DELETE are all bounded by the one rule, and what is out of reach reads as "not
 * found", the answer a missing id gives.
 *
 * - An address is reachable when the caller created it or when it is shared with their company
 *   ({@see AddressAccess::restrict()}).
 * - An order is reachable when the caller placed it and may read their own, or when it was placed
 *   for their company and their role reads the orders of the company. A customer who belongs to
 *   no company keeps the filter they always had, on the owner of the order.
 *
 * The orders take a `scope` query parameter: `own` keeps the orders the caller placed, `company`
 * the ones of the company they may read, and nothing else is changed by it. `company` answers an
 * empty list to whoever lacks the right to read them.
 *
 * Who the caller is comes from {@see AccountCaller}: the token when there is one, the session
 * customer for a page of the theme reading in-process, nobody for a guest.
 */
final readonly class CompanyAccountOwnershipExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public const string SCOPE_PARAMETER = 'scope';

    public const string SCOPE_OWN = 'own';

    public const string SCOPE_COMPANY = 'company';

    private const string ACCOUNT_URI_PREFIX = '/front/account/';

    public function __construct(
        private AccountCaller $caller,
        private AddressAccess $addressAccess,
        private CompanyMembershipReader $membershipReader,
        private CompanyPermissionChecker $permissionChecker,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scope($query, $resourceClass, $operation, $context);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scope($query, $resourceClass, $operation, $context);
    }

    /**
     * Whether this extension, and not the generic customer filter, bounds the resource.
     */
    public static function handles(string $resourceClass, ?Operation $operation): bool
    {
        return \in_array($resourceClass, [Order::class, Address::class], true)
            && FrontOperation::isFront($operation)
            && $operation instanceof HttpOperation
            && str_starts_with((string) $operation->getUriTemplate(), self::ACCOUNT_URI_PREFIX);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function scope(ModelCriteria $query, string $resourceClass, ?Operation $operation, array $context): void
    {
        if (!self::handles($resourceClass, $operation)) {
            return;
        }

        $customer = $this->caller->customer();

        if (Address::class === $resourceClass && $query instanceof AddressQuery) {
            $this->scopeAddresses($query, $customer);
        } elseif (Order::class === $resourceClass && $query instanceof OrderQuery) {
            $scope = $context['filters'][self::SCOPE_PARAMETER] ?? null;
            $this->scopeOrders($query, $customer, \is_string($scope) ? $scope : null);
        }
    }

    private function scopeAddresses(AddressQuery $query, ?Customer $customer): void
    {
        if (null === $customer) {
            // address.id is the primary key: this criterion matches no row.
            $query->add(AddressTableMap::COL_ID, null, Criteria::ISNULL);

            return;
        }

        $this->addressAccess->restrict($query, $customer);
    }

    private function scopeOrders(OrderQuery $query, ?Customer $customer, ?string $scope): void
    {
        if (null === $customer) {
            $query->add(OrderTableMap::COL_ID, null, Criteria::ISNULL);

            return;
        }

        $customerId = (int) $customer->getId();

        if (null === $this->membershipReader->membershipOf($customerId)) {
            if (self::SCOPE_COMPANY === $scope) {
                $query->add(OrderTableMap::COL_ID, null, Criteria::ISNULL);

                return;
            }

            $query->filterByCustomerId($customerId);

            return;
        }

        $ownOrders = self::SCOPE_COMPANY !== $scope && $this->permissionChecker->can($customerId, CompanyPermission::ORDER_VIEW_OWN);
        $companyId = self::SCOPE_OWN === $scope ? null : $this->permissionChecker->visibleCompanyIdFor($customerId, CompanyPermission::ORDER_VIEW_COMPANY);

        $reachable = null;

        if ($ownOrders) {
            $reachable = $query->getNewCriterion(OrderTableMap::COL_PLACED_BY_CUSTOMER_ID, $customerId, Criteria::EQUAL);
            // An order that records no buyer is the one of its customer.
            $unrecorded = $query->getNewCriterion(OrderTableMap::COL_PLACED_BY_CUSTOMER_ID, null, Criteria::ISNULL);
            $unrecorded->addAnd($query->getNewCriterion(OrderTableMap::COL_CUSTOMER_ID, $customerId, Criteria::EQUAL));
            $reachable->addOr($unrecorded);
        }

        if (null !== $companyId) {
            $ofTheCompany = $query->getNewCriterion(OrderTableMap::COL_COMPANY_ID, $companyId, Criteria::EQUAL);

            if (null === $reachable) {
                $reachable = $ofTheCompany;
            } else {
                $reachable->addOr($ofTheCompany);
            }
        }

        if (null === $reachable) {
            $query->add(OrderTableMap::COL_ID, null, Criteria::ISNULL);

            return;
        }

        $query->addAnd($reachable);
    }
}
