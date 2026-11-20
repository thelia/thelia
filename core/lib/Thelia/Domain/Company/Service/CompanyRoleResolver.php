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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\CompanyRole;
use Thelia\Model\CompanyRoleQuery;
use Thelia\Model\Map\CompanyRoleTableMap;

/**
 * The roles a company may give: the ones every company shares, then its own.
 *
 * A role is named by its code. A role of a company never shadows a shared one of the same code,
 * so that the code of the administrator role means one thing everywhere.
 */
final readonly class CompanyRoleResolver
{
    public function resolve(int $companyId, string $roleCode, ?ConnectionInterface $connection = null): ?CompanyRole
    {
        return CompanyRoleQuery::create()
            ->filterByCode($roleCode)
            ->condition('shared', CompanyRoleTableMap::COL_COMPANY_ID.' IS NULL')
            ->condition('own', CompanyRoleTableMap::COL_COMPANY_ID.' = ?', $companyId, \PDO::PARAM_INT)
            ->where(['shared', 'own'], Criteria::LOGICAL_OR)
            ->orderByCompanyId()
            ->findOne($connection);
    }

    public function isAdministrator(CompanyRole $role): bool
    {
        return CompanyRoleCode::ADMINISTRATOR === $role->getCode() && null === $role->getCompanyId();
    }
}
