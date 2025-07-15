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

namespace Thelia\Core\Security\Authentication;

use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Validator\Exception\ValidatorException;
use Thelia\Core\Security\Exception\CustomerNotConfirmedException;
use Thelia\Core\Security\Exception\WrongPasswordException;
use Thelia\Form\BaseForm;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;

class UsernamePasswordFormAuthenticator implements AuthenticatorInterface
{
    protected Form $loginForm;
    protected $options;

    public function __construct(protected Request $request, protected BaseForm $baseLoginForm, protected UserProviderInterface $userProvider, array $options = [])
    {
        $this->loginForm = $this->baseLoginForm->getForm();

        $defaults = [
            'required_method' => 'POST',
            'username_field_name' => 'username',
            'password_field_name' => 'password',
        ];

        $this->options = array_merge($defaults, $options);
    }

    /**
     * @return string the username value
     */
    public function getUsername(): string
    {
        return $this->loginForm->get($this->options['username_field_name'])->getData();
    }

    /**
     * @see \Thelia\Core\Security\Authentication\AuthenticatorInterface::getAuthentifiedUser()
     */
    public function getAuthentifiedUser()
    {
        if ($this->request->isMethod($this->options['required_method'])) {
            if (!$this->loginForm->isValid()) {
                throw new ValidatorException('Form is not valid.');
            }

            // Retreive user
            $username = $this->getUsername();
            $password = $this->loginForm->get($this->options['password_field_name'])->getData();

            $user = $this->userProvider->loadUserByIdentifier($username);

            // Check user password
            $authOk = true === $user->checkPassword($password);

            if (!$authOk) {
                throw new WrongPasswordException(\sprintf("Wrong password for user '%s'.", $username));
            }

            // Customer email confirmation feature is available since Thelia 2.3.4
            if (ConfigQuery::isCustomerEmailConfirmationEnable() && $user instanceof Customer && (null !== $user->getConfirmationToken() && !$user->getEnable())) {
                throw (new CustomerNotConfirmedException())->setUser($user);
            }

            return $user;
        }

        throw new \RuntimeException('Invalid method.');
    }
}
