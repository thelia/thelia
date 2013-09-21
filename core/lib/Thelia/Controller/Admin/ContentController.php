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
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\ContentCreationForm;
use Thelia\Form\ContentModificationForm;


/**
 * Class ContentController
 * @package Thelia\Controller\Admin
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class ContentController extends AbstractCrudController
{

    public function __construct()
    {
        parent::__construct(
            'content',
            'manual',
            'content_order',

            'admin.content.default',
            'admin.content.create',
            'admin.content.update',
            'admin.content.delete',

            TheliaEvents::CONTENT_CREATE,
            TheliaEvents::CONTENT_UPDATE,
            TheliaEvents::CONTENT_DELETE,
            TheliaEvents::CONTENT_TOGGLE_VISIBILITY,
            TheliaEvents::CONTENT_UPDATE_POSITION
        );
    }

    /**
     * Return the creation form for this object
     */
    protected function getCreationForm()
    {
        return new ContentCreationForm($this->getRequest());
    }

    /**
     * Return the update form for this object
     */
    protected function getUpdateForm()
    {
        return new ContentModificationForm($this->getRequest());
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param \Thelia\Form\ContentModificationForm $object
     */
    protected function hydrateObjectForm($object)
    {
        // Prepare the data that will hydrate the form
        $data = array(
            'id'           => $object->getId(),
            'locale'       => $object->getLocale(),
            'title'        => $object->getTitle(),
            'chapo'        => $object->getChapo(),
            'description'  => $object->getDescription(),
            'postscriptum' => $object->getPostscriptum(),
            'visible'      => $object->getVisible(),
            'url'          => $object->getRewrittenUrl($this->getCurrentEditionLocale()),
            'parent'       => $object->getParent()
        );

        // Setup the object form
        return new ContentModificationForm($this->getRequest(), "form", $data);
    }

    /**
     * Creates the creation event with the provided form data
     *
     * @param unknown $formData
     */
    protected function getCreationEvent($formData)
    {
        // TODO: Implement getCreationEvent() method.
    }

    /**
     * Creates the update event with the provided form data
     *
     * @param unknown $formData
     */
    protected function getUpdateEvent($formData)
    {
        // TODO: Implement getUpdateEvent() method.
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
        // TODO: Implement renderListTemplate() method.
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
        // TODO: Implement redirectToListTemplate() method.
    }
}