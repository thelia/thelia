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
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\ProfileModuleQuery;
use Thelia\Model\ProfileResourceQuery;

trait UserPermissionsTrait
{
    
    //abstract public function getAdminProfiles($criteria = null, ConnectionInterface $con = null);

    public function getPermissions()
    {
        $profilesList = $this->getAdminProfiles();
        $data = $profilesList->getData();
        if(empty($data))
        {
            return AdminResources::SUPERADMINISTRATOR;
        }

        $userPermissions = array();

        foreach($data as $aProfile)
        {
            $profileId = $aProfile->getProfileId();

            if (null === $profileId || 0 === $profileId)
            {
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


            foreach ($userResourcePermissionsQuery as $userResourcePermission)
            {
                $code = $userResourcePermission->getVirtualColumn('code');
                if(isset($userPermissions[$code]))
                {
                    $accessValue = $userPermissions[$code]->getAccessValue();
                    $accessValue += $userResourcePermission->getAccess();
                    $userPermissions[$code] = new AccessManager($accessValue);
                }
                else
                {
                    $userPermissions[$code] = new AccessManager($userResourcePermission->getAccess());
                }
            }
            foreach ($userModulePermissionsQuery as $userModulePermission)
            {
                $userPermissions['module'][strtolower($userModulePermission->getVirtualColumn('code'))] = new AccessManager($userModulePermission->getAccess());
            }
        }
        return $userPermissions;
    }
}
