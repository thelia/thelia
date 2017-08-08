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

namespace Thelia\Core\Security\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\Exception\CustomerNotConfirmedException;
use Thelia\Core\Security\UserProvider\UserProviderInterface;
use Thelia\Core\Security\Exception\WrongPasswordException;
use Thelia\Core\Security\Exception\UsernameNotFoundException;
use Symfony\Component\Validator\Exception\ValidatorException;
use Thelia\Form\BaseForm;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;

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
            if (! $this->loginForm->isValid()) {
                throw new ValidatorException("Form is not valid.");
            }

            // Retreive user
            $username = $this->getUsername();
            $password = $this->loginForm->get($this->options['password_field_name'])->getData();

            $user = $this->userProvider->getUser($username);

            if ($user === null) {
                throw new UsernameNotFoundException(sprintf("Username '%s' was not found.", $username));
            }

            // Check user password
            $authOk = $user->checkPassword($password) === true;

            if ($authOk !== true) {
                throw new WrongPasswordException(sprintf("Wrong password for user '%s'.", $username));
            }

            if (ConfigQuery::isCustomerEmailConfirmationEnable() && $user instanceof Customer) {
                // Customer email confirmation feature is available since Thelia 2.3.4
                if ($user->getConfirmationToken() !== null && ! $user->getEnable()) {
                    throw (new CustomerNotConfirmedException())->setUser($user);
                }
            }

            return $user;
        }

        throw new \RuntimeException("Invalid method.");
    }
}
