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

class CategoryController extends BaseAdminController
{
    protected function createNewCategory($args)
    {
         try {
            $categoryCreationForm = new CategoryCreationForm($this->getRequest());

            $form = $this->validateForm($categoryCreationForm, "POST");

            $data = $form->getData();

            $categoryCreateEvent = new CategoryCreateEvent(
                $data["title"],
                $data["parent"],
                $data["locale"]
            );

            $this->dispatch(TheliaEvents::CATEGORY_CREATE, $categoryCreateEvent);

            $category = $categoryCreateEvent->getCreatedCategory();

            $this->adminLogAppend(sprintf("Category %s (ID %s) created", $category->getTitle(), $category->getId()));

            // Substitute _ID_ in the URL with the ID of the created category
            $successUrl = str_replace('_ID_', $category->getId(), $categoryCreationForm->getSuccessUrl());

            // Redirect to the success URL
            $this->redirect($successUrl);
        }
        catch (FormValidationException $e) {
            $categoryCreationForm->setErrorMessage($e->getMessage());
            $this->getParserContext()->setErrorForm($categoryCreationForm);
        }
        catch (Exception $e) {
           Tlog::getInstance()->error(sprintf("Failed to create category: %s", $e->getMessage()));
            $this->getParserContext()->setGeneralError($e->getMessage());
        }

        // At this point, the form has error, and should be redisplayed.
        return $this->render('categories', $args);
    }

    protected function editCategory($args)
    {
        $this->checkAuth("ADMIN", "admin.category.edit");

        return $this->render('edit_category', $args);
    }

    protected function deleteCategory($args)
    {
        try {
            $categoryDeletionForm = new CategoryDeletionForm($this->getRequest());

            $data = $this->validateForm($categoryDeletionForm, "POST")->getData();

            $categoryDeleteEvent = new CategoryDeleteEvent($data['category_id']);

            $this->dispatch(TheliaEvents::CATEGORY_DELETE, $categoryDeleteEvent);

            $category = $categoryDeleteEvent->getDeletedCategory();

            $this->adminLogAppend(sprintf("Category %s (ID %s) deleted", $category->getTitle(), $category->getId()));

            // Substitute _ID_ in the URL with the ID of the created category
            $successUrl = str_replace('_ID_', $categoryDeleteEvent->getDeletedCategory()->getId(), $categoryDeletionForm->getSuccessUrl());

            // Redirect to the success URL
            $this->redirect($successUrl);
        }
        catch (FormValidationException $e) {
            $categoryDeletionForm->setErrorMessage($e->getMessage());
            $this->getParserContext()->setErrorForm($categoryDeletionForm);
        }
       catch (Exception $e) {
            Tlog::getInstance()->error(sprintf("Failed to delete category: %s", $e->getMessage()));
            $this->getParserContext()->setGeneralError($e->getMessage());
        }

        // At this point, something was wrong, category was not deleted. Display parent category list
        return $this->render('categories', $args);
    }

    protected function browseCategory($args)
    {
        $this->checkAuth("AMIN", "admin.catalog.view");

        return $this->render('categories', $args);
    }

    protected function visibilityToggle($args)
    {
        $event = new CategoryToggleVisibilityEvent($this->getRequest()->get('category_id', 0));

        $this->dispatch(TheliaEvents::CATEGORY_TOGGLE_VISIBILITY, $event);

        return $this->nullResponse();
    }

    protected function changePosition($args)
    {
        $request = $this->getRequest();

        $event = new CategoryChangePositionEvent(
                $request->get('category_id', 0),
                CategoryChangePositionEvent::POSITION_ABSOLUTE,
                $request->get('position', null)
        );

        $this->dispatch(TheliaEvents::CATEGORY_CHANGE_POSITION, $event);

        return $this->render('categories', $args);
    }

    protected function positionDown($args)
    {
        $event = new CategoryChangePositionEvent(
            $this->getRequest()->get('category_id', 0),
            CategoryChangePositionEvent::POSITION_DOWN
        );

        $this->dispatch(TheliaEvents::CATEGORY_CHANGE_POSITION, $event);

        return $this->render('categories', $args);
    }

    protected function positionUp($args)
    {
        $event = new CategoryChangePositionEvent(
                $this->getRequest()->get('category_id', 0),
                CategoryChangePositionEvent::POSITION_UP
        );

        $this->dispatch(TheliaEvents::CATEGORY_CHANGE_POSITION, $event);

        return $this->render('categories', $args);
    }

    public function indexAction()
    {
        return $this->processAction();
    }

    public function processAction()
    {
        // Get the current action
        $action = $this->getRequest()->get('action', 'browse');

        // Get the category ID
        $id = $this->getRequest()->get('id', 0);

        $args = array(
            'action' 			  => $action,
            'current_category_id' => $id
        );

        try {
            switch ($action) {
                case 'browse' : // Browse categories

                    return $this->browseCategory($args);

                case 'create' : // Create a new category

                    return $this->createNewCategory($args);

                case 'edit' : // Edit an existing category

                    return $this->editCategory($args);

                case 'delete' : // Delete an existing category

                    return $this->deleteCategory($args);

                case 'visibilityToggle' : // Toggle visibility

                    return $this->visibilityToggle($id);

                case 'changePosition' : // Change position

                    return $this->changePosition($args);

                case 'positionUp' : // Move up category

                    return $this->positionUp($args);

                case 'positionDown' : // Move down category

                    return $this->positionDown($args);
            }
        }
        catch (AuthorizationException $ex) {
            return $this->errorPage($ex->getMessage());
        }
        catch (AuthenticationException $ex) {
            return $this->errorPage($ex->getMessage());
        }

        // We did not recognized the action -> return a 404 page
        return $this->pageNotFound();
    }
}
