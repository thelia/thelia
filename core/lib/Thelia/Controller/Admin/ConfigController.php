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

use Thelia\Core\Event\ConfigDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Tools\URL;
use Thelia\Core\Event\ConfigChangeEvent;
use Thelia\Core\Event\ConfigCreateEvent;
use Thelia\Log\Tlog;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Core\Security\Exception\AuthorizationException;
use Thelia\Model\ConfigQuery;
use Thelia\Form\ConfigModificationForm;
use Thelia\Form\ConfigCreationForm;

/**
 * Manages Thelmia system variables, aka Config objects.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ConfigController extends BaseAdminController
{
    /**
     * Render the currencies list, ensuring the sort order is set.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    protected function renderList() {

        // Find the current order
        $order = $this->getRequest()->get(
                'order',
                $this->getSession()->get('admin.variables_order', 'name')
        );

        // Store the current sort order in session
        $this->getSession()->set('admin.variables_order', $order);

        return $this->render('variables', array('order' => $order));
    }

    /**
     * The default action is displaying the variables list.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function defaultAction() {

        if (null !== $response = $this->checkAuth("admin.configuration.variables.view")) return $response;

        return $this->renderList();
    }

    /**
     * Create a new config object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function createAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.variables.create")) return $response;

        $message = false;

        // Create the Creation Form
        $creationForm = new ConfigCreationForm($this->getRequest());

        try {

            // Validate the form, create the ConfigCreation event and dispatch it.
            $form = $this->validateForm($creationForm, "POST");

            $data = $form->getData();

            $createEvent = new ConfigCreateEvent();

            $createEvent
                ->setEventName($data['name'])
                ->setValue($data['value'])
                ->setLocale($data["locale"])
                ->setTitle($data['title'])
                ->setHidden($data['hidden'])
                ->setSecured($data['secured'])
                ;

            $this->dispatch(TheliaEvents::CONFIG_CREATE, $createEvent);

            $createdObject = $createEvent->getConfig();

            // Log config creation
            $this->adminLogAppend(sprintf("Variable %s (ID %s) created", $createdObject->getName(), $createdObject->getId()));

            // Substitute _ID_ in the URL with the ID of the created object
            $successUrl = str_replace('_ID_', $createdObject->getId(), $creationForm->getSuccessUrl());

            // Redirect to the success URL
            $this->redirect($successUrl);
        }
        catch (FormValidationException $ex) {
            // Form cannot be validated
            $message = sprintf("Please check your input: %s", $ex->getMessage());
        }
        catch (\Exception $ex) {
            // Any other error
            $message = sprintf("Sorry, an error occured: %s", $ex->getMessage());
        }

        if ($message !== false) {
            // An error has been detected: log it
            Tlog::getInstance()->error(sprintf("Error during variable creation process : %s. Exception was %s", $message, $ex->getMessage()));

            // Mark the form as errored
            $creationForm->setErrorMessage($message);

            // Pass it to the parser, along with the error message
            $this->getParserContext()
                ->addForm($creationForm)
                ->setGeneralError($message)
            ;
        }

        // At this point, the form has error, and should be redisplayed.
        return $this->renderList();
    }

    /**
     * Load a config object for modification, and display the edit template.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function changeAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.variables.change")) return $response;

        // Load the config object
        $config = ConfigQuery::create()
                    ->joinWithI18n($this->getCurrentEditionLocale())
                    ->findOneById($this->getRequest()->get('variable_id'));

        if ($config != null) {

            // Prepare the data that will hydrate the form
            $data = array(
                'id'           => $config->getId(),
                'name'         => $config->getName(),
                'value'        => $config->getValue(),
                'hidden'       => $config->getHidden(),
                'secured'      => $config->getSecured(),
                'locale'       => $config->getLocale(),
                'title'        => $config->getTitle(),
                'chapo'        => $config->getChapo(),
                'description'  => $config->getDescription(),
                'postscriptum' => $config->getPostscriptum()
            );

            // Setup the object form
            $changeForm = new ConfigModificationForm($this->getRequest(), "form", $data);

            // Pass it to the parser
            $this->getParserContext()->addForm($changeForm);
        }

        // Render the edition template.
        return $this->render('variable-edit', array('variable_id' => $this->getRequest()->get('variable_id')));
    }

    /**
     * Save changes on a modified config object, and either go back to the variable list, or stay on the edition page.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function saveChangeAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.variables.change")) return $response;

        $message = false;

        // Create the form from the request
        $changeForm = new ConfigModificationForm($this->getRequest());

        // Get the variable ID
        $variable_id = $this->getRequest()->get('variable_id');

        try {

            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, "POST");

            // Get the form field values
            $data = $form->getData();

            $changeEvent = new ConfigChangeEvent($data['id']);

            // Create and dispatch the change event
            $changeEvent
                ->setEventName($data['name'])
                ->setValue($data['value'])
                ->setHidden($data['hidden'])
                ->setSecured($data['secured'])
                ->setLocale($data["locale"])
                ->setTitle($data['title'])
                ->setChapo($data['chapo'])
                ->setDescription($data['description'])
                ->setPostscriptum($data['postscriptum'])
            ;

            $this->dispatch(TheliaEvents::CONFIG_MODIFY, $changeEvent);

            // Log config modification
            $changedObject = $changeEvent->getConfig();

            $this->adminLogAppend(sprintf("Variable %s (ID %s) modified", $changedObject->getName(), $changedObject->getId()));

            // If we have to stay on the same page, do not redirect to the succesUrl,
            // just redirect to the edit page again.
            if ($this->getRequest()->get('save_mode') == 'stay') {
                $this->redirect(URL::absoluteUrl(
                        "admin/configuration/variables/change",
                        array('variable_id' => $variable_id)
                ));
            }

            // Redirect to the success URL
            $this->redirect($changeForm->getSuccessUrl());
        }
        catch (FormValidationException $ex) {
            // Invalid data entered
            $message = sprintf("Please check your input: %s", $ex->getMessage());
        }
        catch (\Exception $ex) {
            // Any other error
            $message = sprintf("Sorry, an error occured: %s", $ex->getMessage());
        }

        if ($message !== false) {
            // Log error message
            Tlog::getInstance()->error(sprintf("Error during variable modification process : %s. Exception was %s", $message, $ex->getMessage()));

            // Mark the form as errored
            $changeForm->setErrorMessage($message);

            // Pas the form and the error to the parser
            $this->getParserContext()
                ->addForm($changeForm)
                ->setGeneralError($message)
            ;
        }

        // At this point, the form has errors, and should be redisplayed.
        return $this->render('variable-edit', array('variable_id' => $variable_id));
    }

    /**
     * Change values modified directly from the variable list
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function changeValuesAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.variables.change")) return $response;

        $variables = $this->getRequest()->get('variable', array());

        // Process all changed variables
        foreach($variables as $id => $value) {
            $event = new ConfigChangeEvent($id);
            $event->setValue($value);

            $this->dispatch(TheliaEvents::CONFIG_SETVALUE, $event);
        }

        $this->redirect(URL::absoluteUrl('/admin/configuration/variables'));
    }

    /**
     * Delete a config object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function deleteAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.variables.delete")) return $response;

        // Get the config id, and dispatch the delet request
        $event = new ConfigDeleteEvent($this->getRequest()->get('variable_id'));

        $this->dispatch(TheliaEvents::CONFIG_DELETE, $event);

        $this->redirect(URL::absoluteUrl('/admin/configuration/variables'));
    }
}