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

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\FrontOperation;
use Thelia\Api\Resource\CompanyCustomer;
use Thelia\Api\Resource\CompanyRole;
use Thelia\Api\Security\AccountCaller;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\Service\CompanyPermissionChecker;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyRoleQuery;
use Thelia\Model\Map\CompanyCustomerTableMap;
use Thelia\Model\Map\CompanyRoleTableMap;

/**
 * Scopes the buyers and the roles of the account endpoints to the company of a caller who manages
 * the buyers: another company's buyers read as absent, and so does everything to a caller who does
 * not hold the right. The roles are the ones every company shares and those of the caller's own.
 *
 * The admin endpoints are left alone: they sit behind the right of the back office.
 */
final readonly class CompanyFrontScopeExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private AccountCaller $caller,
        private CompanyPermissionChecker $permissionChecker,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scope($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scope($query, $resourceClass, $operation);
    }

    /**
     * Whether this extension, and not the generic customer filter, bounds the resource: a buyer is
     * reached through the company of the caller, not through an owner.
     */
    public static function handles(string $resourceClass, ?Operation $operation): bool
    {
        return \in_array($resourceClass, [CompanyCustomer::class, CompanyRole::class], true) && FrontOperation::isFront($operation);
    }

    private function scope(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (!self::handles($resourceClass, $operation)) {
            return;
        }

        $customer = $this->caller->customer();
        $companyId = null === $customer ? null : $this->permissionChecker->visibleCompanyIdFor($customer, CompanyPermission::BUYER_MANAGE);

        if ($query instanceof CompanyCustomerQuery) {
            if (null === $companyId) {
                // company_customer.id is the primary key: this criterion matches no row.
                $query->add(CompanyCustomerTableMap::COL_ID, null, Criteria::ISNULL);

                return;
            }

            $query->filterByCompanyId($companyId);
        } elseif ($query instanceof CompanyRoleQuery) {
            if (null === $companyId) {
                $query->add(CompanyRoleTableMap::COL_ID, null, Criteria::ISNULL);

                return;
            }

            $query->condition('shared', CompanyRoleTableMap::COL_COMPANY_ID.' IS NULL')
                ->condition('own', CompanyRoleTableMap::COL_COMPANY_ID.' = ?', $companyId, \PDO::PARAM_INT)
                ->where(['shared', 'own'], Criteria::LOGICAL_OR);
        }
    }
}
