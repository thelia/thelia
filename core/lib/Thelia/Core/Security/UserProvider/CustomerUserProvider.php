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

use Thelia\Model\CustomerQuery;

class CustomerUserProvider implements UserProviderInterface
{
    public function getUser($key)
    {
        $customer = CustomerQuery::create()
            ->filterByEmail($key)
            ->findOne();

        return $customer;
    }
}
