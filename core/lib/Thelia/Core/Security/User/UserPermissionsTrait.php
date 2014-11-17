<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Security\User;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\ProfileModuleQuery;
use Thelia\Model\ProfileResourceQuery;

trait UserPermissionsTrait
{
    abstract public function getProfileId();

    public function getPermissions()
    {
        $profileId = $this->getProfileId();

        if (null === $profileId || 0 === $profileId) {
            return AdminResources::SUPERADMINISTRATOR;
        }

        $userResourcePermissionsQuery = ProfileResourceQuery::create()
            ->joinResource("resource", Criteria::LEFT_JOIN)
            ->withColumn('resource.code', 'code')
            ->filterByProfileId($profileId)
            ->find();

        $userModulePermissionsQuery = ProfileModuleQuery::create()
            ->joinModule("module", Criteria::LEFT_JOIN)
            ->withColumn('module.code', 'code')
            ->filterByProfileId($profileId)
            ->find();

        $userPermissions = array();
        foreach ($userResourcePermissionsQuery as $userResourcePermission) {
            $userPermissions[$userResourcePermission->getVirtualColumn('code')] = new AccessManager($userResourcePermission->getAccess());
        }
        foreach ($userModulePermissionsQuery as $userModulePermission) {
            $userPermissions['module'][strtolower($userModulePermission->getVirtualColumn('code'))] = new AccessManager($userModulePermission->getAccess());
        }

        return $userPermissions;
    }
}
