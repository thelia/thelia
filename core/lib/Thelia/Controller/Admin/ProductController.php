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

use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Model\ProductQuery;
use Thelia\Form\ProductModificationForm;
use Thelia\Form\ProductCreationForm;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\Product\ProductToggleVisibilityEvent;
use Thelia\Core\Event\Product\ProductDeleteContentEvent;
use Thelia\Core\Event\Product\ProductAddContentEvent;
use Thelia\Model\ProductAssociatedContent;
use Thelia\Model\FolderQuery;
use Thelia\Model\ContentQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Core\Event\Product\ProductAddAccessoryEvent;
use Thelia\Core\Event\Product\ProductDeleteAccessoryEvent;

/**
 * Manages products
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ProductController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'product',
            'manual',
            'product_order',

            'admin.products.default',
            'admin.products.create',
            'admin.products.update',
            'admin.products.delete',

            TheliaEvents::PRODUCT_CREATE,
            TheliaEvents::PRODUCT_UPDATE,
            TheliaEvents::PRODUCT_DELETE,

            TheliaEvents::PRODUCT_TOGGLE_VISIBILITY,
            TheliaEvents::PRODUCT_UPDATE_POSITION
        );
    }

    protected function getCreationForm()
    {
        return new ProductCreationForm($this->getRequest());
    }

    protected function getUpdateForm()
    {
        return new ProductModificationForm($this->getRequest());
    }

    protected function getCreationEvent($formData)
    {
        $createEvent = new ProductCreateEvent();

        $createEvent
            ->setRef($formData['ref'])
            ->setTitle($formData['title'])
            ->setLocale($formData['locale'])
            ->setDefaultCategory($formData['default_category'])
            ->setVisible($formData['visible'])
        ;

        return $createEvent;
    }

    protected function getUpdateEvent($formData)
    {
        $changeEvent = new ProductUpdateEvent($formData['id']);

        // Create and dispatch the change event
        $changeEvent
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
            ->setVisible($formData['visible'])
            ->setUrl($formData['url'])
            ->setParent($formData['parent'])
        ;

        return $changeEvent;
    }

    protected function createUpdatePositionEvent($positionChangeMode, $positionValue)
    {
        return new UpdatePositionEvent(
                $this->getRequest()->get('product_id', null),
                $positionChangeMode,
                $positionValue
        );
    }

    protected function getDeleteEvent()
    {
        return new ProductDeleteEvent($this->getRequest()->get('product_id', 0));
    }

    protected function eventContainsObject($event)
    {
        return $event->hasProduct();
    }

    protected function hydrateObjectForm($object)
    {
        // Prepare the data that will hydrate the form
        $data = array(
            'id'               => $object->getId(),
            'locale'           => $object->getLocale(),
            'title'            => $object->getTitle(),
            'chapo'            => $object->getChapo(),
            'description'      => $object->getDescription(),
            'postscriptum'     => $object->getPostscriptum(),
            'visible'          => $object->getVisible(),
            'url'              => $object->getRewrittenUrl($this->getCurrentEditionLocale()),
            'default_category' => $object->getDefaultCategoryId()
        );

        // Setup the object form
        return new ProductModificationForm($this->getRequest(), "form", $data);
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasProduct() ? $event->getProduct() : null;
    }

    protected function getExistingObject()
    {
        return ProductQuery::create()
        ->joinWithI18n($this->getCurrentEditionLocale())
        ->findOneById($this->getRequest()->get('product_id', 0));
    }

    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function getEditionArguments()
    {
        return array(
                'category_id'          => $this->getCategoryId(),
                'product_id'           => $this->getRequest()->get('product_id', 0),
                'folder_id'            => $this->getRequest()->get('folder_id', 0),
                'accessory_category_id'=> $this->getRequest()->get('accessory_category_id', 0),
                'current_tab'          => $this->getRequest()->get('current_tab', 'general')
        );
    }

    protected function getCategoryId() {
        // Trouver le category_id, soit depuis la reques, souit depuis le produit courant
        $category_id = $this->getRequest()->get('category_id', null);

        if ($category_id == null) {
            $product = $this->getExistingObject();

            if ($product !== null) $category_id = $product->getDefaultCategoryId();
        }

        return $category_id != null ? $category_id : 0;
    }

    protected function renderListTemplate($currentOrder)
    {
        $this->getListOrderFromSession('product', 'product_order', 'manual');

        return $this->render('categories',
                array(
                    'product_order' => $currentOrder,
                    'category_id' => $this->getCategoryId()
        ));
    }

    protected function redirectToListTemplate()
    {
        $this->redirectToRoute(
                'admin.products.default',
                array('category_id' => $this->getCategoryId())
        );
    }

    protected function renderEditionTemplate()
    {
        return $this->render('product-edit', $this->getEditionArguments());
    }

    protected function redirectToEditionTemplate()
    {
        $this->redirectToRoute("admin.products.update", $this->getEditionArguments());
    }

    /**
     * Online status toggle product
     */
    public function setToggleVisibilityAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.products.update")) return $response;

        $event = new ProductToggleVisibilityEvent($this->getExistingObject());

        try {
            $this->dispatch(TheliaEvents::PRODUCT_TOGGLE_VISIBILITY, $event);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        // Ajax response -> no action
        return $this->nullResponse();
    }

    protected function performAdditionalDeleteAction($deleteEvent)
    {
        // Redirect to parent product list
        $this->redirectToRoute(
                'admin.products.default',
                array('category_id' => $this->getCategoryId())
        );
    }

    protected function performAdditionalUpdateAction($updateEvent)
    {
        if ($this->getRequest()->get('save_mode') != 'stay') {

            // Redirect to parent product list
            $this->redirectToRoute(
                    'admin.categories.default',
                    array('category_id' => $this->getCategoryId())
            );
        }
    }

    protected function performAdditionalUpdatePositionAction($positionEvent)
    {
        // Redirect to parent product list
        $this->redirectToRoute(
                'admin.categories.default',
                array('category_id' => $this->getCategoryId())
        );
    }

    // -- Related content management -------------------------------------------

    public function getAvailableRelatedContentAction($productId, $folderId)
    {
        $result = array();

        $folders = FolderQuery::create()->filterById($folderId)->find();

        if ($folders !== null) {

            $list = ContentQuery::create()
                ->joinWithI18n($this->getCurrentEditionLocale())
                ->filterByFolder($folders, Criteria::IN)
                ->filterById(ProductAssociatedContentQuery::create()->select('content_id')->findByProductId($productId), Criteria::NOT_IN)
                ->find();
                ;

            if ($list !== null) {
                foreach($list as $item) {
                    $result[] = array('id' => $item->getId(), 'title' => $item->getTitle());
                }
            }
        }

        return $this->jsonResponse(json_encode($result));
    }

    public function addRelatedContentAction()
    {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.products.update")) return $response;

        $content_id = intval($this->getRequest()->get('content_id'));

        if ($content_id > 0) {

            $event = new ProductAddContentEvent(
                    $this->getExistingObject(),
                    $content_id
            );

            try {
                $this->dispatch(TheliaEvents::PRODUCT_ADD_CONTENT, $event);
            }
            catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        $this->redirectToEditionTemplate();
    }

    public function deleteRelatedContentAction()
    {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.products.update")) return $response;

        $content_id = intval($this->getRequest()->get('content_id'));

        if ($content_id > 0) {

            $event = new ProductDeleteContentEvent(
                    $this->getExistingObject(),
                    $content_id
            );

            try {
                $this->dispatch(TheliaEvents::PRODUCT_REMOVE_CONTENT, $event);
            }
            catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        $this->redirectToEditionTemplate();
    }


    // -- Accessories management ----------------------------------------------

    public function getAvailableAccessoriesAction($productId, $categoryId)
    {
        $result = array();

        $categories = CategoryQuery::create()->filterById($categoryId)->find();

        if ($categories !== null) {

            $list = ProductQuery::create()
            ->joinWithI18n($this->getCurrentEditionLocale())
            ->filterByCategory($categories, Criteria::IN)
            ->filterById(AccessoryQuery::create()->select('accessory')->findByProductId($productId), Criteria::NOT_IN)
            ->find();
            ;

            if ($list !== null) {
                foreach($list as $item) {
                    $result[] = array('id' => $item->getId(), 'title' => $item->getTitle());
                }
            }
        }

        return $this->jsonResponse(json_encode($result));
    }

    public function addAccessoryAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.products.update")) return $response;

        $accessory_id = intval($this->getRequest()->get('accessory_id'));

        if ($accessory_id > 0) {

            $event = new ProductAddAccessoryEvent(
                    $this->getExistingObject(),
                    $accessory_id
            );

            try {
                $this->dispatch(TheliaEvents::PRODUCT_ADD_ACCESSORY, $event);
            }
            catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        $this->redirectToEditionTemplate();
    }

    public function deleteAccessoryAction()
    {

        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.products.update")) return $response;

        $accessory_id = intval($this->getRequest()->get('accessory_id'));

        if ($accessory_id > 0) {

            $event = new ProductDeleteAccessoryEvent(
                    $this->getExistingObject(),
                    $accessory_id
            );

            try {
                $this->dispatch(TheliaEvents::PRODUCT_REMOVE_ACCESSORY, $event);
            }
            catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        $this->redirectToEditionTemplate();
    }

    /**
     * Update accessory position (only for objects whichsupport that)
     */
    public function updateAccessoryPositionAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth('admin.products.update')) return $response;

        try {
            $mode = $this->getRequest()->get('mode', null);

            if ($mode == 'up')
                $mode = UpdatePositionEvent::POSITION_UP;
            else if ($mode == 'down')
                $mode = UpdatePositionEvent::POSITION_DOWN;
            else
                $mode = UpdatePositionEvent::POSITION_ABSOLUTE;

            $position = $this->getRequest()->get('position', null);

            $event = new UpdatePositionEvent($mode, $position);

            $this->dispatch(TheliaEvents::PRODUCT_UPDATE_ACCESSORY_POSITION, $event);
        }
        catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToEditionTemplate();
    }

}
