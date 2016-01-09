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

namespace Thelia\Core\Security\UserProvider;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\AdminQuery;

class AdminUserProvider implements UserProviderInterface
{
    public function getUser($key)
    {
        // Try with login name
        $admin = AdminQuery::create()
            ->filterByLogin($key, Criteria::EQUAL)
            ->findOne();

        // Try with email address
        if (null == $admin && ! empty($key)) {
            $admin = AdminQuery::create()
                ->filterByEmail($key, Criteria::EQUAL)
                ->findOne();
        }

        return $admin;
    }
}
