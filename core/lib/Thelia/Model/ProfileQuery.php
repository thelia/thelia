<?php

namespace Thelia\Model;

use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Base\ProfileQuery as BaseProfileQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'profile' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class ProfileQuery extends BaseProfileQuery
{
    public static function getProfileList()
    {
        $profileList = [
            AdminResources::SUPERADMINISTRATOR => 0
        ];
        foreach (ProfileQuery::create()->find() as $profile) {
            $profileList[$profile->getCode()] = $profile->getId();
        }

        return $profileList;
    }
}
// ProfileQuery
