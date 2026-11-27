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

namespace Thelia\Tests\Support\BackOffice;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\Admin;

/**
 * Signs an administrator in through the login form of the back office, for a web test case.
 */
trait AdministratorSignIn
{
    private const string ADMINISTRATOR_PASSWORD = 'correct horse battery';

    private function administrator(): Admin
    {
        return $this->createFixtureFactory()->admin(['password' => self::ADMINISTRATOR_PASSWORD]);
    }

    private function signIn(Admin $admin): void
    {
        $requestStack = $this->getService(RequestStack::class);

        while (($request = $requestStack->getCurrentRequest()) instanceof Request && !$request->hasSession()) {
            $requestStack->pop();
        }

        $login = $this->client->request('GET', '/admin/login');

        $this->client->request('POST', '/admin/checklogin', [
            'thelia_admin_login' => [
                'username' => $admin->getLogin(),
                'password' => self::ADMINISTRATOR_PASSWORD,
                'success_url' => '/admin',
                '_token' => (string) $login->filter('input[name="thelia_admin_login[_token]"]')->attr('value'),
            ],
        ]);
    }
}
