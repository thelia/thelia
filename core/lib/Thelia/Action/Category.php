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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Model\Map\CategoryTableMap;
use Propel\Runtime\Exception\PropelException;

class Category extends BaseAction implements EventSubscriberInterface
{
    public function create(ActionEvent $event)
    {

        $this->checkAuth("ADMIN", "admin.category.create");

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

        $this->checkAuth("ADMIN", "admin.category.delete");

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

    }

    /**
     * Delete a category
     *
     * @param ActionEvent $event
     */
    public function delete(ActionEvent $event)
    {

        $this->checkAuth("ADMIN", "admin.category.delete");

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

        $this->checkAuth("ADMIN", "admin.category.edit");

        $request = $event->getRequest();

        $category = CategoryQuery::create()->findPk($request->get('category_id', 0));

        if ($category !== null) {

            $category->setVisible($category->getVisible() ? false : true);

            $category->save();

            $categoryEvent = new CategoryEvent($category);

            $event->getDispatcher()->dispatch(TheliaEvents::AFTER_CHANGECATEGORY, $categoryEvent);
        }
    }

    /**
     * Move category up
     *
     * @param ActionEvent $event
     */
    public function changePositionUp(ActionEvent $event)
    {
        return $this->exchangePosition($event, 'up');
    }

    /**
     * Move category down
     *
     * @param ActionEvent $event
     */
    public function changePositionDown(ActionEvent $event)
    {
        return $this->exchangePosition($event, 'down');
    }

    /**
     * Move up or down a category
     *
     * @param ActionEvent $event
     * @param string      $direction up to move up, down to move down
     */
    protected function exchangePosition(ActionEvent $event, $direction)
    {
        $this->checkAuth("ADMIN", "admin.category.edit");

        $request = $event->getRequest();

        $category = CategoryQuery::create()->findPk($request->get('category_id', 0));

        if ($category !== null) {

            // The current position of the category
            $my_position = $category->getPosition();

            // Find category to exchange position with
            $search = CategoryQuery::create()
                ->filterByParent($category->getParent());

            // Up or down ?
            if ($direction == 'up') {
                // Find the category immediately before me
                $search->filterByPosition(array('max' => $my_position-1))->orderByPosition(Criteria::DESC);
            } elseif ($direction == 'down') {
                // Find the category immediately after me
                $search->filterByPosition(array('min' => $my_position+1))->orderByPosition(Criteria::ASC);
            } else

                return;

            $result = $search->findOne();

            // If we found the proper category, exchange their positions
            if ($result) {

                $cnx = Propel::getWriteConnection(CategoryTableMap::DATABASE_NAME);

                $cnx->beginTransaction();

                try {
                    $category->setPosition($result->getPosition())->save();

                    $result->setPosition($my_position)->save();

                    $cnx->commit();
                } catch (Exception $e) {
                    $cnx->rollback();
                }
            }
        }
    }

    /**
     * Changes category position
     *
     * @param ActionEvent $event
     */
    public function changePosition(ActionEvent $event)
    {
        $this->checkAuth("ADMIN", "admin.category.edit");

        $request = $event->getRequest();

        $category = CategoryQuery::create()->findPk($request->get('category_id', 0));

        if ($category !== null) {

            // The required position
            $new_position = $request->get('position', null);

            // The current position
            $current_position = $category->getPosition();

            if ($new_position != null && $new_position > 0 && $new_position != $current_position) {

                 // Find categories to offset
                $search = CategoryQuery::create()->filterByParent($category->getParent());

                if ($new_position > $current_position) {
                    // The new position is after the current position -> we will offset + 1 all categories located between us and the new position
                    $search->filterByPosition(array('min' => 1+$current_position, 'max' => $new_position));

                    $delta = -1;
                } else {
                    // The new position is brefore the current position -> we will offset - 1 all categories located between us and the new position
                    $search->filterByPosition(array('min' => $new_position, 'max' => $current_position - 1));

                    $delta = 1;
                }

                $results = $search->find();

                $cnx = Propel::getWriteConnection(CategoryTableMap::DATABASE_NAME);

                $cnx->beginTransaction();

                try {
                    foreach ($results as $result) {
                        $result->setPosition($result->getPosition() + $delta)->save($cnx);
                    }

                    $category->setPosition($new_position)->save($cnx);

                    $cnx->commit();
                } catch (Exception $e) {
                    $cnx->rollback();
                }
            }
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

            "action.toggleCategoryVisibility" 	=> array("toggleVisibility", 128),
            "action.changeCategoryPositionUp" 	=> array("changePositionUp", 128),
            "action.changeCategoryPositionDown" => array("changePositionDown", 128),
            "action.changeCategoryPosition" 	=> array("changePosition", 128),
        );
    }
}
