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

use Thelia\Core\Event\Administrator\AdministratorEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Admin;
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

    public function viewAction()
    {
        // Open the update dialog for the current administrator
        return $this->render('administrators', [ 'show_update_dialog' => true ]);
    }

    public function setEmailAction()
    {
        // Open the update dialog for the current administrator, and display the "set email address" notice.
        return $this->render(
            'administrators',
            [
                'show_update_dialog' => true,
                'show_email_change_notice' => true
            ]
        );
    }

    protected function getCreationForm()
    {
        return $this->createForm(AdminForm::ADMINISTRATOR_CREATION);
    }

    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::ADMINISTRATOR_MODIFICATION);
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
        $event->setEmail($formData['email']);

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
        $event->setEmail($formData['email']);

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

    /**
     * @param Admin $object
     *
     * @return \Thelia\Form\BaseForm
     */
    protected function hydrateObjectForm($object)
    {
        $data = array(
            'id'                => $object->getId(),
            'firstname'         => $object->getFirstname(),
            'lastname'          => $object->getLastname(),
            'login'             => $object->getLogin(),
            'profile'           => $object->getProfileId(),
            'locale'            => $object->getLocale(),
            'email'             => $object->getEmail()
        );

        // Setup the object form
        return $this->createForm(AdminForm::ADMINISTRATOR_MODIFICATION, "form", $data);
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

    /**
     * @param Admin $object
     * @return string
     */
    protected function getObjectLabel($object)
    {
        return $object->getLogin();
    }

    /**
     * @param Admin $object
     * @return int
     */
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
