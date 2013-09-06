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

use Thelia\Core\Event\MessageDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Tools\URL;
use Thelia\Core\Event\MessageUpdateEvent;
use Thelia\Core\Event\MessageCreateEvent;
use Thelia\Log\Tlog;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Core\Security\Exception\AuthorizationException;
use Thelia\Model\MessageQuery;
use Thelia\Form\MessageModificationForm;
use Thelia\Form\MessageCreationForm;

/**
 * Manages messages sent by mail
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class MessageController extends BaseAdminController
{
    /**
     * The default action is displaying the messages list.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function defaultAction() {

        if (null !== $response = $this->checkAuth("admin.configuration.messages.view")) return $response;

        return $this->render('messages');
    }

    /**
     * Create a new message object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function createAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.messages.create")) return $response;

        $message = false;

        // Create the Creation Form
        $creationForm = new MessageCreationForm($this->getRequest());

        try {

            // Validate the form, create the MessageCreation event and dispatch it.
            $form = $this->validateForm($creationForm, "POST");

            $data = $form->getData();

            $createEvent = new MessageCreateEvent();

            $createEvent
                ->setMessageName($data['name'])
                ->setLocale($data["locale"])
                ->setTitle($data['title'])
                ->setSecured($data['secured'])
                ;

            $this->dispatch(TheliaEvents::MESSAGE_CREATE, $createEvent);

            $createdObject = $createEvent->getMessage();

            // Log message creation
            $this->adminLogAppend(sprintf("Variable %s (ID %s) created", $createdObject->getName(), $createdObject->getId()));

            // Substitute _ID_ in the URL with the ID of the created object
            $successUrl = str_replace('_ID_', $createdObject->getId(), $creationForm->getSuccessUrl());

            // Redirect to the success URL
            $this->redirect($successUrl);
        }
        catch (FormValidationException $ex) {
            // Form cannot be validated
            $message = $this->createStandardFormValidationErrorMessage($ex);
        }
        catch (\Exception $ex) {
            // Any other error
            $message = $ex->getMessage();
        }

        $this->setupFormErrorContext("message modification", $message, $creationForm, $ex);

        // At this point, the form has error, and should be redisplayed.
        return $this->render('messages');
    }

    /**
     * Load a message object for modification, and display the edit template.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function changeAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.messages.update")) return $response;

        // Load the message object
        $message = MessageQuery::create()
                    ->joinWithI18n($this->getCurrentEditionLocale())
                    ->findOneById($this->getRequest()->get('message_id'));

        if ($message != null) {

            // Prepare the data that will hydrate the form
            $data = array(
                'id'           => $message->getId(),
                'name'         => $message->getName(),
                'secured'      => $message->getSecured(),
                'locale'       => $message->getLocale(),
                'title'        => $message->getTitle(),
                'subject'      => $message->getSubject(),
                'html_message' => $message->getHtmlMessage(),
                'text_message' => $message->getTextMessage()
            );

            // Setup the object form
            $changeForm = new MessageModificationForm($this->getRequest(), "form", $data);

            // Pass it to the parser
            $this->getParserContext()->addForm($changeForm);
        }

        // Render the edition template.
        return $this->render('message-edit', array('message_id' => $this->getRequest()->get('message_id')));
    }

    /**
     * Save changes on a modified message object, and either go back to the message list, or stay on the edition page.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function saveChangeAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.messages.update")) return $response;

        $message = false;

        // Create the form from the request
        $changeForm = new MessageModificationForm($this->getRequest());

        // Get the message ID
        $message_id = $this->getRequest()->get('message_id');

        try {

            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, "POST");

            // Get the form field values
            $data = $form->getData();

            $changeEvent = new MessageUpdateEvent($data['id']);

            // Create and dispatch the change event
            $changeEvent
                ->setMessageName($data['name'])
                ->setSecured($data['secured'])
                ->setLocale($data["locale"])
                ->setTitle($data['title'])
                ->setSubject($data['subject'])
                ->setHtmlMessage($data['html_message'])
                ->setTextMessage($data['text_message'])
            ;

            $this->dispatch(TheliaEvents::MESSAGE_UPDATE, $changeEvent);

            // Log message modification
            $changedObject = $changeEvent->getMessage();

            $this->adminLogAppend(sprintf("Variable %s (ID %s) modified", $changedObject->getName(), $changedObject->getId()));

            // If we have to stay on the same page, do not redirect to the succesUrl,
            // just redirect to the edit page again.
            if ($this->getRequest()->get('save_mode') == 'stay') {
                $this->redirectToRoute(
                        "admin.configuration.messages.update",
                        array('message_id' => $message_id)
                );
            }

            // Redirect to the success URL
            $this->redirect($changeForm->getSuccessUrl());
        }
        catch (FormValidationException $ex) {
            // Form cannot be validated
            $message = $this->createStandardFormValidationErrorMessage($ex);
        }
        catch (\Exception $ex) {
            // Any other error
            $message = $ex->getMessage();
        }

        $this->setupFormErrorContext("message modification", $message, $changeForm, $ex);

        // At this point, the form has errors, and should be redisplayed.
        return $this->render('message-edit', array('message_id' => $message_id));
    }

    /**
     * Delete a message object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function deleteAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.messages.delete")) return $response;

        // Get the message id, and dispatch the delet request
        $event = new MessageDeleteEvent($this->getRequest()->get('message_id'));

        $this->dispatch(TheliaEvents::MESSAGE_DELETE, $event);

        $this->redirect(URL::getInstance()->adminViewUrl('messages'));
    }
}