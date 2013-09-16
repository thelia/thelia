<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
