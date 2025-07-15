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

namespace Thelia\Model;

use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Base\ProfileQuery as BaseProfileQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'profile' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class ProfileQuery extends BaseProfileQuery
{
    public static function getProfileList()
    {
        $profileList = [
            AdminResources::SUPERADMINISTRATOR => 0,
        ];

        foreach (self::create()->find() as $profile) {
            $profileList[$profile->getCode()] = $profile->getId();
        }

        return $profileList;
    }
}

// ProfileQuery
