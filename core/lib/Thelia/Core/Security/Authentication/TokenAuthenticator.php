<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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

namespace Thelia\Core\Security\Authentication;

use Thelia\Core\Security\UserProvider\TokenUserProvider;
use Thelia\Core\Security\Exception\TokenAuthenticationException;

class TokenAuthenticator implements AuthenticatorInterface
{
    protected $key;

    protected $userProvider;

    public function __construct($key, TokenUserProvider $userProvider)
    {
        $this->key = $key;

        $this->userProvider = $userProvider;
    }

    /**
     * @see \Thelia\Core\Security\Authentication\AuthenticatorInterface::getAuthentifiedUser()
     */
    public function getAuthentifiedUser()
    {
        $keyData = $this->userProvider->decodeKey($this->key);

        $user = $this->userProvider->getUser($keyData);

        if ($user === null) throw new TokenAuthenticationException("No user matches the provided token");
        return $user;
    }
}
