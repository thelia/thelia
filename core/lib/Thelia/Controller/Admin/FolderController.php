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
use Thelia\Core\Event\FolderUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\FolderModificationForm;
use Thelia\Model\FolderQuery;

/**
 * Class FolderController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class FolderController extends AbstractCrudController
{

    public function __construct()
    {
        parent::__construct(
            'folder',
            'manual',
            'folder_order',

            'admin.folder.default',
            'admin.folder.create',
            'admin.folder.update',
            'admin.folder.delete',

            TheliaEvents::FOLDER_CREATE,
            TheliaEvents::FOLDER_UPDATE,
            TheliaEvents::FOLDER_DELETE,
            TheliaEvents::FOLDER_TOGGLE_VISIBILITY,
            TheliaEvents::FOLDER_UPDATE_POSITION
        );
    }

    /**
     * Return the creation form for this object
     */
    protected function getCreationForm()
    {
        // TODO: Implement getCreationForm() method.
    }

    /**
     * Return the update form for this object
     */
    protected function getUpdateForm()
    {
        return new FolderModificationForm($this->getRequest());
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param \Thelia\Model\Folder $object
     */
    protected function hydrateObjectForm($object) {

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
        return new FolderModificationForm($this->getRequest(), "form", $data);
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
        $updateEvent = new FolderUpdateEvent($formData['id']);

        $updateEvent
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
            ->setVisible($formData['visible'])
            ->setUrl($formData['url'])
            ->setParent($formData['parent'])
        ;

        return $updateEvent;
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
        return $event->hasFolder();
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
    protected function getExistingObject() {
        return FolderQuery::create()
            ->joinWithI18n($this->getCurrentEditionLocale())
            ->findOneById($this->getRequest()->get('folder_id', 0));
    }

    /**
     * Returns the object label form the object event (name, title, etc.)
     *
     * @param unknown $object
     */
    protected function getObjectLabel($object) {
        return $object->getTitle();
    }

    /**
     * Returns the object ID from the object
     *
     * @param unknown $object
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
    protected function renderListTemplate($currentOrder) {

        // Get product order
        $product_order = $this->getListOrderFromSession('content', 'content_order', 'manual');

        return $this->render('folders',
            array(
                'folder_order' => $currentOrder,
                'content_order' => $product_order,
                'folder_id' => $this->getRequest()->get('folder_id', 0)
            ));
    }


    /**
     * Render the edition template
     */
    protected function renderEditionTemplate() {

        return $this->render('folder-edit', $this->getEditionArguments());
    }

    protected function getEditionArguments()
    {
        return array(
            'folder_id' => $this->getRequest()->get('folder_id', 0),
            'current_tab' => $this->getRequest()->get('current_tab', 'general')
        );
    }

    /**
     * @param \Thelia\Core\Event\FolderUpdateEvent $updateEvent
     * @return Response|void
     */
    protected function performAdditionalUpdateAction($updateEvent)
    {
        if ($this->getRequest()->get('save_mode') != 'stay') {

            // Redirect to parent category list
            $this->redirectToRoute(
                'admin.folders.default',
                array('folder_id' => $updateEvent->getFolder()->getParent())
            );
        }
    }

    protected function performAdditionalDeleteAction($deleteEvent)
    {
        // Redirect to parent category list
        $this->redirectToRoute(
            'admin.folders.default',
            array('folder_id' => $deleteEvent->getFolder()->getParent())
        );
    }

    /**
     * Redirect to the edition template
     */
    protected function redirectToEditionTemplate()
    {
        $this->redirectToRoute("admin.folders.update", $this->getEditionArguments());
    }

    /**
     * Redirect to the list template
     */
    protected function redirectToListTemplate()
    {
        $this->redirectToRoute(
            'admin.folders.default',
            array('folder_id' => $this->getRequest()->get('folder_id', 0))
        );
    }
}