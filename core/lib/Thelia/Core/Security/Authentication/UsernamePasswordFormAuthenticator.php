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


use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\UserProvider\UserProviderInterface;

use Thelia\Core\Security\Exception\WrongPasswordException;
use Thelia\Core\Security\Exception\UsernameNotFoundException;
use Symfony\Component\Validator\Exception\ValidatorException;
use Thelia\Form\BaseForm;

class UsernamePasswordFormAuthenticator implements AuthenticatorInterface
{
    protected $request;
    protected $loginForm;
    protected $userProvider;
    protected $options;

    protected $baseLoginForm;

    public function __construct(Request $request, BaseForm $loginForm, UserProviderInterface $userProvider, array $options = array())
    {
        $this->request = $request;
        $this->baseLoginForm = $loginForm;
        $this->loginForm = $this->baseLoginForm->getForm();
        $this->userProvider = $userProvider;

        $defaults = array(
            'required_method' => 'POST',
            'username_field_name' => 'username',
            'password_field_name' => 'password'
        );

        $this->options = array_merge($defaults, $options);
    }

    /**
     * @return string the username value
     */
    public function getUsername()
    {
        return $this->loginForm->get($this->options['username_field_name'])->getData();
    }

    /**
     * @see \Thelia\Core\Security\Authentication\AuthenticatorInterface::getAuthentifiedUser()
     */
    public function getAuthentifiedUser()
    {
        if ($this->request->isMethod($this->options['required_method'])) {

            if (! $this->loginForm->isValid()) throw new ValidatorException("Form is not valid.");

            // Retreive user
            $username = $this->getUsername();
            $password = $this->loginForm->get($this->options['password_field_name'])->getData();

            $user = $this->userProvider->getUser($username);

            if ($user === null) throw new UsernameNotFoundException(sprintf("Username '%s' was not found.", $username));

            // Check user password
            $authOk = $user->checkPassword($password) === true;

            if ($authOk !== true) throw new WrongPasswordException(sprintf("Wrong password for user '%s'.", $username));
            return $user;
        }

        throw new \RuntimeException("Invalid method.");
    }
}
