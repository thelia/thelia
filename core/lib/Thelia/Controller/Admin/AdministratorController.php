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
        $event->setLocale($formData['locale']);

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
        $event->setLocale($formData['locale']);

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
            'locale'            => $object->getLocale()
        );

        // Setup the object form
        return new AdministratorModificationForm($this->getRequest(), "form", $data);
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasAdministrator() ? $event->getAdministrator() : null;
    }

    protected function getExistingObject()
    {
        return AdminQuery::create()
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
        return $this->redirectToListTemplate();
    }

    protected function performAdditionalCreateAction($updateEvent)
    {
        // We always return to the feature edition form
        return $this->redirectToListTemplate();
    }

    protected function performAdditionalUpdateAction($updateEvent)
    {
        // We always return to the feature edition form
        return $this->redirectToListTemplate();
    }

    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute(
            "admin.configuration.administrators.view"
        );
    }
}
