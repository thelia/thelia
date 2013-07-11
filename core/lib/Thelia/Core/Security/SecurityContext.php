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

namespace Thelia\Core\Security;

use Thelia\Core\Security\Authentication\AuthenticationProviderInterface;
use Thelia\Core\Security\Exception\AuthenticationTokenNotFoundException;

/**
 * A simple security manager, in charge of authenticating users using various authentication systems.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class SecurityContext {
/*
    protected $authProvider;

    public function __construct(AuthenticationProviderInterface $authProvider) {
        $this->authProvider = $authProvider;
    }
*/
    /**
    * Checks if the current token is authenticated
    *
    * @throws AuthenticationCredentialsNotFoundException when the security context has no authentication token.
    *
    * @return Boolean
    * @throws AuthenticationTokenNotFoundException if no thoken was found in context
    */
    final public function isGranted($roles, $permissions)
    {
        if (null === $this->token) {
            throw new AuthenticationTokenNotFoundException('The security context contains no authentication token.');
        }

        if (!$this->token->isAuthenticated()) {
            $this->token = $this->authProvider->authenticate($this->token);
        }

        if ($this->token->isAuthenticated()) {
        	// Check user roles and permissions
        }

        return false;
    }

    /**
    * Gets the currently authenticated token.
    *
    * @return TokenInterface|null A TokenInterface instance or null if no authentication information is available
    */
    public function getToken()
    {
        return $this->token;
    }

    /**
    * Sets the  token.
    *
    * @param TokenInterface $token A TokenInterface token, or null if no further authentication information should be stored
    */
    public function setToken(TokenInterface $token = null)
    {
        $this->token = $token;
    }
}