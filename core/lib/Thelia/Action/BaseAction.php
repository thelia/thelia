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
namespace Thelia\Action;

use Thelia\Form\BaseForm;
use Thelia\Action\Exception\FormValidationException;
use Thelia\Core\Event\ActionEvent;
use Symfony\Component\Form\Form;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BaseAction
{
    /**
     * @var The container
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Return the event dispatcher,
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    public function getDispatcher()
    {
        return $this->container->get('event_dispatcher');
    }

    /**
     * Check current user authorisations.
     *
     * @param mixed $roles a single role or an array of roles.
     * @param mixed $permissions a single permission or an array of permissions.
     *
     * @throws AuthenticationException if permissions are not granted to the current user.
     */
    protected function checkAuth($roles, $permissions) {

        if (! $this->getSecurityContext()->isGranted(
                is_array($roles) ? $roles : array($roles),
                is_array($permissions) ? $permissions : array($permissions)) ) {

            Tlog::getInstance()->addAlert("Authorization roles:", $roles, " permissions:", $permissions, " refused.");

            throw new AuthorizationException("Sorry, you're not allowed to perform this action");
        }
    }

    /**
     * Return the security context
     *
     * @return Thelia\Core\Security\SecurityContext
     */
    protected function getSecurityContext()
    {
        return $this->container->get('thelia.securityContext');
    }
}