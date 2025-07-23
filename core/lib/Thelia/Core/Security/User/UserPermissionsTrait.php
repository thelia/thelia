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

namespace Thelia\Core\Security\User;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\ProfileModuleQuery;
use Thelia\Model\ProfileResourceQuery;

trait UserPermissionsTrait
{
    abstract public function getProfileId();

    public function getPermissions(): string|array
    {
        $profileId = $this->getProfileId();

        if (null === $profileId || 0 === $profileId) {
            return AdminResources::SUPERADMINISTRATOR;
        }

        $userResourcePermissionsQuery = ProfileResourceQuery::create()
            ->joinResource('resource', Criteria::LEFT_JOIN)
            ->withColumn('resource.code', 'code')
            ->filterByProfileId($profileId)
            ->find();

        $userModulePermissionsQuery = ProfileModuleQuery::create()
            ->joinModule('module', Criteria::LEFT_JOIN)
            ->withColumn('module.code', 'code')
            ->filterByProfileId($profileId)
            ->find();

        $userPermissions = [];

        foreach ($userResourcePermissionsQuery as $userResourcePermission) {
            $userPermissions[$userResourcePermission->getVirtualColumn('code')] = new AccessManager($userResourcePermission->getAccess());
        }

        foreach ($userModulePermissionsQuery as $userModulePermission) {
            $userPermissions['module'][strtolower($userModulePermission->getVirtualColumn('code'))] = new AccessManager($userModulePermission->getAccess());
        }

        return $userPermissions;
    }
}
