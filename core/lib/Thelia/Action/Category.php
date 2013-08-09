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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Category as CategoryModel;
use Thelia\Form\CategoryCreationForm;
use Thelia\Core\Event\CategoryEvent;
use Thelia\Tools\Redirect;
use Thelia\Model\CategoryQuery;
use Thelia\Model\AdminLog;
use Thelia\Form\CategoryDeletionForm;
use Thelia\Action\Exception\FormValidationException;
use Propel\Runtime\Exception\PropelException;

class Category extends BaseAction implements EventSubscriberInterface
{
    public function create(ActionEvent $event)
    {
        $request = $event->getRequest();

        try {
            $categoryCreationForm = new CategoryCreationForm($request);

            $form = $this->validateForm($categoryCreationForm, "POST");

            $data = $form->getData();

            $category = new CategoryModel();

               $event->getDispatcher()->dispatch(TheliaEvents::BEFORE_CREATECATEGORY, $event);

               $category->create(
                $data["title"],
                $data["parent"],
                $data["locale"]
            );

               AdminLog::append(sprintf("Category %s (ID %s) created", $category->getTitle(), $category->getId()), $request, $request->getSession()->getAdminUser());

            $categoryEvent = new CategoryEvent($category);

            $event->getDispatcher()->dispatch(TheliaEvents::AFTER_CREATECATEGORY, $categoryEvent);

            // Substitute _ID_ in the URL with the ID of the created category
            $successUrl = str_replace('_ID_', $category->getId(), $categoryCreationForm->getSuccessUrl());

            // Redirect to the success URL
            $this->redirect($successUrl);

        } catch (PropelException $e) {
            Tlog::getInstance()->error(sprintf('error during creating category with message "%s"', $e->getMessage()));

            $message = "Failed to create this category, please try again.";
        }

        // The form has errors, propagate it.
        $this->propagateFormError($categoryCreationForm, $message, $event);
    }

    public function modify(ActionEvent $event)
    {
        /*
        $request = $event->getRequest();

        $customerModification = new CustomerModification($request);

        $form = $customerModification->getForm();

        if ($request->isMethod("post")) {

            $form->bind($request);

            if ($form->isValid()) {
                $data = $form->getData();

                $customer = CustomerQuery::create()->findPk(1);
                try {
                    $customerEvent = new CustomerEvent($customer);
                    $event->getDispatcher()->dispatch(TheliaEvents::BEFORE_CHANGECUSTOMER, $customerEvent);

                    $data = $form->getData();

                    $customer->createOrUpdate(
                        $data["title"],
                        $data["firstname"],
                        $data["lastname"],
                        $data["address1"],
                        $data["address2"],
                        $data["address3"],
                        $data["phone"],
                        $data["cellphone"],
                        $data["zipcode"],
                        $data["country"]
                    );

                    $customerEvent->customer = $customer;
                    $event->getDispatcher()->dispatch(TheliaEvents::AFTER_CHANGECUSTOMER, $customerEvent);

                    // Update the logged-in user, and redirect to the success URL (exits)
                    // We don-t send the login event, as the customer si already logged.
                    $this->processSuccessfullLogin($event, $customer, $customerModification);
                 } catch (PropelException $e) {

                    Tlog::getInstance()->error(sprintf('error during modifying customer on action/modifyCustomer with message "%s"', $e->getMessage()));

                    $message = "Failed to change your account, please try again.";
                }
            } else {
                $message = "Missing or invalid data";
            }
        } else {
            $message = "Wrong form method !";
        }

        // The form has an error
        $customerModification->setError(true);
        $customerModification->setErrorMessage($message);

        // Dispatch the errored form
        $event->setErrorForm($customerModification);
        */
    }

    /**
     * Delete a category
     *
     * @param ActionEvent $event
     */
    public function delete(ActionEvent $event)
    {
        $request = $event->getRequest();

        try {
            $categoryDeletionForm = new CategoryDeletionForm($request);

            $form = $this->validateForm($categoryDeletionForm, "POST");

            $data = $form->getData();

            $category = CategoryQuery::create()->findPk($data['id']);

            $categoryEvent = new CategoryEvent($category);

            $event->getDispatcher()->dispatch(TheliaEvents::BEFORE_DELETECATEGORY, $categoryEvent);

            $category->delete();

            AdminLog::append(sprintf("Category %s (ID %s) deleted", $category->getTitle(), $category->getId()), $request, $request->getSession()->getAdminUser());

            $categoryEvent->category = $category;

            $event->getDispatcher()->dispatch(TheliaEvents::AFTER_DELETECATEGORY, $categoryEvent);

            // Substitute _ID_ in the URL with the ID of the created category
            $successUrl = str_replace('_ID_', $category->getParent(), $categoryDeletionForm->getSuccessUrl());

            // Redirect to the success URL
            Redirect::exec($successUrl);
        } catch (PropelException $e) {

            \Thelia\Log\Tlog::getInstance()->error(sprintf('error during deleting category ID=%s on action/modifyCustomer with message "%s"', $data['id'], $e->getMessage()));

            $message = "Failed to change your account, please try again.";
        } catch (FormValidationException $e) {

             $message = $e->getMessage();
        }

        $this->propagateFormError($categoryDeletionForm, $message, $event);
    }

    /**
     * Toggle category visibility. No form used here
     *
     * @param ActionEvent $event
     */
    public function toggleVisibility(ActionEvent $event)
    {
        $request = $event->getRequest();

        $category = CategoryQuery::create()->findPk($request->get('id', 0));

        if ($category !== null) {

            $category->setVisible($category->getVisible() ? false : true);

            $category->save();

            $categoryEvent = new CategoryEvent($category);

            $event->getDispatcher()->dispatch(TheliaEvents::AFTER_CHANGECATEGORY, $categoryEvent);
        }
    }

    /**
     * Returns an array of event names this subscriber listens to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            "action.createCategory" => array("create", 128),
            "action.modifyCategory" => array("modify", 128),
            "action.deleteCategory" => array("delete", 128),

            "action.toggleCategoryVisibility" => array("toggleVisibility", 128),
        );
    }
}
