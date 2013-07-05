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

use Thelia\Core\Security\Authentication\AuthenticationProviderInterface;
use Thelia\Core\Security\Encoder\PasswordEncoderInterface;
use Thelia\Core\Security\User\UserProviderInterface;
use Thelia\Security\Token\TokenInterface;
use Thelia\Core\Security\Exception\IncorrectPasswordException;
use Thelia\Core\Security\Token\UsernamePasswordToken;

class UsernamePasswordAuthenticator implements AuthenticationProviderInterface {

    protected $userProvider;
    protected $encoder;

    private $token;

    public function __construct(UserProviderInterface $userProvider, PasswordEncoderInterface $encoder) {
        $this->userProvider = $userProvider;
        $this->encoder = $encoder;
    }

    public function supportsToken(TokenInterface $token) {

    	return $token instanceof UsernamePasswordToken;
    }

    public function authenticate($token) {

        if (!$this->supports($token)) {
        	return null;
        }

        // Retreive user
        $user = $this->userProvider->getUser($this->token->getUsername());

        // Check password
        $authOk = $this->encoder->isEqual($password, $user->getPassword(), $user->getAlgo(), $user->getSalt()) === true;

        $authenticatedToken = new UsernamePasswordToken($user, $token->getCredentials(), $authOk);

        return $authenticatedToken;
    }
}