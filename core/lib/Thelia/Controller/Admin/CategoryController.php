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

use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\CategoryCreateEvent;
use Thelia\Form\CategoryCreationForm;
use Thelia\Core\Event\CategoryDeleteEvent;
use Thelia\Core\Event\CategoryUpdatePositionEvent;
use Thelia\Model\CategoryQuery;
use Thelia\Form\CategoryModificationForm;

class CategoryController extends BaseAdminController
{
    /**
     * Render the categories list, ensuring the sort order is set.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    protected function renderList()
    {
        return $this->render('categories', $this->getTemplateArgs());
    }

    protected function getTemplateArgs()
    {
        // Get the category ID
        $category_id = $this->getRequest()->get('category_id', 0);

        // Find the current category order
        $category_order = $this->getRequest()->get(
                'order',
                $this->getSession()->get('admin.category_order', 'manual')
        );

        $args = array(
            'current_category_id' => $category_id,
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
    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth("admin.categories.view")) return $response;
        return $this->renderList();
    }

    /**
     * Create a new category object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function createAction()
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

            $createEvent = new CategoryCreateEvent(
                $data["title"],
                $data["parent"],
                $data["locale"]
            );

            $this->dispatch(TheliaEvents::CATEGORY_CREATE, $createEvent);

            if (! $createEvent->hasCategory()) throw new \LogicException($this->getTranslator()->trans("No category was created."));

            $createdObject = $createEvent->getCategory();

            // Log category creation
            $this->adminLogAppend(sprintf("Category %s (ID %s) created", $createdObject->getTitle(), $createdObject->getId()));

            // Substitute _ID_ in the URL with the ID of the created object
            $successUrl = str_replace('_ID_', $createdObject->getId(), $creationForm->getSuccessUrl());

            // Redirect to the success URL
            $this->redirect($successUrl);
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext("category creation", $error_msg, $creationForm, $ex);

        // At this point, the form has error, and should be redisplayed.
        return $this->renderList();
    }

    /**
     * Load a category object for modification, and display the edit template.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function changeAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.categories.update")) return $response;

        // Load the category object
        $category = CategoryQuery::create()
            ->joinWithI18n($this->getCurrentEditionLocale())
            ->findOneById($this->getRequest()->get('category_id'));

        if ($category != null) {

            // Prepare the data that will hydrate the form
            $data = array(
                    'id'     => $category->getId(),
                    'locale' => $category->getLocale(),
                    'title' => $category->getTitle(),
                    'chapo' => $category->getChapo(),
                    'description' => $category->getDescription(),
                    'postscriptum' => $category->getPostscriptum(),
                    'parent' => $category->getParent(),
                    'visible' => $category->getVisible() ? true : false,
                    'url' => $category->getUrl($this->getCurrentEditionLocale())
                    // tbc !!!
            );

            // Setup the object form
            $changeForm = new CategoryModificationForm($this->getRequest(), "form", $data);

            // Pass it to the parser
            $this->getParserContext()->addForm($changeForm);
        }

        // Render the edition template.
        return $this->render('category-edit', $this->getTemplateArgs());
    }

    /**
     * Save changes on a modified category object, and either go back to the category list, or stay on the edition page.
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function saveChangeAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.categories.update")) return $response;

        $error_msg = false;

        // Create the form from the request
        $changeForm = new CategoryModificationForm($this->getRequest());

        // Get the category ID
        $category_id = $this->getRequest()->get('category_id');

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

            if (! $createEvent->hasCategory()) throw new \LogicException($this->getTranslator()->trans("No category was updated."));

            // Log category modification
            $changedObject = $changeEvent->getCategory();

            $this->adminLogAppend(sprintf("Category %s (ID %s) modified", $changedObject->getTitle(), $changedObject->getId()));

            // If we have to stay on the same page, do not redirect to the succesUrl,
            // just redirect to the edit page again.
            if ($this->getRequest()->get('save_mode') == 'stay') {
                $this->redirectToRoute(
                        "admin.categories.update",
                        array('category_id' => $category_id)
                );
            }

            // Redirect to the success URL
            $this->redirect($changeForm->getSuccessUrl());
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext("category modification", $error_msg, $changeForm, $ex);

        // At this point, the form has errors, and should be redisplayed.
        return $this->render('category-edit', array('category_id' => $category_id));
    }

    /**
     * Online status toggle category
     */
    public function setToggleVisibilityAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.categories.update")) return $response;

        $changeEvent = new CategoryUpdateEvent($this->getRequest()->get('category_id', 0));

        // Create and dispatch the change event
        $changeEvent->setIsDefault(true);

        try {
            $this->dispatch(TheliaEvents::CATEGORY_SET_DEFAULT, $changeEvent);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToRoute('admin.categories.default');
    }

    /**
     * Update categoryposition
     */
    public function updatePositionAction()
    {
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
                    $this->getRequest()->get('category_id', null),
                    $mode,
                    $this->getRequest()->get('position', null)
            );

            $this->dispatch(TheliaEvents::CATEGORY_UPDATE_POSITION, $event);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToRoute('admin.categories.default');
    }

    /**
     * Delete a category object
     *
     * @return Symfony\Component\HttpFoundation\Response the response
     */
    public function deleteAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.categories.delete")) return $response;

        // Get the category id, and dispatch the deleted request
        $event = new CategoryDeleteEvent($this->getRequest()->get('category_id'));

        $this->dispatch(TheliaEvents::CATEGORY_DELETE, $event);

        if ($event->hasCategory())
            $this->adminLogAppend(sprintf("Category %s (ID %s) deleted", $event->getCategory()->getTitle(), $event->getCategory()->getId()));

        $this->redirectToRoute('admin.categories.default');
    }
}
