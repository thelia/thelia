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

namespace Thelia\Controller\Admin;

use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Event\Administrator\AdministratorEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\AdministratorCreationForm;
use Thelia\Form\AdministratorModificationForm;
use Thelia\Model\AdminQuery;

class AdministratorController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'administrator',
            'manual',
            'order',

            AdminResources::ADMINISTRATOR,

            TheliaEvents::ADMINISTRATOR_CREATE,
            TheliaEvents::ADMINISTRATOR_UPDATE,
            TheliaEvents::ADMINISTRATOR_DELETE
        );
    }

    protected function getCreationForm()
    {
        return new AdministratorCreationForm($this->getRequest());
    }

    protected function getUpdateForm()
    {
        return new AdministratorModificationForm($this->getRequest());
    }

    protected function getCreationEvent($formData)
    {
        $event = new AdministratorEvent();

        $event->setLogin($formData['login']);
        $event->setFirstname($formData['firstname']);
        $event->setLastname($formData['lastname']);
        $event->setPassword($formData['password']);
        $event->setProfile($formData['profile'] ? : null);

        return $event;
    }

    protected function getUpdateEvent($formData)
    {
        $event = new AdministratorEvent();

        $event->setId($formData['id']);
        $event->setLogin($formData['login']);
        $event->setFirstname($formData['firstname']);
        $event->setLastname($formData['lastname']);
        $event->setPassword($formData['password']);
        $event->setProfile($formData['profile'] ? : null);

        return $event;
    }

    protected function getDeleteEvent()
    {
        $event = new AdministratorEvent();

        $event->setId(
            $this->getRequest()->get('administrator_id', 0)
        );

        return $event;
    }

    protected function eventContainsObject($event)
    {
        return $event->hasAdministrator();
    }

    protected function hydrateObjectForm($object)
    {
        $data = array(
            'id'                => $object->getId(),
            'firstname'         => $object->getFirstname(),
            'lastname'          => $object->getLastname(),
            'login'             => $object->getLogin(),
            'profile'           => $object->getProfileId(),
        );

        // Setup the object form
        return new AdministratorModificationForm($this->getRequest(), "form", $data);
    }

    protected function hydrateResourceUpdateForm($object)
    {
        $data = array(
            'id'           => $object->getId(),
        );

        // Setup the object form
        return new AdministratorUpdateResourceAccessForm($this->getRequest(), "form", $data);
    }

    protected function hydrateModuleUpdateForm($object)
    {
        $data = array(
            'id'           => $object->getId(),
        );

        // Setup the object form
        return new AdministratorUpdateModuleAccessForm($this->getRequest(), "form", $data);
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasAdministrator() ? $event->getAdministrator() : null;
    }

    protected function getExistingObject()
    {
        return AdminQuery::create()
            ->joinWithI18n($this->getCurrentEditionLocale())
            ->findOneById($this->getRequest()->get('administrator_id'));
    }

    protected function getObjectLabel($object)
    {
        return $object->getLogin();
    }

    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function renderListTemplate($currentOrder)
    {
        // We always return to the feature edition form
        return $this->render(
            'administrators',
            array()
        );
    }

    protected function renderEditionTemplate()
    {
        // We always return to the feature edition form
        return $this->render('administrators');
    }

    protected function redirectToEditionTemplate()
    {
        // We always return to the feature edition form
        $this->redirectToListTemplate();
    }

    protected function performAdditionalCreateAction($updateEvent)
    {
        // We always return to the feature edition form
        $this->redirectToListTemplate();
    }

    protected function performAdditionalUpdateAction($updateEvent)
    {
        // We always return to the feature edition form
        $this->redirectToListTemplate();
    }

    protected function redirectToListTemplate()
    {
        $this->redirectToRoute(
            "admin.configuration.administrators.view"
        );
    }
}
