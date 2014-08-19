<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Controller\Admin;
use Thelia\Core\Event\Sale\SaleClearStatusEvent;
use Thelia\Core\Event\Sale\SaleCreateEvent;
use Thelia\Core\Event\Sale\SaleDeleteEvent;
use Thelia\Core\Event\Sale\SaleEvent;
use Thelia\Core\Event\Sale\SaleToggleActivityEvent;
use Thelia\Core\Event\Sale\SaleUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Sale\SaleCreationForm;
use Thelia\Form\Sale\SaleModificationForm;
use Thelia\Model\AttributeCombination;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Sale;
use Thelia\Model\SaleProduct;
use Thelia\Model\SaleQuery;

/**
 * Class SaleController
 * @package Thelia\Controller\Admin
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class SaleController extends AbstractCrudController
{

    public function __construct()
    {
        parent::__construct(
            'sale',
            'start-date',
            'order',

            AdminResources::SALES,

            TheliaEvents::SALE_CREATE,
            TheliaEvents::SALE_UPDATE,
            TheliaEvents::SALE_DELETE
        );
    }

    /**
     * Return the creation form for this object
     */
    protected function getCreationForm()
    {
        return new SaleCreationForm($this->getRequest());
    }

    /**
     * Return the update form for this object
     */
    protected function getUpdateForm()
    {
        return new SaleModificationForm($this->getRequest());
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param  Sale                 $sale
     * @return SaleModificationForm
     */
    protected function hydrateObjectForm($sale)
    {
        // Find all categories of the selected products
        $saleProducts = $sale->getSaleProductList();

        $categories = $products = [ ];

        /** @var SaleProduct $saleProduct */
        foreach($saleProducts as $saleProduct) {
            $categories[] = $saleProduct->getProduct()->getDefaultCategoryId();
            $products[] = $saleProduct->getProduct()->getId();
        }

        $dateFormat = SaleModificationForm::PHP_DATE_FORMAT;

        // Prepare the data that will hydrate the form
        $data = [
            'id'            => $sale->getId(),
            'locale'        => $sale->getLocale(),
            'title'         => $sale->getTitle(),
            'label'         => $sale->getSaleLabel(),
            'chapo'         => $sale->getChapo(),
            'description'   => $sale->getDescription(),
            'postscriptum'  => $sale->getPostscriptum(),
            'active'        => $sale->getActive(),
            'display_initial_price' => $sale->getActive(),
            'start_date'            => $sale->getStartDate($dateFormat),
            'end_date'              => $sale->getEndDate($dateFormat),
            'price_offset_type'     => $sale->getPriceOffsetType(),
            'price_offset'          => $sale->getPriceOffsets(),
            'categories'            => $categories,
            'products'              => $products,
            'product_attributes'    => array() // $sale->getSaleProductsAttributeList()
        ];

        // Setup the object form
        return new SaleModificationForm($this->getRequest(), "form", $data);
    }

    /**
     * Creates the creation event with the provided form data
     *
     * @param  array            $formData
     * @return SaleCreateEvent
     */
    protected function getCreationEvent($formData)
    {
        $saleCreateEvent = new SaleCreateEvent();

        $saleCreateEvent
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setSaleLabel($formData['label'])
        ;

        return $saleCreateEvent;
    }

    /**
     * Creates the update event with the provided form data
     *
     * @param  array            $formData
     * @return SaleUpdateEvent
     */
    protected function getUpdateEvent($formData)
    {
        // Build the products array
        $products = [];

        foreach($formData['products'] as $productId) {
            $products[$productId] = [];
            /*
            if (isset($formData['product_attributes'])) {
                $products[]
            }
            */
        }

        $saleUpdateEvent = new SaleUpdateEvent($formData['id']);

        $saleUpdateEvent
            ->setStartDate($formData['start_date'])
            ->setEndDate($formData['end_date'])
            ->setActive($formData['active'])
            ->setPriceOffsetType($formData['price_offset_type'])
            ->setPriceOffsets($formData['price_offset'])
            ->setProducts($products)
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setSaleLabel($formData['label'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
        ;

        return $saleUpdateEvent;
    }

    /**
     * Creates the delete event with the provided form data
     *
     * @return SaleDeleteEvent
     */
    protected function getDeleteEvent()
    {
        return new SaleDeleteEvent($this->getRequest()->get('sale_id'));
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param  SaleEvent $event
     * @return bool
     */
    protected function eventContainsObject($event)
    {
        return $event->hasSale();
    }

    /**
     * Get the created object from an event.
     *
     * @param $event \Thelia\Core\Event\Sale\SaleEvent
     *
     * @return null|\Thelia\Model\Sale
     */
    protected function getObjectFromEvent($event)
    {
        return $event->getSale();
    }

    /**
     * Load an existing object from the database
     *
     * @return \Thelia\Model\Sale
     */
    protected function getExistingObject()
    {
        $sale = SaleQuery::create()
            ->findOneById($this->getRequest()->get('sale_id', 0));

        if (null !== $sale) {
            $sale->setLocale($this->getCurrentEditionLocale());
        }

        return $sale;
    }

    /**
     * Returns the object label form the object event (name, title, etc.)
     *
     * @param $object \Thelia\Model\Sale
     *
     * @return string sale title
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * Returns the object ID from the object
     *
     * @param $object \Thelia\Model\Sale
     *
     * @return int sale id
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    /**
     * Render the main list template
     *
     * @param string $currentOrder, if any, null otherwise.
     *
     * @return Response
     */
    protected function renderListTemplate($currentOrder)
    {
        $this->getListOrderFromSession('sale', 'order', 'start-date');

        return $this->render('sales', [
                'order' => $currentOrder,
            ]);
    }

    protected function getEditionArguments()
    {
        return [
            'sale_id' => $this->getRequest()->get('sale_id', 0)
        ];
    }

    /**
     * Render the edition template
     */
    protected function renderEditionTemplate()
    {
        return $this->render('sale-edit', $this->getEditionArguments());
    }

    /**
     * Redirect to the edition template
     */
    protected function redirectToEditionTemplate()
    {
        $this->redirect($this->getRoute('admin.sale.update', $this->getEditionArguments()));
    }

    /**
     * Redirect to the list template
     */
    protected function redirectToListTemplate()
    {
        $this->redirectToRoute('admin.sale.default');
    }

    /**
     * Toggle activity status of the sale.
     *
     * @return Response
     */
    protected function toggleActivity()
    {
        try {
            $this->dispatch(
                TheliaEvents::SALE_TOGGLE_ACTIVITY,
                new SaleToggleActivityEvent(
                    $this->getExistingObject()
                )
            );
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        return $this->nullResponse();
    }

    public function updateProductList()
    {
        // Build the list of categories
        $categories = '';

        foreach($this->getRequest()->get('categories', []) as $category_id) {
            $categories .=  $category_id . ',';
        }

        return $this->render('ajax/sale-edit-products',[
                'sale_id'       => $this->getRequest()->get('sale_id'),
                'category_list' => rtrim($categories, ','),
                'product_list'  => $this->getRequest()->get('products', [])
            ]);
    }

    public function updateProductAttributes()
    {
        $attributesInfo = [];

        $productId = $this->getRequest()->get('product_id');

        // Get PSE for this product
        if (null !== $pseList = ProductSaleElementsQuery::create()->filterByProductId($productId)->find()) {

            /** @var ProductSaleElements $pse */
            foreach($pseList as $pse) {

                // Find all combinations
                if (null !== $combinations = AttributeCombinationQuery::create()->filterByProductSaleElementsId($pse->getId())) {

                    /** @var AttributeCombination $combination */
                    foreach($combinations as $combination) {

                        $attrId = $combination->getAttributeId();
                        $attrAvId = $combination->getAttributeAvId();

                        // Store each AttributeAv
                        if (! isset($attributesInfo[$attrId])) {

                            $attributesInfo[$attrId] = [];
                        }

                        if (! in_array($attrAvId, $attributesInfo[$attrId])) {
                            $attributesInfo[$attrId][] = $attrAvId;
                        }
                    }
                };
            }
        }

        return $this->render('ajax/sale-edit-product-attributes',[
            'product_id'       => $productId,
            'attributes_info'   => $attributesInfo
        ]);
    }

    public function resetSaleStatus()
    {
        try {
            $this->dispatch(
                TheliaEvents::SALE_CLEAR_SALE_STATUS,
                new SaleClearStatusEvent()
            );
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToListTemplate();
    }
}
