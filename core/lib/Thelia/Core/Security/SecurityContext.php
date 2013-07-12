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
use Thelia\Core\Security\Token\TokenInterface;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Core\HttpFoundation\Request;

/**
 * A simple security manager, in charge of checking user
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class SecurityContext {

	const CONTEXT_FRONT_OFFICE = 'front';
	const CONTEXT_BACK_OFFICE  = 'admin';

	private $request;
	private $context;

	public function __construct(Request $request) {

		$this->request = $request;

		$this->context = null;
	}

	public function setContext($context) {
		if ($context !== self::CONTEXT_FRONT_OFFICE && $context !== self::CONTEXT_BACK_OFFICE)  {
			throw new \InvalidArgumentException(sprintf("Invalid or empty context identifier '%s'", $context));
		}

		$this->context = $context;
	}

	public function getContext($exception_if_context_undefined = false) {
		if (null === $this->context && $exception_if_context_undefined === true)
			throw new \LogicException("No context defined. Please use setContext() first.");

		return $this->context;
	}

	private function getSession() {
		$session = $this->request->getSession();

		if ($session === null)
			throw new \LogicException("No session found.");

		return $session;
	}

    /**
    * Gets the currently authenticated user in  the current context, or null if none is defined
    *
    * @return UserInterface|null A UserInterface instance or null if no user is available
    */
	public function getUser() {
		$context = $this->getContext(true);

		if ($context === self::CONTEXT_FRONT_OFFICE)
			$user = $this->getSession()->getCustomerUser();
		else if ($context == self::CONTEXT_BACK_OFFICE)
			$user = $this->getSession()->getAdminUser();
		else
			$user = null;

		return $user;
	}

	final public function isAuthenticated()
	{
		if (null !== $this->getUser()) {
			return true;
		}

		return false;
	}

    /**
    * Checks if the current user is allowed
    *
    * @return Boolean
    */
    final public function isGranted($roles, $permissions)
    {
        if ($this->isAuthenticated() === true) {

       		echo "TODO: check roles and permissions !";

        	// TODO : check roles and permissions
        	return true;
        }

        return false;
    }

    /**
    * Sets the authenticated user.
    *
    * @param UserInterface $user A UserInterface, or null if no further user should be stored
    */
    public function setUser(UserInterface $user)
    {
		$context = $this->getContext(true);

		$user->eraseCredentials();

		if ($context === self::CONTEXT_FRONT_OFFICE)
			$this->getSession()->setCustomerUser($user);
		else if ($context == self::CONTEXT_BACK_OFFICE)
			$this->getSession()->setAdminUser($user);
    }

    /**
     * Clear the user from the security context
     */
    public function clear() {
		$context = $this->getContext(true);

		if ($context === self::CONTEXT_FRONT_OFFICE)
			$this->getSession()->clearCustomerUser();
		else if ($context == self::CONTEXT_BACK_OFFICE)
			$this->getSession()->clearAdminUser();
    }
}