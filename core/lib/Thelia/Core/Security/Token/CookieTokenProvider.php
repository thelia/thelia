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

namespace Thelia\Core\Security\Token;

use Thelia\Core\HttpFoundation\Request;
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

    public function createCookie(UserInterface $user, $cookieName, $cookieExpires)
    {
        $tokenProvider = new TokenProvider();

        $key = $tokenProvider->encodeKey($user);

        setcookie($cookieName, $key, time() + $cookieExpires, '/');
    }

    public function clearCookie($cookieName)
    {
        setcookie($cookieName, '', time() - 3600, '/');
    }
}
