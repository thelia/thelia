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

use Symfony\Component\HttpFoundation\Request;
use Thelia\Form\CustomerLogin;
use Thelia\Core\Security\UserProvider\CustomerUserProvider;

class CustomerUsernamePasswordFormAuthenticator extends UsernamePasswordFormAuthenticator
{
    public function __construct(Request $request, CustomerLogin $loginForm)
    {
        parent::__construct(
            $request,
            $loginForm,
            new CustomerUserProvider(),
            array(
                'username_field_name' => 'email'
            )
        );
    }
}
