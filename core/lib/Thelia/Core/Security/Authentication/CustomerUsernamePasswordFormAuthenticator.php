<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Security\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\UserProvider\CustomerUserProvider;
use Thelia\Form\CustomerLogin;

class CustomerUsernamePasswordFormAuthenticator extends UsernamePasswordFormAuthenticator
{
    public function __construct(Request $request, CustomerLogin $loginForm)
    {
        parent::__construct(
            $request,
            $loginForm,
            new CustomerUserProvider(),
            [
                'username_field_name' => 'email',
            ]
        );
    }
}
