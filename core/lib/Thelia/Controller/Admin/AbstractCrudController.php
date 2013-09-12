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

use Thelia\Form\Exception\FormValidationException;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\ToggleVisibilityEvent;

/**
 * Manages currencies sent by mail
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
abstract class AbstractCrudController extends BaseAdminController
{
   protected $objectName;

    // List ordering
    protected $defaultListOrder;

    // Permissions
    protected $viewPermissionIdentifier;
    protected $createPermissionIdentifier;
    protected $updatePermissionIdentifier;
    protected $deletePermissionIdentifier;

    // Events
    protected $createEventIdentifier;
    protected $updateEventIdentifier;
    protected $deleteEventIdentifier;
    protected $visibilityToggleEventIdentifier;
    protected $changePositionEventIdentifier;


    public function __construct(
            $objectName,

            $defaultListOrder = null,

            $viewPermissionIdentifier,
            $createPermissionIdentifier,
            $updatePermissionIdentifier,
            $deletePermissionIdentifier,

            $createEventIdentifier,
            $updateEventIdentifier,
            $deleteEventIdentifier,
            $visibilityToggleEventIdentifier = null,
            $changePositionEventIdentifier = null
    ) {
            $this->objectName = $objectName;

            $this->defaultListOrder = $defaultListOrder;

            $this->viewPermissionIdentifier = $viewPermissionIdentifier;
            $this->createPermissionIdentifier = $createPermissionIdentifier;
            $this->updatePermissionIdentifier = $updatePermissionIdentifier;
            $this->deletePermissionIdentifier = $deletePermissionIdentifier;

            $this->createEventIdentifier = $createEventIdentifier;
            $this->updateEventIdentifier = $updateEventIdentifier;
            $this->deleteEventIdentifier = $deleteEventIdentifier;
            $this->visibilityToggleEventIdentifier = $visibilityToggleEventIdentifier;
            $this->changePositionEventIdentifier = $changePositionEventIdentifier;
    }

    /**
     * Return the creation form for this object
     */
    protected abstract function getCreationForm();

    /**
     * Return the update form for this object
     */
    protected abstract function getUpdateForm();

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param unknown $object
     */
    protected abstract function hydrateObjectForm($object);

    /**
     * Creates the creation event with the provided form data
     *
     * @param unknown $formData
     */
    protected abstract function getCreationEvent($formData);

    /**
     * Creates the update event with the provided form data
     *
     * @param unknown $formData
     */
    protected abstract function getUpdateEvent($formData);

    /**
     * Creates the delete event with the provided form data
     */
    protected abstract function getDeleteEvent();

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param unknown $event
     */
    protected abstract function eventContainsObject($event);

    /**
     * Get the created object from an event.
     *
     * @param unknown $createEvent
     */
    protected abstract function getObjectFromEvent($event);

    /**
     * Load an existing object from the database
     */
    protected abstract function getExistingObject();

    /**
     * Returns the object label form the object event (name, title, etc.)
     *
     * @param unknown $object
     */
    protected abstract function getObjectLabel($object);

    /**
     * Returns the object ID from the object
     *
     * @param unknown $object
     */
    protected abstract function getObjectId($object);

    /**
     * Render the main list template
     *
     * @param unknown $currentOrder, if any, null otherwise.
     */
    protected abstract function renderListTemplate($currentOrder);

    /**
     * Render the edition template
     */
    protected abstract function renderEditionTemplate();

    /**
     * Redirect to the edition template
     */
    protected abstract function redirectToEditionTemplate();

    /**
     * Redirect to the list template
     */
    protected abstract function redirectToListTemplate();


    protected function createUpdatePositionEvent($positionChangeMode, $positionValue) {
        throw new \LogicException ("Position Update is not supported for this object");
    }

    protected function createToggleVisibilityEvent() {

        throw new \LogicException ("Toggle Visibility is not supported for this object");
    }

    /**
     * Render the object list, ensuring the sort order is set.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    protected function renderList()
    {
        $order = null;

        if ($this->defaultListOrder != null) {
            $orderSessionIdentifier = sprintf("admin.%s.currentListOrder", $this->objectName);

            // Find the current order
            $order = $this->getRequest()->get(
                    'order',
                    $this->getSession()->get($orderSessionIdentifier, $this->defaultListOrder)
            );

            // Store the current sort order in session
            $this->getSession()->set($orderSessionIdentifier, $order);
        }

        return $this->renderListTemplate($order);
    }

    /**
     * The default action is displaying the list.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth($this->viewPermissionIdentifier)) return $response;

        return $this->renderList();
    }

    /**
     * Create a new object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function createAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->createPermissionIdentifier)) return $response;

        $error_msg = false;

        // Create the Creation Form
        $creationForm = $this->getCreationForm($this->getRequest());

        try {

            // Validate the form, create the event and dispatch it.
            $form = $this->validateForm($creationForm, "POST");

            $data = $form->getData();

            $createEvent = $this->getCreationEvent($data);

            $this->dispatch($this->createEventIdentifier, $createEvent);

            if (! $this->eventContainsObject($createEvent))
                throw new \LogicException(
                        $this->getTranslator()->trans("No %obj was created.", array('%obj', $this->objectName)));

            if (null !== $createdObject = $this->getObjectFromEvent($createEvent)) {
                // Log object creation
                $this->adminLogAppend(sprintf("%s %s (ID %s) created", ucfirst($this->objectName), $this->getObjectLabel($createdObject), $this->getObjectId($createdObject)));
            }

            // Substitute _ID_ in the URL with the ID of the created object
            $successUrl = str_replace('_ID_', $this->getObjectId($createdObject), $creationForm->getSuccessUrl());

            // Redirect to the success URL
            $this->redirect($successUrl);

        }
        catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        }
        catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
                $this->getTranslator()->trans("%obj creation", array('%obj' => $this->objectName)), $error_msg, $creationForm, $ex);

        // At this point, the form has error, and should be redisplayed.
        return $this->renderList();
    }

    /**
     * Load a object for modification, and display the edit template.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function changeAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->updatePermissionIdentifier)) return $response;

        // Load the object
        $object = $this->getExistingObject($this->getRequest());

        if ($object != null) {

            // Hydrate the form abd pass it to the parser
            $changeForm = $this->hydrateObjectForm($object);

            // Pass it to the parser
            $this->getParserContext()->addForm($changeForm);
        }

        // Render the edition template.
        return $this->renderEditionTemplate();
    }

    /**
     * Save changes on a modified object, and either go back to the object list, or stay on the edition page.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function saveChangeAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->updatePermissionIdentifier)) return $response;

        $error_msg = false;

        // Create the form from the request
        $changeForm = $this->getUpdateForm($this->getRequest());

        try {

            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, "POST");

            // Get the form field values
            $data = $form->getData();

            $changeEvent = $this->getUpdateEvent($data);

            $this->dispatch($this->updateEventIdentifier, $changeEvent);

            if (! $this->eventContainsObject($changeEvent))
                throw new \LogicException(
                        $this->getTranslator()->trans("No %obj was updated.", array('%obj', $this->objectName)));

            // Log object modification
            if (null !== $changedObject = $this->getObjectFromEvent($changeEvent)) {
                $this->adminLogAppend(sprintf("%s %s (ID %s) modified", ucfirst($this->objectName), $this->getObjectLabel($changedObject), $this->getObjectId($changedObject)));
            }

            // If we have to stay on the same page, do not redirect to the succesUrl,
            // just redirect to the edit page again.
            if ($this->getRequest()->get('save_mode') == 'stay') {
                $this->redirectToEditionTemplate($this->getRequest());
            }

            // Redirect to the success URL
            $this->redirect($changeForm->getSuccessUrl());
        }
        catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        }
        catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
                $this->getTranslator()->trans("%obj modification", array('%obj' => $this->objectName)), $error_msg, $changeForm, $ex);

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    /**
     * Update object position (only for objects whichsupport that)
     */
    public function updatePositionAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->updatePermissionIdentifier)) return $response;

        try {
            $mode = $this->getRequest()->get('mode', null);

            if ($mode == 'up')
                $mode = UpdatePositionEvent::POSITION_UP;
            else if ($mode == 'down')
                $mode = UpdatePositionEvent::POSITION_DOWN;
            else
                $mode = UpdatePositionEvent::POSITION_ABSOLUTE;

            $position = $this->getRequest()->get('position', null);

            $event = $this->createUpdatePositionEvent($mode, $position);

            $this->dispatch($this->changePositionEventIdentifier, $event);
        }
        catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToListTemplate();
    }

    /**
     * Online status toggle (only for object which support it)
     */
    public function setToggleVisibilityAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->updatePermissionIdentifier)) return $response;

        $changeEvent = $this->createToggleVisibilityEvent($this->getRequest());

        // Create and dispatch the change event
        $changeEvent->setIsDefault(true);

        try {
            $this->dispatch($this->visibilityToggleEventIdentifier, $changeEvent);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToRoute('admin.categories.default');
    }

    /**
     * Delete an object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function deleteAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->deletePermissionIdentifier)) return $response;

        // Get the currency id, and dispatch the delet request
        $deleteEvent = $this->getDeleteEvent();

        $this->dispatch($this->deleteEventIdentifier, $deleteEvent);

        if (null !== $deletedObject = $this->getObjectFromEvent($deleteEvent)) {
            $this->adminLogAppend(
                    sprintf("%s %s (ID %s) deleted", ucfirst($this->objectName), $this->getObjectLabel($deletedObject), $this->getObjectId($deletedObject)));
        }

        $this->redirectToListTemplate();
    }
}
