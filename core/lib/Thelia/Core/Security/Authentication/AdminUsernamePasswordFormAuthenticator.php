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

namespace Thelia\Core\Security\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\UserProvider\AdminUserProvider;
use Thelia\Form\AdminLogin;

class AdminUsernamePasswordFormAuthenticator extends UsernamePasswordFormAuthenticator
{
    public function __construct(Request $request, AdminLogin $loginForm)
    {
        parent::__construct(
            $request,
            $loginForm,
            new AdminUserProvider()
        );
    }
}
