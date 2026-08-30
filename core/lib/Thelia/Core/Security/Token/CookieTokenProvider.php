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
    }

    public function createCookie(UserInterface $user, $cookieName, $cookieExpires, ?bool $secure = null): void
    {
        $tokenProvider = new TokenProvider();

        $key = $tokenProvider->encodeKey($user);

        setcookie($cookieName, $key, $this->buildCookieOptions(time() + $cookieExpires, $secure));
    }

    public function clearCookie($cookieName, ?bool $secure = null): void
    {
        setcookie($cookieName, '', $this->buildCookieOptions(time() - 3600, $secure));
    }

    /**
     * Build the options for the remember-me cookie. It holds a long-lived
     * authentication token, so it is hardened against theft: httponly keeps it
     * out of reach of JavaScript (XSS), samesite mitigates CSRF, and secure
     * keeps it off cleartext HTTP. When the caller does not tell us whether the
     * connection is secure, it is deduced from the current request so a plain
     * HTTP local setup does not silently drop the cookie.
     */
    protected function buildCookieOptions(int $expires, ?bool $secure): array
    {
        return [
            'expires' => $expires,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure ?? Request::createFromGlobals()->isSecure(),
        ];
    }
}
