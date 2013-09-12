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

use Thelia\Core\Event\AttributeDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Tools\URL;
use Thelia\Core\Event\AttributeUpdateEvent;
use Thelia\Core\Event\AttributeCreateEvent;
use Thelia\Log\Tlog;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Core\Security\Exception\AuthorizationException;
use Thelia\Model\AttributeQuery;
use Thelia\Form\AttributeModificationForm;
use Thelia\Form\AttributeCreationForm;
use Thelia\Core\Event\AttributeUpdatePositionEvent;
use Thelia\Form\AttributeValueCreationForm;
use Thelia\Core\Event\AttributeValueCreateEvent;
use Thelia\Core\Event\AttributeValueDeleteEvent;

/**
 * Manages product attributes
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class AttributeController extends BaseAdminController
{
    /**
     * Render the attributes list, ensuring the sort order is set.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    protected function renderList() {

        // Find the current order
        $order = $this->getRequest()->get(
                'order',
                $this->getSession()->get('admin.attribute_order', 'manual')
        );

        // Store the current sort order in session
        $this->getSession()->set('admin.attribute_order', $order);

        return $this->render('attributes', array('order' => $order));
    }

    /**
     * The default action is displaying the product attributes list.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function defaultAction() {

        if (null !== $response = $this->checkAuth("admin.configuration.attributes.view")) return $response;

        return $this->renderList();
    }

    /**
     * Create a new product attribute object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function createAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.attributes.create")) return $response;

        $error_msg = false;

        // Create the Creation Form
        $creationForm = new AttributeCreationForm($this->getRequest());

        try {

            // Validate the form, create the AttributeCreation event and dispatch it.
            $form = $this->validateForm($creationForm, "POST");

            $data = $form->getData();

            $createEvent = new AttributeCreateEvent();

            $createEvent
                ->setTitle($data['title'])
                ->setLocale($data["locale"])
                ->setAddToAllTemplates($data['add_to_all'])
            ;

            $this->dispatch(TheliaEvents::ATTRIBUTE_CREATE, $createEvent);

            if (! $createEvent->hasAttribute()) throw new \LogicException($this->getTranslator()->trans("No product attribute was created."));

            $createdObject = $createEvent->getAttribute();

            // Log product attribute creation
            $this->adminLogAppend(sprintf("Attribute %s (ID %s) created", $createdObject->getTitle(), $createdObject->getId()));

            // Substitute _ID_ in the URL with the ID of the created object
            $successUrl = str_replace('_ID_', $createdObject->getId(), $creationForm->getSuccessUrl());

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

        $this->setupFormErrorContext("product attribute creation", $error_msg, $creationForm, $ex);

        // At this point, the form has error, and should be redisplayed.
        return $this->renderList();
    }

    /**
     * Create a new product attribute value object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function createValueAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.attribute-values.create")) return $response;

        $error_msg = false;

        // Create the Creation Form
        $creationForm = new AttributeValueCreationForm($this->getRequest());

        try {

            // Validate the form, create the AttributeCreation event and dispatch it.
            $form = $this->validateForm($creationForm, "POST");

            $data = $form->getData();

            $createEvent = new AttributeValueCreateEvent();

            $createEvent
                ->setTitle($data['title'])
                ->setLocale($data["locale"])
                ->setAttributeId($data["attribute_id"])
                ;

            $this->dispatch(TheliaEvents::ATTRIBUTE_VALUE_CREATE, $createEvent);

            if (! $createEvent->hasAttribute()) throw new \LogicException($this->getTranslator()->trans("No product attribute value was created."));

            $createdObject = $createEvent->getAttributeValue();

            // Log product attribute creation
            $this->adminLogAppend(sprintf("Attribute value %s (ID %s) created", $createdObject->getTitle(), $createdObject->getId()));

            // Redirect to the success URL
            $this->redirect($creationForm->getSuccessUrl());
        }
        catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        }
        catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext("product attribute value creation", $error_msg, $creationForm, $ex);

        // At this point, the form has error, and should be redisplayed on the edition page
        return $this->render('attribute-edit', array('attribute_id' => $this->getRequest()->get('attribute_id')));
    }

    /**
     * Load a product attribute object for modification, and display the edit template.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function changeAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.attributes.update")) return $response;

        // Load the product attribute object
        $attribute = AttributeQuery::create()
                    ->joinWithI18n($this->getCurrentEditionLocale())
                    ->findOneById($this->getRequest()->get('attribute_id'));

        if ($attribute != null) {

            // Prepare the data that will hydrate the form
            $data = array(
                'id'           => $attribute->getId(),
                'locale'       => $attribute->getLocale(),
                'title'        => $attribute->getTitle(),
                'chapo'        => $attribute->getChapo(),
                'description'  => $attribute->getDescription(),
                'postscriptum' => $attribute->getPostscriptum()
            );

            // Setup the object form
            $changeForm = new AttributeModificationForm($this->getRequest(), "form", $data);

            // Pass it to the parser
            $this->getParserContext()->addForm($changeForm);
        }

        // Render the edition template.
        return $this->render('attribute-edit', array('attribute_id' => $this->getRequest()->get('attribute_id')));
    }

    /**
     * Save changes on a modified product attribute object, and either go back to the product attribute list, or stay on the edition page.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function saveChangeAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.attributes.update")) return $response;

        $error_msg = false;

        // Create the form from the request
        $changeForm = new AttributeModificationForm($this->getRequest());

        // Get the attribute ID
        $attribute_id = $this->getRequest()->get('attribute_id');

        try {

            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, "POST");

            // Get the form field values
            $data = $form->getData();

            $changeEvent = new AttributeUpdateEvent($data['id']);

            // Create and dispatch the change event
            $changeEvent
                ->setLocale($data["locale"])
                ->setTitle($data['title'])
                ->setChapo($data['chapo'])
                ->setDescription($data['description'])
                ->setPostscriptum($data['postscriptum'])
            ;

            $this->dispatch(TheliaEvents::ATTRIBUTE_UPDATE, $changeEvent);

            if (! $changeEvent->hasAttribute()) throw new \LogicException($this->getTranslator()->trans("No product attribute was updated."));

            // Log product attribute modification
            $changedObject = $changeEvent->getAttribute();

            $this->adminLogAppend(sprintf("Attribute %s (ID %s) modified", $changedObject->getTitle(), $changedObject->getId()));

            // If we have to stay on the same page, do not redirect to the succesUrl,
            // just redirect to the edit page again.
            if ($this->getRequest()->get('save_mode') == 'stay') {
                $this->redirectToRoute(
                        "admin.configuration.attributes.update",
                        array('attribute_id' => $attribute_id)
                );
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

        $this->setupFormErrorContext("product attribute modification", $error_msg, $changeForm, $ex);

        // At this point, the form has errors, and should be redisplayed.
        return $this->render('attribute-edit', array('attribute_id' => $attribute_id));
    }

    /**
     * Update product attribute position
     */
    public function updatePositionAction() {
        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.attributes.update")) return $response;

        try {
            $mode = $this->getRequest()->get('mode', null);

            if ($mode == 'up')
                $mode = AttributeUpdatePositionEvent::POSITION_UP;
            else if ($mode == 'down')
                $mode = AttributeUpdatePositionEvent::POSITION_DOWN;
            else
                $mode = AttributeUpdatePositionEvent::POSITION_ABSOLUTE;

            $position = $this->getRequest()->get('position', null);

            $event = new AttributeUpdatePositionEvent(
                    $this->getRequest()->get('attribute_id', null),
                    $mode,
                    $this->getRequest()->get('position', null)
            );

            $this->dispatch(TheliaEvents::ATTRIBUTE_UPDATE_POSITION, $event);
        }
        catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToRoute('admin.configuration.attributes.default');
    }


    /**
     * Delete a product attribute object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function deleteAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.product attributes.delete")) return $response;

        // Get the product attribute id, and dispatch the delet request
        $event = new AttributeDeleteEvent($this->getRequest()->get('attribute_id'));

        $this->dispatch(TheliaEvents::ATTRIBUTE_DELETE, $event);

        if ($event->hasAttribute())
            $this->adminLogAppend(sprintf("Attribute %s (ID %s) deleted", $event->getAttribute()->getTitle(), $event->getAttribute()->getId()));

        $this->redirectToRoute('admin.configuration.attributes.default');
    }

    /**
     * Delete a product attribute value object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function deleteValueAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.product attribute-values.delete")) return $response;

        // Get the product attribute id, and dispatch the delet request
        $event = new AttributeValueDeleteEvent($this->getRequest()->get('value_id'));

        $this->dispatch(TheliaEvents::ATTRIBUTE_VALUE_DELETE, $event);

        if ($event->hasAttributeValue())
            $this->adminLogAppend(sprintf("Attribute value %s (ID %s) deleted", $event->getAttributeValue()->getTitle(), $event->getAttributeValue()->getId()));

        $this->redirectToRoute('admin.configuration.attributes.default');
    }
}