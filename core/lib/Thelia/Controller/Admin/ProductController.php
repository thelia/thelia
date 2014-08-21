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

use Symfony\Component\HttpFoundation\JsonResponse;
use Propel\Runtime\ActiveQuery\Criteria;

use Thelia\Core\Event\FeatureProduct\FeatureProductDeleteEvent;
use Thelia\Core\Event\FeatureProduct\FeatureProductUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductAddCategoryEvent;
use Thelia\Core\Event\Product\ProductDeleteCategoryEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\Product\ProductToggleVisibilityEvent;
use Thelia\Core\Event\Product\ProductDeleteContentEvent;
use Thelia\Core\Event\Product\ProductAddContentEvent;
use Thelia\Core\Event\Product\ProductAddAccessoryEvent;
use Thelia\Core\Event\Product\ProductDeleteAccessoryEvent;
use Thelia\Core\Event\Product\ProductCombinationGenerationEvent;
use Thelia\Core\Event\Product\ProductSetTemplateEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementDeleteEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;

use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\AccessManager;

use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\FeatureQuery;
use Thelia\Model\FeatureTemplateQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\AttributeQuery;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductPrice;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Country;
use Thelia\Model\Product;

use Thelia\Form\ProductCreationForm;
use Thelia\Form\ProductModificationForm;
use Thelia\Form\ProductSaleElementUpdateForm;
use Thelia\Form\ProductDefaultSaleElementUpdateForm;
use Thelia\Form\ProductCombinationGenerationForm;

use Thelia\Model\TaxRuleQuery;
use Thelia\TaxEngine\Calculator;
use Thelia\Tools\NumberFormat;

/**
 * Manages products
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ProductController extends AbstractSeoCrudController
{
    public function __construct()
    {
        parent::__construct(
            'product',
            'manual',
            'product_order',

            AdminResources::PRODUCT,

            TheliaEvents::PRODUCT_CREATE,
            TheliaEvents::PRODUCT_UPDATE,
            TheliaEvents::PRODUCT_DELETE,

            TheliaEvents::PRODUCT_TOGGLE_VISIBILITY,
            TheliaEvents::PRODUCT_UPDATE_POSITION,
            TheliaEvents::PRODUCT_UPDATE_SEO
        );
    }

    /**
     * Attributes ajax tab loading
     */
    public function loadAttributesAjaxTabAction()
    {
        return $this->render(
                'ajax/product-attributes-tab',
                array(
                    'product_id' => $this->getRequest()->get('product_id', 0),
                )
        );
    }

    /**
     * Related information ajax tab loading
     */
    public function loadRelatedAjaxTabAction()
    {
        return $this->render(
                'ajax/product-related-tab',
                array(
                        'product_id'             => $this->getRequest()->get('product_id', 0),
                        'folder_id'              => $this->getRequest()->get('folder_id', 0),
                        'accessory_category_id'  => $this->getRequest()->get('accessory_category_id', 0)

                )
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
            ->setBasePrice($formData['price'])
            ->setBaseWeight($formData['weight'])
            ->setCurrencyId($formData['currency'])
            ->setTaxRuleId($formData['tax_rule'])
        ;

        return $createEvent;
    }

    protected function getUpdateEvent($formData)
    {

        $changeEvent = new ProductUpdateEvent($formData['id']);

        $changeEvent
            ->setLocale($formData['locale'])
            ->setRef($formData['ref'])
            ->setTitle($formData['title'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
            ->setVisible($formData['visible'])
            ->setDefaultCategory($formData['default_category'])
            ->setBrandId($formData['brand_id'])
        ;

        // Create and dispatch the change event
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

    protected function updatePriceFromDefaultCurrency($productPrice, $saleElement, $defaultCurrency, $currentCurrency)
    {
        // Get price for default currency
        $priceForDefaultCurrency = ProductPriceQuery::create()
        ->filterByCurrency($defaultCurrency)
        ->filterByProductSaleElements($saleElement)
        ->findOne()
        ;

        if ($priceForDefaultCurrency !== null) {
            $productPrice
            ->setPrice($priceForDefaultCurrency->getPrice() * $currentCurrency->getRate())
            ->setPromoPrice($priceForDefaultCurrency->getPromoPrice() * $currentCurrency->getRate())
            ;
        }
    }

    protected function appendValue(&$array, $key, $value)
    {
        if (! isset($array[$key])) $array[$key] = array();

        $array[$key][] = $value;
    }

    /**
     * @param  Product                 $object
     * @return ProductModificationForm
     */
    protected function hydrateObjectForm($object)
    {
        // Find product's sale elements
        $saleElements = ProductSaleElementsQuery::create()
            ->filterByProduct($object)
            ->find();

        $defaultCurrency = Currency::getDefaultCurrency();
        $currentCurrency = $this->getCurrentEditionCurrency();

        // Common parts
        $defaultPseData = $combinationPseData = array(
            "product_id"  => $object->getId(),
            "tax_rule"    => $object->getTaxRuleId()
        );

        foreach ($saleElements as $saleElement) {

            // Get the product price for the current currency
            $productPrice = ProductPriceQuery::create()
                ->filterByCurrency($currentCurrency)
                ->filterByProductSaleElements($saleElement)
                ->findOne()
            ;

            // No one exists ?
            if ($productPrice === null) {
                $productPrice = new ProductPrice();

                // If the current currency is not the default one, calculate the price
                // using default currency price and current currency rate
                if ($currentCurrency->getId() != $defaultCurrency->getId()) {

                    $productPrice->setFromDefaultCurrency(true);

                    $this->updatePriceFromDefaultCurrency($productPrice, $saleElement, $defaultCurrency, $currentCurrency);
                }
            }

            // Caclulate prices if we have to use the rate * defaulkt currency price
            if ($productPrice->getFromDefaultCurrency() == true) {
                $this->updatePriceFromDefaultCurrency($productPrice, $saleElement, $defaultCurrency, $currentCurrency);
            }

            $isDefaultPse = count($saleElement->getAttributeCombinations()) == 0;

            // If this PSE has no combination -> this is the default one
            // affect it to the thelia.admin.product_sale_element.update form
            if ($isDefaultPse) {
                $defaultPseData = array(
                    "product_sale_element_id" => $saleElement->getId(),
                    "reference"               => $saleElement->getRef(),
                    "price"                   => $productPrice->getPrice(),
                    "price_with_tax"          => number_format($this->computePrice($productPrice->getPrice(), 'without_tax', $object), 2, '.', ''),
                    "use_exchange_rate"       => $productPrice->getFromDefaultCurrency() ? 1 : 0,
                    "currency"                => $productPrice->getCurrencyId(),
                    "weight"                  => $saleElement->getWeight(),
                    "quantity"                => $saleElement->getQuantity(),
                    "sale_price"              => $productPrice->getPromoPrice(),
                    "sale_price_with_tax"     => number_format($this->computePrice($productPrice->getPromoPrice(), 'without_tax', $object), 2, '.', ''),
                    "onsale"                  => $saleElement->getPromo() > 0 ? 1 : 0,
                    "isnew"                   => $saleElement->getNewness() > 0 ? 1 : 0,
                    "isdefault"               => $saleElement->getIsDefault() > 0 ? 1 : 0,
                    "ean_code"                => $saleElement->getEanCode()
                );
            } else {

                if ($saleElement->getIsDefault()) {
                    $combinationPseData['default_pse']       = $saleElement->getId();
                    $combinationPseData['currency']          = $currentCurrency->getId();
                    $combinationPseData['use_exchange_rate'] = $productPrice->getFromDefaultCurrency() ? 1 : 0;
                }

                $this->appendValue($combinationPseData, "product_sale_element_id" , $saleElement->getId());
                $this->appendValue($combinationPseData, "reference"               , $saleElement->getRef());
                $this->appendValue($combinationPseData, "price"                   , $productPrice->getPrice());
                $this->appendValue($combinationPseData, "price_with_tax"          , number_format($this->computePrice($productPrice->getPrice(), 'without_tax', $object), 2, '.', ''));
                $this->appendValue($combinationPseData, "weight"                  , $saleElement->getWeight());
                $this->appendValue($combinationPseData, "quantity"                , $saleElement->getQuantity());
                $this->appendValue($combinationPseData, "sale_price"              , $productPrice->getPromoPrice());
                $this->appendValue($combinationPseData, "sale_price_with_tax"     , number_format($this->computePrice($productPrice->getPromoPrice(), 'without_tax', $object), 2, '.', ''));
                $this->appendValue($combinationPseData, "onsale"                  , $saleElement->getPromo() > 0 ? 1 : 0);
                $this->appendValue($combinationPseData, "isnew"                   , $saleElement->getNewness() > 0 ? 1 : 0);
                $this->appendValue($combinationPseData, "isdefault"               , $saleElement->getIsDefault() > 0 ? 1 : 0);
                $this->appendValue($combinationPseData, "ean_code"                , $saleElement->getEanCode());
            }

            $defaultPseForm = new ProductDefaultSaleElementUpdateForm($this->getRequest(), "form", $defaultPseData);
            $this->getParserContext()->addForm($defaultPseForm);

            $combinationPseForm = new ProductSaleElementUpdateForm($this->getRequest(), "form", $combinationPseData);
            $this->getParserContext()->addForm($combinationPseForm);
        }

        // Hydrate the "SEO" tab form
        $this->hydrateSeoForm($object);

        // The "General" tab form
        $data = array(
            'id'               => $object->getId(),
            'ref'              => $object->getRef(),
            'locale'           => $object->getLocale(),
            'title'            => $object->getTitle(),
            'chapo'            => $object->getChapo(),
            'description'      => $object->getDescription(),
            'postscriptum'     => $object->getPostscriptum(),
            'visible'          => $object->getVisible(),
            'default_category' => $object->getDefaultCategoryId(),
            'brand_id'         => $object->getBrandId()
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
        $product = ProductQuery::create()
            ->findOneById($this->getRequest()->get('product_id', 0));

        if (null !== $product) {
            $product->setLocale($this->getCurrentEditionLocale());
        }

        return $product;
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
                'category_id'           => $this->getCategoryId(),
                'product_id'            => $this->getRequest()->get('product_id', 0),
                'folder_id'             => $this->getRequest()->get('folder_id', 0),
                'accessory_category_id' => $this->getRequest()->get('accessory_category_id', 0),
                'current_tab'           => $this->getRequest()->get('current_tab', 'general'),
                'page'                  => $this->getRequest()->get('page', 1)
        );
    }

    protected function getCategoryId()
    {
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
                    'category_id' => $this->getCategoryId(),
                    'page' => $this->getRequest()->get('page', 1)
        ));
    }

    protected function redirectToListTemplate()
    {
        $this->redirectToRoute(
                'admin.products.default',
                array(
                    'category_id' => $this->getCategoryId(),
                    'page' => $this->getRequest()->get('page', 1)
                )
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
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

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
                foreach ($list as $item) {
                    $result[] = array('id' => $item->getId(), 'title' => $item->getTitle());
                }
            }
        }

        return $this->jsonResponse(json_encode($result));
    }

    public function addRelatedContentAction()
    {

        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

        $content_id = intval($this->getRequest()->get('content_id'));

        if ($content_id > 0) {

            $event = new ProductAddContentEvent(
                    $this->getExistingObject(),
                    $content_id
            );

            try {
                $this->dispatch(TheliaEvents::PRODUCT_ADD_CONTENT, $event);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        $this->redirectToEditionTemplate();
    }

    public function deleteRelatedContentAction()
    {

        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

        $content_id = intval($this->getRequest()->get('content_id'));

        if ($content_id > 0) {

            $event = new ProductDeleteContentEvent(
                    $this->getExistingObject(),
                    $content_id
            );

            try {
                $this->dispatch(TheliaEvents::PRODUCT_REMOVE_CONTENT, $event);
            } catch (\Exception $ex) {
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
                foreach ($list as $item) {
                    $result[] = array('id' => $item->getId(), 'title' => $item->getTitle());
                }
            }
        }

        return $this->jsonResponse(json_encode($result));
    }

    public function addAccessoryAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

        $accessory_id = intval($this->getRequest()->get('accessory_id'));

        if ($accessory_id > 0) {

            $event = new ProductAddAccessoryEvent(
                    $this->getExistingObject(),
                    $accessory_id
            );

            try {
                $this->dispatch(TheliaEvents::PRODUCT_ADD_ACCESSORY, $event);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        $this->redirectToEditionTemplate();
    }

    public function deleteAccessoryAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

        $accessory_id = intval($this->getRequest()->get('accessory_id'));

        if ($accessory_id > 0) {

            $event = new ProductDeleteAccessoryEvent(
                    $this->getExistingObject(),
                    $accessory_id
            );

            try {
                $this->dispatch(TheliaEvents::PRODUCT_REMOVE_ACCESSORY, $event);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        $this->redirectToEditionTemplate();
    }

    /**
     * Update accessory position
     */
    public function updateAccessoryPositionAction()
    {
        $accessory = AccessoryQuery::create()->findPk($this->getRequest()->get('accessory_id', null));

        return $this->genericUpdatePositionAction(
                $accessory,
                TheliaEvents::PRODUCT_UPDATE_ACCESSORY_POSITION
        );
    }

    /**
     * Update related content position
     */
    public function updateContentPositionAction()
    {
        $content = ProductAssociatedContentQuery::create()->findPk($this->getRequest()->get('content_id', null));

        return $this->genericUpdatePositionAction(
                $content,
                TheliaEvents::PRODUCT_UPDATE_CONTENT_POSITION
        );
    }

    /**
     * Change product template for a given product.
     *
     * @param unknown $productId
     */
    public function setProductTemplateAction($productId)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

        $product = ProductQuery::create()->findPk($productId);

        if ($product != null) {

            $template_id = intval($this->getRequest()->get('template_id', 0));

            $this->dispatch(
                    TheliaEvents::PRODUCT_SET_TEMPLATE,
                    new ProductSetTemplateEvent($product, $template_id, $this->getCurrentEditionCurrency()->getId())
            );
        }

        $this->redirectToEditionTemplate();
    }

    /**
     * Update product attributes and features
     */
    public function updateAttributesAndFeaturesAction($productId)
    {
        $product = ProductQuery::create()->findPk($productId);

        if ($product != null) {

            $featureTemplate = FeatureTemplateQuery::create()->filterByTemplateId($product->getTemplateId())->find();

            if ($featureTemplate !== null) {

                // Get all features for the template attached to this product
                $allFeatures = FeatureQuery::create()
                    ->filterByFeatureTemplate($featureTemplate)
                    ->find();

                $updatedFeatures = array();

                // Update all features values, starting with feature av. values
                $featureValues = $this->getRequest()->get('feature_value', array());

                foreach ($featureValues as $featureId => $featureValueList) {

                    // Delete all features av. for this feature.
                    $event = new FeatureProductDeleteEvent($productId, $featureId);

                    $this->dispatch(TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE, $event);

                    // Add then all selected values
                    foreach ($featureValueList as $featureValue) {
                        $event = new FeatureProductUpdateEvent($productId, $featureId, $featureValue);

                        $this->dispatch(TheliaEvents::PRODUCT_FEATURE_UPDATE_VALUE, $event);
                    }

                    $updatedFeatures[] = $featureId;
                }

                // Update then features text values
                $featureTextValues = $this->getRequest()->get('feature_text_value', array());

                foreach ($featureTextValues as $featureId => $featureValue) {

                    // considere empty text as empty feature value (e.g., we will delete it)
                    if (empty($featureValue)) continue;

                    $event = new FeatureProductUpdateEvent($productId, $featureId, $featureValue, true);

                    $this->dispatch(TheliaEvents::PRODUCT_FEATURE_UPDATE_VALUE, $event);

                    $updatedFeatures[] = $featureId;
                }

                // Delete features which don't have any values
                foreach ($allFeatures as $feature) {

                    if (! in_array($feature->getId(), $updatedFeatures)) {
                        $event = new FeatureProductDeleteEvent($productId, $feature->getId());

                        $this->dispatch(TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE, $event);
                    }
                }
            }
        }

        // If we have to stay on the same page, do not redirect to the succesUrl,
        // just redirect to the edit page again.
        if ($this->getRequest()->get('save_mode') == 'stay') {
            $this->redirectToEditionTemplate($this->getRequest());
        }

        // Redirect to the category/product list
        $this->redirectToListTemplate();
    }

    public function addAdditionalCategoryAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

        $category_id = intval($this->getRequest()->request->get('additional_category_id'));

        if ($category_id > 0) {

            $event = new ProductAddCategoryEvent(
                    $this->getExistingObject(),
                    $category_id
            );

            try {
                $this->dispatch(TheliaEvents::PRODUCT_ADD_CATEGORY, $event);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        $this->redirectToEditionTemplate();
    }

    public function deleteAdditionalCategoryAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

        $category_id = intval($this->getRequest()->get('additional_category_id'));

        if ($category_id > 0) {

            $event = new ProductDeleteCategoryEvent(
                    $this->getExistingObject(),
                    $category_id
            );

            try {
                $this->dispatch(TheliaEvents::PRODUCT_REMOVE_CATEGORY, $event);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        $this->redirectToEditionTemplate();
    }

    // -- Product combination management ---------------------------------------

    public function getAttributeValuesAction($productId, $attributeId)
    {
        $result = array();

        // Get attribute for this product
        $attribute = AttributeQuery::create()->findPk($attributeId);

        if ($attribute !== null) {

            $values = AttributeAvQuery::create()
                ->joinWithI18n($this->getCurrentEditionLocale())
                ->filterByAttribute($attribute)
                ->find();
            ;

            if ($values !== null) {
                foreach ($values as $value) {
                    $result[] = array('id' => $value->getId(), 'title' => $value->getTitle());
                }
            }
        }

        return $this->jsonResponse(json_encode($result));
    }

    public function addAttributeValueToCombinationAction($productId, $attributeAvId, $combination)
    {
        $result = array();

        // Get attribute for this product
        $attributeAv = AttributeAvQuery::create()->joinWithI18n($this->getCurrentEditionLocale())->findPk($attributeAvId);

        if ($attributeAv !== null) {

            $addIt = true;

            $attribute = $attributeAv->getAttribute();

            // Check if this attribute is not already present
            $combinationArray = explode(',', $combination);

            foreach ($combinationArray as $id) {

                $attrAv = AttributeAvQuery::create()->joinWithI18n($this->getCurrentEditionLocale())->findPk($id);

                if ($attrAv !== null) {

                    if ($attrAv->getAttributeId() == $attribute->getId()) {

                        $result['error'] = $this->getTranslator()->trans(
                                'A value for attribute "%name" is already present in the combination',
                                array('%name' => $attribute->getTitle())
                        );

                        $addIt = false;
                    }

                    $result[] = array('id' => $attrAv->getId(), 'title' => $attrAv->getAttribute()->getTitle() . " : " . $attrAv->getTitle());
                }
            }

            if ($addIt) $result[] = array('id' => $attributeAv->getId(), 'title' => $attribute->getTitle() . " : " . $attributeAv->getTitle());
        }

        return $this->jsonResponse(json_encode($result));
    }

    /**
     * A a new combination to a product
     */
    public function addProductSaleElementAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

        $event = new ProductSaleElementCreateEvent(
                $this->getExistingObject(),
                $this->getRequest()->get('combination_attributes', array()),
                $this->getCurrentEditionCurrency()->getId()
        );

        try {
            $this->dispatch(TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT, $event);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToEditionTemplate();
    }

    /**
     * A a new combination to a product
     */
    public function deleteProductSaleElementAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

        $event = new ProductSaleElementDeleteEvent(
                $this->getRequest()->get('product_sale_element_id',0),
                $this->getCurrentEditionCurrency()->getId()
        );

        try {
            $this->dispatch(TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT, $event);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $this->redirectToEditionTemplate();
    }

    /**
     * Process a single PSE update, using form data array.
     *
     * @param  array     $data the form data
     * @throws Exception is someting goes wrong.
     */
    protected function processSingleProductSaleElementUpdate($data)
    {
        $event = new ProductSaleElementUpdateEvent(
                $this->getExistingObject(),
                $data['product_sale_element_id']
        );

        $event
            ->setReference($data['reference'])
            ->setPrice($data['price'])
            ->setCurrencyId($data['currency'])
            ->setWeight($data['weight'])
            ->setQuantity($data['quantity'])
            ->setSalePrice($data['sale_price'])
            ->setOnsale($data['onsale'])
            ->setIsnew($data['isnew'])
            ->setIsdefault($data['isdefault'])
            ->setEanCode($data['ean_code'])
            ->setTaxRuleId($data['tax_rule'])
            ->setFromDefaultCurrency($data['use_exchange_rate'])
        ;

        $this->dispatch(TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT, $event);

        // Log object modification
        if (null !== $changedObject = $event->getProductSaleElement()) {
            $this->adminLogAppend($this->resourceCode, AccessManager::UPDATE, sprintf("Product Sale Element (ID %s) for product reference %s modified", $changedObject->getId(), $event->getProduct()->getRef()));
        }
    }

    /**
     * Change a product sale element
     */
    protected function processProductSaleElementUpdate($changeForm)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

        $error_msg = false;

        try {

            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, "POST");

            // Get the form field values
            $data = $form->getData();

            if (is_array($data['product_sale_element_id'])) {

                // Common fields
                $tmp_data = array(
                    'tax_rule'          => $data['tax_rule'],
                    'currency'          => $data['currency'],
                    'use_exchange_rate' => $data['use_exchange_rate'],
                );

                $count = count($data['product_sale_element_id']);

                for ($idx = 0; $idx < $count; $idx++) {
                    $tmp_data['product_sale_element_id'] = $pse_id = $data['product_sale_element_id'][$idx];
                    $tmp_data['reference']               = $data['reference'][$idx];
                    $tmp_data['price']                   = $data['price'][$idx];
                    $tmp_data['weight']                  = $data['weight'][$idx];
                    $tmp_data['quantity']                = $data['quantity'][$idx];
                    $tmp_data['sale_price']              = $data['sale_price'][$idx];
                    $tmp_data['onsale']                  = isset($data['onsale'][$idx]) ? 1 : 0;
                    $tmp_data['isnew']                   = isset($data['isnew'][$idx]) ? 1 : 0;
                    $tmp_data['isdefault']               = $data['default_pse'] == $pse_id;
                    $tmp_data['ean_code']                = $data['ean_code'][$idx];

                    $this->processSingleProductSaleElementUpdate($tmp_data);
                }
            } else {
                // No need to preprocess data
                $this->processSingleProductSaleElementUpdate($data);
            }

            // If we have to stay on the same page, do not redirect to the succesUrl, just redirect to the edit page again.
            if ($this->getRequest()->get('save_mode') == 'stay') {
                $this->redirectToEditionTemplate($this->getRequest());
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

        $this->setupFormErrorContext(
                $this->getTranslator()->trans("ProductSaleElement modification"), $error_msg, $changeForm, $ex);

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    /**
     * Process the change of product's PSE list.
     */
    public function updateProductSaleElementsAction()
    {
        return $this->processProductSaleElementUpdate(
                new ProductSaleElementUpdateForm($this->getRequest())
        );
    }

    /**
     * Update default product sale element (not attached to any combination)
     */
    public function updateProductDefaultSaleElementAction()
    {
        return $this->processProductSaleElementUpdate(
                new ProductDefaultSaleElementUpdateForm($this->getRequest())
        );
    }

    // Create combinations
    protected function combine($input, &$output, &$tmp)
    {
        $current = array_shift($input);

        if (count($input) > 0) {
            foreach ($current as $element) {
                $tmp[] = $element;
                $this->combine($input, $output, $tmp);
                array_pop($tmp);
            }
        } else {
            foreach ($current as $element) {
                $tmp[] = $element;
                $output[] = $tmp;
                array_pop($tmp);
            }
        }
    }

    /**
     * Build combinations from the combination output builder
     */
    public function buildCombinationsAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;

        $error_msg = false;

        $changeForm = new ProductCombinationGenerationForm($this->getRequest());

        try {

            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, "POST");

            // Get the form field values
            $data = $form->getData();

            // Rework attributes_av array, to build an array which contains all combinations,
            // in the form combination[] = array of combination attributes av IDs
            //
            // First, create an array of attributes_av ID in the form $attributes_av_list[$attribute_id] = array of attributes_av ID
            // from the list of attribute_id:attributes_av ID from the form.
            $combinations = $attributes_av_list = array();

            foreach ($data['attribute_av'] as $item) {
                list($attribute_id, $attribute_av_id) = explode(':', $item);

                if (! isset($attributes_av_list[$attribute_id]))
                    $attributes_av_list[$attribute_id] = array();


                $attributes_av_list[$attribute_id][] = $attribute_av_id;
            }

            // Next, recursively combine array
            $combinations = $tmp = array();

            $this->combine($attributes_av_list, $combinations, $tmp);

            // Create event
            $event = new ProductCombinationGenerationEvent(
                    $this->getExistingObject(),
                    $data['currency'],
                    $combinations
            );

            $event
                ->setReference($data['reference'] == null ? '' : $data['reference'])
                ->setPrice($data['price'] == null ? 0 : $data['price'])
                ->setWeight($data['weight'] == null ? 0 : $data['weight'])
                ->setQuantity($data['quantity'] == null ? 0 : $data['quantity'])
                ->setSalePrice($data['sale_price'] == null ? 0 : $data['sale_price'])
                ->setOnsale($data['onsale'] == null ? false : $data['onsale'])
                ->setIsnew($data['isnew'] == null ? false : $data['isnew'])
                ->setEanCode($data['ean_code'] == null ? '' : $data['ean_code'])
            ;

            $this->dispatch(TheliaEvents::PRODUCT_COMBINATION_GENERATION, $event);

            // Log object modification
            $this->adminLogAppend($this->resourceCode, AccessManager::CREATE, sprintf("Combination generation for product reference %s", $event->getProduct()->getRef()));

           // Redirect to the success URL
           $this->redirect($changeForm->getSuccessUrl());

        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
                $this->getTranslator()->trans("Combination builder"), $error_msg, $changeForm, $ex);

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    /**
     * Invoked through Ajax; this method calculates the taxed price from the unaxed price, and
     * vice versa.
     */
    public function priceCaclulator()
    {
        $return_price = 0;

        $price      = floatval($this->getRequest()->query->get('price', 0));
        $product_id = intval($this->getRequest()->query->get('product_id', 0));
        $action     = $this->getRequest()->query->get('action', ''); // With ot without tax
        $convert    = intval($this->getRequest()->query->get('convert_from_default_currency', 0));

        if (null !== $product = ProductQuery::create()->findPk($product_id)) {

            if ($action == 'to_tax') {
                $return_price = $this->computePrice($price, 'without_tax', $product);
            } elseif ($action == 'from_tax') {
                $return_price = $this->computePrice($price, 'with_tax', $product);
            } else {
                $return_price = $price;
            }

            if ($convert != 0) {
                $return_price = $price * Currency::getDefaultCurrency()->getRate();
            }
        }

        return new JsonResponse(array('result' => $return_price));
    }

    /**
     *
     * Calculate tax or untax price for a non existing product.
     *
     * For an existing product, use self::priceCaclulator
     *
     * @return JsonResponse
     */
    public function calculatePrice()
    {
        $return_price = 0;

        $price      = floatval($this->getRequest()->query->get('price'));
        $tax_rule_id = intval($this->getRequest()->query->get('tax_rule'));
        $action     = $this->getRequest()->query->get('action'); // With ot without tax

        $taxRule = TaxRuleQuery::create()->findPk($tax_rule_id);

        if (null !== $price && null !== $taxRule) {

            $calculator = new Calculator();

            $calculator->loadTaxRuleWithoutProduct(
                $taxRule,
                Country::getShopLocation()
            );

            if ($action == 'to_tax') {
                $return_price = $calculator->getTaxedPrice($price);
            } elseif ($action == 'from_tax') {
                $return_price = $calculator->getUntaxedPrice($price);
            } else {
                $return_price = $price;
            }
        }

        return new JsonResponse(array('result' => $return_price));
    }

    /**
     * Calculate all prices
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function loadConvertedPrices()
    {
        $product_sale_element_id  = intval($this->getRequest()->get('product_sale_element_id', 0));
        $currency_id = intval($this->getRequest()->get('currency_id', 0));

        $price_with_tax = $price_without_tax = $sale_price_with_tax = $sale_price_without_tax = 0;

        if (null !== $pse = ProductSaleElementsQuery::create()->findPk($product_sale_element_id)) {
            if ($currency_id > 0
                &&
                $currency_id != Currency::getDefaultCurrency()->getId()
                &&
                null !== $currency = CurrencyQuery::create()->findPk($currency_id)) {

                // Get the default currency price
                $productPrice = ProductPriceQuery::create()
                    ->filterByCurrency(Currency::getDefaultCurrency())
                    ->filterByProductSaleElementsId($product_sale_element_id)
                    ->findOne()
                ;

                // Calculate the converted price
                if (null !== $productPrice) {
                    $price_without_tax = $productPrice->getPrice() * $currency->getRate();
                    $sale_price_without_tax = $productPrice->getPromoPrice() * $currency->getRate();
                }
            }

            if (null !== $product = $pse->getProduct()) {
                $price_with_tax = $this->computePrice($price_without_tax, 'with_tax', $product);
                $sale_price_with_tax = $this->computePrice($sale_price_without_tax, 'with_tax', $product);
            }
        }

        return new JsonResponse(array(
            'price_with_tax'         => NumberFormat::getInstance($this->getRequest())->formatStandardNumber($price_with_tax),
            'price_without_tax'      => NumberFormat::getInstance($this->getRequest())->formatStandardNumber($price_without_tax),
            'sale_price_with_tax'    => NumberFormat::getInstance($this->getRequest())->formatStandardNumber($sale_price_with_tax),
            'sale_price_without_tax' => NumberFormat::getInstance($this->getRequest())->formatStandardNumber($sale_price_without_tax)
        ));
    }

    /**
     * Calculate taxed/untexted price for a product
     *
     * @param  unknown  $price
     * @param  unknown  $price_type
     * @param  Product  $product
     * @return Ambigous <unknown, number>
     */
    protected function computePrice($price, $price_type, Product $product, $convert = false)
    {
        $calc = new Calculator();

        $calc->load(
                $product,
                Country::getShopLocation()
        );

        if ($price_type == 'without_tax') {
            $return_price = $calc->getTaxedPrice($price);
        } elseif ($price_type == 'with_tax') {
            $return_price = $calc->getUntaxedPrice($price);
        } else {
            $return_price = $price;
        }

        if ($convert != 0) {
            $return_price = $price * Currency::getDefaultCurrency()->getRate();
        }

        // Format the number using '.', to perform further calculation
        //return NumberFormat::getInstance($this->getRequest())->formatStandardNumber($return_price);
        return $return_price;
    }
}
