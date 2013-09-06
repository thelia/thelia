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

use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\Exception\AuthorizationException;
use Thelia\Log\Tlog;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\CategoryCreateEvent;
use Thelia\Form\CategoryCreationForm;
use Thelia\Core\Event\CategoryDeleteEvent;
use Thelia\Core\Event\CategoryToggleVisibilityEvent;
use Thelia\Core\Event\CategoryChangePositionEvent;
use Thelia\Form\CategoryDeletionForm;
use Thelia\Model\Lang;

class CategoryController extends BaseAdminController
{
    /**
     * Render the categories list, ensuring the sort order is set.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    protected function renderList() {

        $args = $this->setupArgs();

        return $this->render('categories', $args);
    }

    protected function setupArgs() {

        // Get the category ID
        $id = $this->getRequest()->get('category_id', 0);

        // Find the current category order
        $category_order = $this->getRequest()->get(
                'order',
                $this->getSession()->get('admin.category_order', 'manual')
        );

        $args = array(
            'current_category_id' => $id,
            'category_order'      => $category_order,
        );

        // Store the current sort order in session
        $this->getSession()->set('admin.category_order', $category_order);

        return $args;
    }

    /**
     * The default action is displaying the categories list.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function defaultAction() {

        if (null !== $response = $this->checkAuth("admin.categories.view")) return $response;

        return $this->renderList();
    }

    protected function createAction($args)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.categories.create")) return $response;

        $error_msg = false;

        // Create the Creation Form
        $creationForm = new CategoryCreationForm($this->getRequest());

        try {

            // Validate the form, create the CategoryCreation event and dispatch it.
            $form = $this->validateForm($creationForm, "POST");

            $data = $form->getData();

            $createEvent = new CategoryCreateEvent();

            $categoryCreateEvent = new CategoryCreateEvent(
                $data["title"],
                $data["parent"],
                $data["locale"]
            );

            $this->dispatch(TheliaEvents::CATEGORY_CREATE, $createEvent);

            $createdObject = $createEvent->getCategory();

            // Log currency creation
            $this->adminLogAppend(sprintf("Category %s (ID %s) created", $createdObject->getName(), $createdObject->getId()));

            // Substitute _ID_ in the URL with the ID of the created object
            $successUrl = str_replace('_ID_', $createdObject->getId(), $creationForm->getSuccessUrl());

            // Redirect to the success URL
            $this->redirect($successUrl);
        }
        catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = sprintf("Please check your input: %s", $ex->getMessage());
        }
        catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex;
        }

        if ($error_msg !== false) {
            // An error has been detected: log it
            Tlog::getInstance()->error(sprintf("Error during category creation process : %s. Exception was %s", $error_msg, $ex->getMessage()));

            // Mark the form as errored
            $creationForm->setErrorMessage($error_msg);

            // Pass it to the parser, along with the error currency
            $this->getParserContext()
                ->addForm($creationForm)
                ->setGeneralError($error_msg)
            ;
        }

        // At this point, the form has error, and should be redisplayed.
        return $this->renderList();
    }

    /**
     * Load a currency object for modification, and display the edit template.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function changeAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.categories.update")) return $response;

        // Load the currency object
        $currency = CategoryQuery::create()
        ->joinWithI18n($this->getCurrentEditionLocale())
        ->findOneById($this->getRequest()->get('currency_id'));

        if ($currency != null) {

            // Prepare the data that will hydrate the form
            $data = array(
                    'id'     => $currency->getId(),
                    'name'   => $currency->getName(),
                    'locale' => $currency->getLocale(),
                    'code'   => $currency->getCode(),
                    'symbol' => $currency->getSymbol(),
                    'rate'   => $currency->getRate()
            );

            // Setup the object form
            $changeForm = new CategoryModificationForm($this->getRequest(), "form", $data);

            // Pass it to the parser
            $this->getParserContext()->addForm($changeForm);
        }

        // Render the edition template.
        return $this->render('currency-edit', array('currency_id' => $this->getRequest()->get('currency_id')));
    }

    /**
     * Save changes on a modified currency object, and either go back to the currency list, or stay on the edition page.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function saveChangeAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.categories.update")) return $response;

        $error_msg = false;

        // Create the form from the request
        $changeForm = new CategoryModificationForm($this->getRequest());

        // Get the currency ID
        $currency_id = $this->getRequest()->get('currency_id');

        try {

            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, "POST");

            // Get the form field values
            $data = $form->getData();

            $changeEvent = new CategoryUpdateEvent($data['id']);

            // Create and dispatch the change event
            $changeEvent
            ->setCategoryName($data['name'])
            ->setLocale($data["locale"])
            ->setSymbol($data['symbol'])
            ->setCode($data['code'])
            ->setRate($data['rate'])
            ;

            $this->dispatch(TheliaEvents::CATEGORY_UPDATE, $changeEvent);

            // Log currency modification
            $changedObject = $changeEvent->getCategory();

            $this->adminLogAppend(sprintf("Category %s (ID %s) modified", $changedObject->getName(), $changedObject->getId()));

            // If we have to stay on the same page, do not redirect to the succesUrl,
            // just redirect to the edit page again.
            if ($this->getRequest()->get('save_mode') == 'stay') {
                $this->redirectToRoute(
                        "admin.categories.update",
                        array('currency_id' => $currency_id)
                );
            }

            // Redirect to the success URL
            $this->redirect($changeForm->getSuccessUrl());
        }
        catch (FormValidationException $ex) {
            // Invalid data entered
            $error_msg = sprintf("Please check your input: %s", $ex->getMessage());
        }
        catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex;
        }

        if ($error_msg !== false) {
            // Log error currency
            Tlog::getInstance()->error(sprintf("Error during currency modification process : %s. Exception was %s", $error_msg, $ex->getMessage()));

            // Mark the form as errored
            $changeForm->setErrorMessage($error_msg);

            // Pas the form and the error to the parser
            $this->getParserContext()
            ->addForm($changeForm)
            ->setGeneralError($error_msg)
            ;
        }

        // At this point, the form has errors, and should be redisplayed.
        return $this->render('currency-edit', array('currency_id' => $currency_id));
    }

    /**
     * Sets the default currency
     */
    public function setDefaultAction() {
        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.categories.update")) return $response;

        $changeEvent = new CategoryUpdateEvent($this->getRequest()->get('currency_id', 0));

        // Create and dispatch the change event
        $changeEvent->setIsDefault(true);

        try {
            $this->dispatch(TheliaEvents::CATEGORY_SET_DEFAULT, $changeEvent);
        }
        catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToRoute('admin.categories.default');
    }

    /**
     * Update categories rates
     */
    public function updateRatesAction() {
        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.categories.update")) return $response;

        try {
            $this->dispatch(TheliaEvents::CATEGORY_UPDATE_RATES);
        }
        catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToRoute('admin.categories.default');
    }

    /**
     * Update currencyposition
     */
    public function updatePositionAction() {
        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.categories.update")) return $response;

        try {
            $mode = $this->getRequest()->get('mode', null);

            if ($mode == 'up')
                $mode = CategoryUpdatePositionEvent::POSITION_UP;
            else if ($mode == 'down')
                $mode = CategoryUpdatePositionEvent::POSITION_DOWN;
            else
                $mode = CategoryUpdatePositionEvent::POSITION_ABSOLUTE;

            $position = $this->getRequest()->get('position', null);

            $event = new CategoryUpdatePositionEvent(
                    $this->getRequest()->get('currency_id', null),
                    $mode,
                    $this->getRequest()->get('position', null)
            );

            $this->dispatch(TheliaEvents::CATEGORY_UPDATE_POSITION, $event);
        }
        catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToRoute('admin.categories.default');
    }


    /**
     * Delete a currency object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function deleteAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.categories.delete")) return $response;

        // Get the currency id, and dispatch the delet request
        $event = new CategoryDeleteEvent($this->getRequest()->get('currency_id'));

        $this->dispatch(TheliaEvents::CATEGORY_DELETE, $event);

        $this->redirectToRoute('admin.categories.default');
    }
}
