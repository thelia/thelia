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

use Thelia\Core\Event\State\StateCreateEvent;
use Thelia\Core\Event\State\StateDeleteEvent;
use Thelia\Core\Event\State\StateToggleVisibilityEvent;
use Thelia\Core\Event\State\StateUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\StateQuery;

/**
 * Class StateController
 * @package Thelia\Controller\Admin
 * @author Julien Chanséaume <manu@raynaud.io>
 */
class StateController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'state',
            'manual',
            'state_order',
            AdminResources::STATE,
            TheliaEvents::STATE_CREATE,
            TheliaEvents::STATE_UPDATE,
            TheliaEvents::STATE_DELETE,
            TheliaEvents::STATE_TOGGLE_VISIBILITY
        );
    }

    /**
     * Return the creation form for this object
     */
    protected function getCreationForm()
    {
        return $this->createForm(AdminForm::STATE_CREATION);
    }

    /**
     * Return the update form for this object
     */
    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::STATE_MODIFICATION);
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param \Thelia\Model\State $object
     */
    protected function hydrateObjectForm($object)
    {
        $data = array(
            'id' => $object->getId(),
            'locale' => $object->getLocale(),
            'visible' => $object->getVisible() ? true : false,
            'country_id' => $object->getCountryId(),
            'title' => $object->getTitle(),
            'isocode' => $object->getIsocode(),
        );

        return $this->createForm(AdminForm::STATE_MODIFICATION, 'form', $data);
    }

    /**
     * Creates the creation event with the provided form data
     *
     * @param unknown $formData
     */
    protected function getCreationEvent($formData)
    {
        $event = new StateCreateEvent();

        return $this->hydrateEvent($event, $formData);
    }

    /**
     * Creates the update event with the provided form data
     *
     * @param unknown $formData
     */
    protected function getUpdateEvent($formData)
    {
        $event = new StateUpdateEvent($formData['id']);

        return $this->hydrateEvent($event, $formData);
    }

    protected function hydrateEvent($event, $formData)
    {
        $event
            ->setLocale($formData['locale'])
            ->setVisible($formData['visible'])
            ->setCountry($formData['country_id'])
            ->setTitle($formData['title'])
            ->setIsocode($formData['isocode'])
        ;

        return $event;
    }

    /**
     * Creates the delete event with the provided form data
     */
    protected function getDeleteEvent()
    {
        return new StateDeleteEvent($this->getRequest()->get('state_id'));
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param unknown $event
     */
    protected function eventContainsObject($event)
    {
        return $event->hasState();
    }

    /**
     * Get the created object from an event.
     *
     * @param unknown $createEvent
     */
    protected function getObjectFromEvent($event)
    {
        return $event->getState();
    }

    /**
     * Load an existing object from the database
     */
    protected function getExistingObject()
    {
        $state = StateQuery::create()
            ->findPk($this->getRequest()->get('state_id', 0))
        ;

        if (null !== $state) {
            $state->setLocale($this->getCurrentEditionLocale());
        }

        return $state;
    }

    /**
     * Returns the object label form the object event (name, title, etc.)
     *
     * @param \Thelia\Model\State $object
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * Returns the object ID from the object
     *
     * @param \Thelia\Model\State $object
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    /**
     * Render the main list template
     *
     * @param unknown $currentOrder , if any, null otherwise.
     */
    protected function renderListTemplate($currentOrder)
    {
        return $this->render(
            "states",
            array(
                'page' => $this->getRequest()->get('page', 1),
                "page_limit" => $this->getRequest()->get('page_limit', 50),
                'page_order' => $this->getRequest()->get('page_order', 1)
            )
        );
    }

    /**
     * Render the edition template
     */
    protected function renderEditionTemplate()
    {
        return $this->render('state-edit', $this->getEditionArgument());
    }

    protected function getEditionArgument()
    {
        return array(
            'state_id' => $this->getRequest()->get('state_id', 0),
            'page' => $this->getRequest()->get('page', 1),
            'page_order' => $this->getRequest()->get('page_order', 1)
        );
    }

    /**
     * Redirect to the edition template
     */
    protected function redirectToEditionTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.states.update',
            [],
            [
                "state_id" => $this->getRequest()->get('state_id', 0)
            ]
        );
    }

    /**
     * Redirect to the list template
     */
    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute('admin.configuration.states.default');
    }

    /**
     * @return StateToggleVisibilityEvent|void
     */
    protected function createToggleVisibilityEvent()
    {
        return new StateToggleVisibilityEvent($this->getExistingObject());
    }
}
