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
use Thelia\Core\Event\Area\AreaDeleteEvent;
use Thelia\Core\Event\Area\AreaUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\Area\AreaCreateForm;
use Thelia\Form\Area\AreaModificationForm;
use Thelia\Model\AreaQuery;

/**
 * Class AreaController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AreaController extends AbstractCrudController
{

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

    protected function getAreaId()
    {
        return $this->getRequest()->get('area_id', 0);
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
        return new AreaModificationForm($this->getRequest());
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param unknown $object
     */
    protected function hydrateObjectForm($object)
    {
        $data = array(
            'name' => $object->getName()
        );

        return new AreaModificationForm($this->getRequest(), 'form', $data);
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
        return new AreaDeleteEvent($this->getAreaId());
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param \Thelia\Core\Event\Area\AreaEvent $event
     */
    protected function eventContainsObject($event)
    {
        return $event->hasArea();
    }

    /**
     * Get the created object from an event.
     *
     * @param \Thelia\Core\Event\Area\AreaEvent $event
     */
    protected function getObjectFromEvent($event)
    {
        return $event->getArea();
    }

    /**
     * Load an existing object from the database
     */
    protected function getExistingObject()
    {
        return AreaQuery::create()->findPk($this->getAreaId());
    }

    /**
     * Returns the object label form the object event (name, title, etc.)
     *
     * @param \Thelia\Model\Area $object
     */
    protected function getObjectLabel($object)
    {
        return $object->getName();
    }

    /**
     * Returns the object ID from the object
     *
     * @param \Thelia\Model\Area $object
     */
    protected function getObjectId($object)
    {
        return $object->getId();
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
        return $this->render('shipping-configuration-edit',array(
            'area_id' => $this->getAreaId()
        ));
    }

    /**
     * Redirect to the edition template
     */
    protected function redirectToEditionTemplate()
    {
        $this->redirectToRoute('admin.configuration.shipping-configuration.update.view', array(), array(
                "area_id" => $this->getAreaId()
            )
        );
    }

    /**
     * Redirect to the list template
     */
    protected function redirectToListTemplate()
    {
        $this->redirectToRoute('admin.configuration.shipping-configuration.default');
    }
}
