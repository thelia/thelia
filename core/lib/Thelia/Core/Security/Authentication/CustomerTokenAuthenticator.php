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

namespace Thelia\Core\Security\Authentication;

use Thelia\Core\Security\UserProvider\CustomerTokenUserProvider;

class CustomerTokenAuthenticator extends TokenAuthenticator
{
    public function __construct($key)
    {
        parent::__construct(
            $key,
            new CustomerTokenUserProvider()
        );
    }
}
