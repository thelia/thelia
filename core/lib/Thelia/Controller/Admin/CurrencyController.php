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

use Thelia\Core\Event\CurrencyDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Tools\URL;
use Thelia\Core\Event\CurrencyUpdateEvent;
use Thelia\Core\Event\CurrencyCreateEvent;
use Thelia\Log\Tlog;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Core\Security\Exception\AuthorizationException;
use Thelia\Model\CurrencyQuery;
use Thelia\Form\CurrencyModificationForm;
use Thelia\Form\CurrencyCreationForm;
use Thelia\Core\Event\CurrencyUpdatePositionEvent;

/**
 * Manages currencies sent by mail
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class CurrencyController extends BaseAdminController
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
                $this->getSession()->get('admin.currency_order', 'manual')
        );

        // Store the current sort order in session
        $this->getSession()->set('admin.currency_order', $order);

        return $this->render('currencies', array('order' => $order));
    }

    /**
     * The default action is displaying the currencies list.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function defaultAction() {

        if (null !== $response = $this->checkAuth("admin.configuration.currencies.view")) return $response;

        return $this->renderList();
    }

    /**
     * Create a new currency object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function createAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.currencies.create")) return $response;

        $error_msg = false;

        // Create the Creation Form
        $creationForm = new CurrencyCreationForm($this->getRequest());

        try {

            // Validate the form, create the CurrencyCreation event and dispatch it.
            $form = $this->validateForm($creationForm, "POST");

            $data = $form->getData();

            $createEvent = new CurrencyCreateEvent();

            $createEvent
                ->setCurrencyName($data['name'])
                ->setLocale($data["locale"])
                ->setSymbol($data['symbol'])
                ->setCode($data['code'])
                ->setRate($data['rate'])
            ;

            $this->dispatch(TheliaEvents::CURRENCY_CREATE, $createEvent);

            $createdObject = $createEvent->getCurrency();

            // Log currency creation
            $this->adminLogAppend(sprintf("Variable %s (ID %s) created", $createdObject->getName(), $createdObject->getId()));

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
            Tlog::getInstance()->error(sprintf("Error during currency creation process : %s. Exception was %s", $error_msg, $ex->getMessage()));

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
        if (null !== $response = $this->checkAuth("admin.configuration.currencies.update")) return $response;

        // Load the currency object
        $currency = CurrencyQuery::create()
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
            $changeForm = new CurrencyModificationForm($this->getRequest(), "form", $data);

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
        if (null !== $response = $this->checkAuth("admin.configuration.currencies.update")) return $response;

        $error_msg = false;

        // Create the form from the request
        $changeForm = new CurrencyModificationForm($this->getRequest());

        // Get the currency ID
        $currency_id = $this->getRequest()->get('currency_id');

        try {

            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, "POST");

            // Get the form field values
            $data = $form->getData();

            $changeEvent = new CurrencyUpdateEvent($data['id']);

            // Create and dispatch the change event
            $changeEvent
                ->setCurrencyName($data['name'])
                ->setLocale($data["locale"])
                ->setSymbol($data['symbol'])
                ->setCode($data['code'])
                ->setRate($data['rate'])
            ;

            $this->dispatch(TheliaEvents::CURRENCY_UPDATE, $changeEvent);

            // Log currency modification
            $changedObject = $changeEvent->getCurrency();

            $this->adminLogAppend(sprintf("Variable %s (ID %s) modified", $changedObject->getName(), $changedObject->getId()));

            // If we have to stay on the same page, do not redirect to the succesUrl,
            // just redirect to the edit page again.
            if ($this->getRequest()->get('save_mode') == 'stay') {
                $this->redirectToRoute(
                        "admin.configuration.currencies.update",
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
        if (null !== $response = $this->checkAuth("admin.configuration.currencies.update")) return $response;

        $changeEvent = new CurrencyUpdateEvent($this->getRequest()->get('currency_id', 0));

        // Create and dispatch the change event
        $changeEvent->setIsDefault(true);

        try {
            $this->dispatch(TheliaEvents::CURRENCY_SET_DEFAULT, $changeEvent);
        }
        catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToRoute('admin.configuration.currencies.default');
    }

    /**
     * Update currencies rates
     */
    public function updateRatesAction() {
        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.currencies.update")) return $response;

        try {
            $this->dispatch(TheliaEvents::CURRENCY_UPDATE_RATES);
        }
        catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToRoute('admin.configuration.currencies.default');
    }

    /**
     * Update currencyposition
     */
    public function updatePositionAction() {
        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.currencies.update")) return $response;

        try {
            $mode = $this->getRequest()->get('mode', null);

            if ($mode == 'up')
                $mode = CurrencyUpdatePositionEvent::POSITION_UP;
            else if ($mode == 'down')
                $mode = CurrencyUpdatePositionEvent::POSITION_DOWN;
            else
                $mode = CurrencyUpdatePositionEvent::POSITION_ABSOLUTE;

            $position = $this->getRequest()->get('position', null);

            $event = new CurrencyUpdatePositionEvent(
                    $this->getRequest()->get('currency_id', null),
                    $mode,
                    $this->getRequest()->get('position', null)
            );

            $this->dispatch(TheliaEvents::CURRENCY_UPDATE_POSITION, $event);
        }
        catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToRoute('admin.configuration.currencies.default');
    }


    /**
     * Delete a currency object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function deleteAction() {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.configuration.currencies.delete")) return $response;

        // Get the currency id, and dispatch the delet request
        $event = new CurrencyDeleteEvent($this->getRequest()->get('currency_id'));

        $this->dispatch(TheliaEvents::CURRENCY_DELETE, $event);

        $this->redirectToRoute('admin.configuration.currencies.default');
    }
}