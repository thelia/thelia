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

namespace Thelia\Core\Security\UserProvider;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Model\AdminQuery;

class AdminTokenUserProvider extends TokenUserProvider
{
    public function getUser(array $key): UserInterface
    {
        if (null === $admin = AdminQuery::create()
            ->filterByLogin($key['username'], Criteria::EQUAL)
            ->filterByRememberMeSerial($key['serial'], Criteria::EQUAL)
            ->filterByRememberMeToken($key['token'], Criteria::EQUAL)
            ->findOne()) {
            throw new \InvalidArgumentException('No admin found with this token (maybe try to delete your cookies)');
        }

        return $admin;
    }
}
