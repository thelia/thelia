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

namespace Thelia\Core\Security\Token;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\User\UserInterface;

class CookieTokenProvider
{
    public function getKeyFromCookie(Request $request, $cookieName)
    {
        if ($request->cookies->has($cookieName)) {
            // Create the authenticator
            return $request->cookies->get($cookieName);
        }

        return null;
    }

    public function createCookie(UserInterface $user, $cookieName, $cookieExpires): void
    {
        $tokenProvider = new TokenProvider();

        $key = $tokenProvider->encodeKey($user);

        setcookie($cookieName, $key, ['expires' => time() + $cookieExpires, 'path' => '/']);
    }

    public function clearCookie($cookieName): void
    {
        setcookie($cookieName, '', ['expires' => time() - 3600, 'path' => '/']);
    }
}
