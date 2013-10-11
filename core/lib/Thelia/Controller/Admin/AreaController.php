<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
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
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Controller\Admin;
use Thelia\Core\Event\Area\AreaCreateEvent;
use Thelia\Core\Event\Area\AreaUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\Area\AreaCreateForm;

/**
 * Class AreaController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AreaController extends AbstractCrudController
{
/*    public function indexAction()
    {
        if (null !== $response = $this->checkAuth("admin.shipping-configuration.view")) return $response;
        return $this->render("shipping-configuration", array("display_shipping_configuration" => 20));
    }

    public function updateAction($shipping_configuration_id)
    {
        return $this->render("shipping-configuration-edit", array(
            "shipping_configuration_id" => $shipping_configuration_id
        ));
    }*/

    public function __construct()
    {
        parent::__construct(
            'area',
            null,
            null,

            'admin.area.default',
            'admin.area.create',
            'admin.area.update',
            'admin.area.delete',

            TheliaEvents::AREA_CREATE,
            TheliaEvents::AREA_UPDATE,
            TheliaEvents::AREA_DELETE
        );
    }

    /**
     * Return the creation form for this object
     */
    protected function getCreationForm()
    {
        return new AreaCreateForm($this->getRequest());
    }

    /**
     * Return the update form for this object
     */
    protected function getUpdateForm()
    {
        return new AreaCreateForm($this->getRequest());
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param unknown $object
     */
    protected function hydrateObjectForm($object)
    {
        // TODO: Implement hydrateObjectForm() method.
    }

    /**
     * Creates the creation event with the provided form data
     *
     * @param unknown $formData
     *
     * @return \Thelia\Core\Event\Area\AreaCreateEvent
     */
    protected function getCreationEvent($formData)
    {
        $event = new AreaCreateEvent();

        return $this->hydrateEvent($event, $formData);
    }

    /**
     * Creates the update event with the provided form data
     *
     * @param unknown $formData
     */
    protected function getUpdateEvent($formData)
    {
        $event = new AreaUpdateEvent();

        return $this->hydrateEvent($event, $formData);
    }

    private function hydrateEvent($event, $formData)
    {
        $event->setName($formData['name']);

        return $event;
    }

    /**
     * Creates the delete event with the provided form data
     */
    protected function getDeleteEvent()
    {
        // TODO: Implement getDeleteEvent() method.
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param unknown $event
     */
    protected function eventContainsObject($event)
    {
        // TODO: Implement eventContainsObject() method.
    }

    /**
     * Get the created object from an event.
     *
     * @param unknown $createEvent
     */
    protected function getObjectFromEvent($event)
    {
        // TODO: Implement getObjectFromEvent() method.
    }

    /**
     * Load an existing object from the database
     */
    protected function getExistingObject()
    {
        // TODO: Implement getExistingObject() method.
    }

    /**
     * Returns the object label form the object event (name, title, etc.)
     *
     * @param unknown $object
     */
    protected function getObjectLabel($object)
    {
        // TODO: Implement getObjectLabel() method.
    }

    /**
     * Returns the object ID from the object
     *
     * @param unknown $object
     */
    protected function getObjectId($object)
    {
        // TODO: Implement getObjectId() method.
    }

    /**
     * Render the main list template
     *
     * @param unknown $currentOrder, if any, null otherwise.
     */
    protected function renderListTemplate($currentOrder)
    {
        return $this->render("shipping-configuration");
    }

    /**
     * Render the edition template
     */
    protected function renderEditionTemplate()
    {
        // TODO: Implement renderEditionTemplate() method.
    }

    /**
     * Redirect to the edition template
     */
    protected function redirectToEditionTemplate()
    {
        // TODO: Implement redirectToEditionTemplate() method.
    }

    /**
     * Redirect to the list template
     */
    protected function redirectToListTemplate()
    {
        return $this->render("shipping-configuration");
    }
}
