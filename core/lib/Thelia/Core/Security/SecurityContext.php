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

use Thelia\Core\Security\User\UserInterface;
use Thelia\Core\HttpFoundation\Request;

/**
 * A simple security manager, in charge of checking user
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class SecurityContext
{
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    private function getSession()
    {
        $session = $this->request->getSession();

        if ($session === null)
            throw new \LogicException("No session found.");

        return $session;
    }

    /**
    * Gets the currently authenticated user in  the admin, or null if none is defined
    *
    * @return UserInterface|null A UserInterface instance or null if no user is available
    */
    public function getAdminUser()
    {
        return $this->getSession()->getAdminUser();
    }

    /**
     * Gets the currently authenticated customer, or null if none is defined
     *
     * @return UserInterface|null A UserInterface instance or null if no user is available
     */
    public function getCustomerUser()
    {
        return $this->getSession()->getCustomerUser();
    }

    /**
     * Check if a user has at least one of the required roles
     *
     * @param UserInterface $user the user
     * @param array $roles the roles
     * @return boolean true if the user has the required role, false otherwise
     */
    final public function hasRequiredRole($user, array $roles) {

        if ($user != null) {
            // Check if user's roles matches required roles
            $userRoles = $user->getRoles();

            $roleFound = false;

            foreach ($userRoles as $role) {
                if (in_array($role, $roles)) {
                    $roleFound = true;

                    return true;
                }
            }
        }

        return false;
    }

    /**
    * Checks if the current user is allowed
    *
    * @return Boolean
    */
    final public function isGranted(array $roles, array $permissions)
    {
        // Find a user which matches the required roles.
        $user = $this->getCustomerUser();

        if (! $this->hasRequiredRole($user, $roles)) {
            $user = $this->getAdminUser();

            if (! $this->hasRequiredRole($user, $roles)) {
                $user = null;
            }
        }

        if ($user != null) {

            if (empty($permissions)) {
               return true;
            }

            // Get permissions from profile
            // $userPermissions = $user->getPermissions(); FIXME

            // TODO: Finalize permissions system !;

            $userPermissions = array('*'); // FIXME !

            $permissionsFound = true;

            // User have all permissions ?
            if (in_array('*', $userPermissions))
               return true;

            // Check that user's permissions matches required permissions
            foreach ($permissions as $permission) {
               if (! in_array($permission, $userPermissions)) {
                   $permissionsFound = false;

                   break;
               }
            }

            return $permissionsFound;
        }

        return false;
    }

    /**
    * Sets the authenticated admin user.
    *
    * @param UserInterface $user A UserInterface, or null if no further user should be stored
    */
    public function setAdminUser(UserInterface $user)
    {
        $user->eraseCredentials();

        $this->getSession()->setAdminUser($user);
    }

    /**
     * Sets the authenticated customer user.
     *
     * @param UserInterface $user A UserInterface, or null if no further user should be stored
     */
    public function setCustomerUser(UserInterface $user)
    {
        $user->eraseCredentials();

        $this->getSession()->setCustomerUser($user);
    }

    /**
     * Clear the customer from the security context
     */
    public function clearCustomerUser()
    {
        $this->getSession()->clearCustomerUser();
    }

    /**
     * Clear the admin from the security context
     */
    public function clearAdminUser()
    {
       $this->getSession()->clearAdminUser();
    }
}