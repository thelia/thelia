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

namespace Thelia\Action;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Core\Event\Feature\FeatureAvCreateEvent;
use Thelia\Core\Event\Feature\FeatureAvDeleteEvent;
use Thelia\Core\Event\FeatureProduct\FeatureProductDeleteEvent;
use Thelia\Core\Event\FeatureProduct\FeatureProductUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\Product\ProductAddAccessoryEvent;
use Thelia\Core\Event\Product\ProductAddCategoryEvent;
use Thelia\Core\Event\Product\ProductAddContentEvent;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductDeleteAccessoryEvent;
use Thelia\Core\Event\Product\ProductDeleteCategoryEvent;
use Thelia\Core\Event\Product\ProductDeleteContentEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\Product\ProductSetTemplateEvent;
use Thelia\Core\Event\Product\ProductToggleVisibilityEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementDeleteEvent;
use Thelia\Core\Event\Template\TemplateDeleteAttributeEvent;
use Thelia\Core\Event\Template\TemplateDeleteFeatureEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Core\Event\ViewCheckEvent;
use Thelia\Model\Accessory;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\AttributeTemplateQuery;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\FeatureAvI18n;
use Thelia\Model\FeatureAvI18nQuery;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureProduct;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\FeatureTemplateQuery;
use Thelia\Model\Map\AttributeTemplateTableMap;
use Thelia\Model\Map\FeatureTemplateTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product as ProductModel;
use Thelia\Model\ProductAssociatedContent;
use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\ProductCategory;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductI18n;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleQuery;

class Product extends BaseAction implements EventSubscriberInterface
{
    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Create a new product entry
     *
     * @param \Thelia\Core\Event\Product\ProductCreateEvent $event
     */
    public function create(ProductCreateEvent $event)
    {
        $defaultTaxRuleId = null;
        if (null !== $defaultTaxRule = TaxRuleQuery::create()->findOneByIsDefault(true)) {
            $defaultTaxRuleId = $defaultTaxRule->getId();
        }

        $product = new ProductModel();

        $product
            ->setDispatcher($this->eventDispatcher)

            ->setRef($event->getRef())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setVisible($event->getVisible() ? 1 : 0)
            ->setVirtual($event->getVirtual() ? 1 : 0)
            ->setTemplateId($event->getTemplateId())

            ->create(
                $event->getDefaultCategory(),
                $event->getBasePrice(),
                $event->getCurrencyId(),
                // Set the default tax rule if not defined
                $event->getTaxRuleId() ?: $defaultTaxRuleId,
                $event->getBaseWeight(),
                $event->getBaseQuantity()
            )
        ;

        $event->setProduct($product);
    }

    /*******************
     * CLONING PROCESS *
     *******************/

    /**
     * @param ProductCloneEvent $event
     * @throws \Exception
     */
    public function cloneProduct(ProductCloneEvent $event)
    {
        $con = Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            // Get important datas
            $lang = $event->getLang();
            $originalProduct = $event->getOriginalProduct();

            if (null === $originalProductDefaultI18n = ProductI18nQuery::create()
                ->findPk([$originalProduct->getId(), $lang])) {
                // No i18n entry for the current language. Try to find one for creating the product.
                // It will be updated later by updateClone()
                $originalProductDefaultI18n = ProductI18nQuery::create()
                    ->findOneById($originalProduct->getId())
                    ;
            }

            $originalProductDefaultPrice = ProductPriceQuery::create()
                ->findOneByProductSaleElementsId($originalProduct->getDefaultSaleElements()->getId());

            // Cloning process

            $this->createClone($event, $originalProductDefaultI18n, $originalProductDefaultPrice);

            $this->updateClone($event, $originalProductDefaultPrice);

            $this->cloneFeatureCombination($event);

            $this->cloneAssociatedContent($event);

            $this->cloneAccessories($event);

            // Dispatch event for file cloning
            $this->eventDispatcher->dispatch(TheliaEvents::FILE_CLONE, $event);

            // Dispatch event for PSE cloning
            $this->eventDispatcher->dispatch(TheliaEvents::PSE_CLONE, $event);

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();
            throw $e;
        }
    }

    public function createClone(ProductCloneEvent $event, ProductI18n $originalProductDefaultI18n, ProductPrice $originalProductDefaultPrice)
    {
        // Build event and dispatch creation of the clone product
        $createCloneEvent = new ProductCreateEvent();
        $createCloneEvent
            ->setTitle($originalProductDefaultI18n->getTitle())
            ->setRef($event->getRef())
            ->setLocale($event->getLang())
            ->setVisible(0)
            ->setVirtual($event->getOriginalProduct()->getVirtual())
            ->setTaxRuleId($event->getOriginalProduct()->getTaxRuleId())
            ->setDefaultCategory($event->getOriginalProduct()->getDefaultCategoryId())
            ->setBasePrice($originalProductDefaultPrice->getPrice())
            ->setCurrencyId($originalProductDefaultPrice->getCurrencyId())
            ->setBaseWeight($event->getOriginalProduct()->getDefaultSaleElements()->getWeight());

        $this->eventDispatcher->dispatch(TheliaEvents::PRODUCT_CREATE, $createCloneEvent);

        $event->setClonedProduct($createCloneEvent->getProduct());
    }

    public function updateClone(ProductCloneEvent $event, ProductPrice $originalProductDefaultPrice)
    {
        // Get original product's I18ns
        $originalProductI18ns = ProductI18nQuery::create()
            ->findById($event->getOriginalProduct()->getId());

        /** @var ProductI18n $originalProductI18n */
        foreach ($originalProductI18ns as $originalProductI18n) {
            $clonedProductUpdateEvent = new ProductUpdateEvent($event->getClonedProduct()->getId());
            $clonedProductUpdateEvent
                ->setRef($event->getClonedProduct()->getRef())
                ->setVisible($event->getClonedProduct()->getVisible())
                ->setVirtual($event->getClonedProduct()->getVirtual())

                ->setLocale($originalProductI18n->getLocale())
                ->setTitle($originalProductI18n->getTitle())
                ->setChapo($originalProductI18n->getChapo())
                ->setDescription($originalProductI18n->getDescription())
                ->setPostscriptum($originalProductI18n->getPostscriptum())

                ->setBasePrice($originalProductDefaultPrice->getPrice())
                ->setCurrencyId($originalProductDefaultPrice->getCurrencyId())
                ->setBaseWeight($event->getOriginalProduct()->getDefaultSaleElements()->getWeight())
                ->setTaxRuleId($event->getOriginalProduct()->getTaxRuleId())
                ->setBrandId($event->getOriginalProduct()->getBrandId())
                ->setDefaultCategory($event->getOriginalProduct()->getDefaultCategoryId());

            $this->eventDispatcher->dispatch(TheliaEvents::PRODUCT_UPDATE, $clonedProductUpdateEvent);

            // SEO info
            $clonedProductUpdateSeoEvent = new UpdateSeoEvent($event->getClonedProduct()->getId());
            $clonedProductUpdateSeoEvent
                ->setLocale($originalProductI18n->getLocale())
                ->setMetaTitle($originalProductI18n->getMetaTitle())
                ->setMetaDescription($originalProductI18n->getMetaDescription())
                ->setMetaKeywords($originalProductI18n->getMetaKeywords())
                ->setUrl(null);
            $this->eventDispatcher->dispatch(TheliaEvents::PRODUCT_UPDATE_SEO, $clonedProductUpdateSeoEvent);
        }

        $event->setClonedProduct($clonedProductUpdateEvent->getProduct());

        // Set clone's template
        $clonedProductUpdateTemplateEvent = new ProductSetTemplateEvent(
            $event->getClonedProduct(),
            $event->getOriginalProduct()->getTemplateId(),
            $originalProductDefaultPrice->getCurrencyId()
        );

        $this->eventDispatcher->dispatch(TheliaEvents::PRODUCT_SET_TEMPLATE, $clonedProductUpdateTemplateEvent);
    }

    public function cloneFeatureCombination(ProductCloneEvent $event)
    {
        // Get original product FeatureProduct list
        $originalProductFeatureList = FeatureProductQuery::create()
            ->findByProductId($event->getOriginalProduct()->getId());

        // Set clone product FeatureProducts
        /** @var FeatureProduct $originalProductFeature */
        foreach ($originalProductFeatureList as $originalProductFeature) {
            // Get original FeatureAvI18n list
            $originalProductFeatureAvI18nList = FeatureAvI18nQuery::create()
                ->findById($originalProductFeature->getFeatureAvId());

            /** @var FeatureAvI18n $originalProductFeatureAvI18n */
            foreach ($originalProductFeatureAvI18nList as $originalProductFeatureAvI18n) {
                // Create a FeatureProduct for each FeatureAv (not for each FeatureAvI18n)
                $clonedProductCreateFeatureEvent = new FeatureProductUpdateEvent(
                    $event->getClonedProduct()->getId(),
                    $originalProductFeature->getFeatureId(),
                    $originalProductFeature->getFeatureAvId()
                );
                $clonedProductCreateFeatureEvent->setLocale($originalProductFeatureAvI18n->getLocale());

                // If it's a free text value, pass the FeatureAvI18n's title as featureValue to the event
                if ($originalProductFeature->getFreeTextValue() !== null) {
                    $clonedProductCreateFeatureEvent->setFeatureValue($originalProductFeatureAvI18n->getTitle());
                    $clonedProductCreateFeatureEvent->setIsTextValue(true);
                }

                $this->eventDispatcher->dispatch(TheliaEvents::PRODUCT_FEATURE_UPDATE_VALUE, $clonedProductCreateFeatureEvent);
            }
        }
    }

    public function cloneAssociatedContent(ProductCloneEvent $event)
    {
        // Get original product associated contents
        $originalProductAssocConts = ProductAssociatedContentQuery::create()
            ->findByProductId($event->getOriginalProduct()->getId());

        // Set clone product associated contents
        /** @var  ProductAssociatedContent $originalProductAssocCont */
        foreach ($originalProductAssocConts as $originalProductAssocCont) {
            $clonedProductCreatePAC = new ProductAddContentEvent($event->getClonedProduct(), $originalProductAssocCont->getContentId());
            $this->eventDispatcher->dispatch(TheliaEvents::PRODUCT_ADD_CONTENT, $clonedProductCreatePAC);
        }
    }

    public function cloneAccessories(ProductCloneEvent $event)
    {
        // Get original product accessories
        $originalProductAccessoryList = AccessoryQuery::create()
            ->findByProductId($event->getOriginalProduct()->getId());

        // Set clone product accessories
        /** @var Accessory $originalProductAccessory */
        foreach ($originalProductAccessoryList as $originalProductAccessory) {
            $clonedProductAddAccessoryEvent = new ProductAddAccessoryEvent($event->getClonedProduct(), $originalProductAccessory->getAccessory());
            $this->eventDispatcher->dispatch(TheliaEvents::PRODUCT_ADD_ACCESSORY, $clonedProductAddAccessoryEvent);
        }
    }

    /***************
     * END CLONING *
     ***************/

    /**
     * Change a product
     *
     * @param \Thelia\Core\Event\Product\ProductUpdateEvent $event
     * @throws PropelException
     * @throws \Exception
     */
    public function update(ProductUpdateEvent $event)
    {
        if (null !== $product = ProductQuery::create()->findPk($event->getProductId())) {
            $con = Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);
            $con->beginTransaction();

            try {
                $prevRef = $product->getRef();

                $product
                    ->setDispatcher($this->eventDispatcher)
                    ->setRef($event->getRef())
                    ->setLocale($event->getLocale())
                    ->setTitle($event->getTitle())
                    ->setDescription($event->getDescription())
                    ->setChapo($event->getChapo())
                    ->setPostscriptum($event->getPostscriptum())
                    ->setVisible($event->getVisible() ? 1 : 0)
                    ->setVirtual($event->getVirtual() ? 1 : 0)
                    ->setBrandId($event->getBrandId() <= 0 ? null : $event->getBrandId())

                    ->save($con)
                ;

                // Update default PSE (if product has no attributes and the product's ref change)
                $defaultPseRefChange = $prevRef !== $product->getRef()
                    && 0 === $product->getDefaultSaleElements()->countAttributeCombinations();
                if ($defaultPseRefChange) {
                    $defaultPse = $product->getDefaultSaleElements();
                    $defaultPse->setRef($product->getRef())->save();
                }

                // Update default category (if required)
                $product->setDefaultCategory($event->getDefaultCategory());

                $event->setProduct($product);
                $con->commit();
            } catch (PropelException $e) {
                $con->rollBack();
                throw $e;
            }
        }
    }

    /**
     * @param UpdateSeoEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @return mixed
     */
    public function updateSeo(UpdateSeoEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        return $this->genericUpdateSeo(ProductQuery::create(), $event, $dispatcher);
    }

    /**
     * Delete a product entry
     *
     * @param \Thelia\Core\Event\Product\ProductDeleteEvent $event
     * @throws \Exception
     */
    public function delete(ProductDeleteEvent $event)
    {
        if (null !== $product = ProductQuery::create()->findPk($event->getProductId())) {
            $con = Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);
            $con->beginTransaction();

            try {
                $fileList = ['images' => [], 'documentList' => []];

                // Get product's files to delete after product deletion
                $fileList['images']['list'] = ProductImageQuery::create()
                    ->findByProductId($event->getProductId());
                $fileList['images']['type'] = TheliaEvents::IMAGE_DELETE;

                $fileList['documentList']['list'] = ProductDocumentQuery::create()
                    ->findByProductId($event->getProductId());
                $fileList['documentList']['type'] = TheliaEvents::DOCUMENT_DELETE;

                // Delete product
                $product
                    ->setDispatcher($this->eventDispatcher)
                    ->delete($con)
                ;

                $event->setProduct($product);

                // Dispatch delete product's files event
                foreach ($fileList as $fileTypeList) {
                    foreach ($fileTypeList['list'] as $fileToDelete) {
                        $fileDeleteEvent = new FileDeleteEvent($fileToDelete);
                        $this->eventDispatcher->dispatch($fileTypeList['type'], $fileDeleteEvent);
                    }
                }

                $con->commit();
            } catch (\Exception $e) {
                $con->rollBack();
                throw $e;
            }
        }
    }

    /**
     * Toggle product visibility. No form used here
     *
     * @param ProductToggleVisibilityEvent $event
     */
    public function toggleVisibility(ProductToggleVisibilityEvent $event)
    {
        $product = $event->getProduct();

        $product
            ->setDispatcher($this->eventDispatcher)
            ->setVisible($product->getVisible() ? false : true)
            ->save()
            ;

        $event->setProduct($product);
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param UpdatePositionEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $this->genericUpdateDelegatePosition(
            ProductCategoryQuery::create()
                ->filterByProductId($event->getObjectId())
                ->filterByCategoryId($event->getReferrerId()),
            $event,
            $dispatcher
        );
    }

    public function addContent(ProductAddContentEvent $event)
    {
        if (ProductAssociatedContentQuery::create()
            ->filterByContentId($event->getContentId())
             ->filterByProduct($event->getProduct())->count() <= 0) {
            $content = new ProductAssociatedContent();

            $content
                ->setDispatcher($this->eventDispatcher)
                ->setProduct($event->getProduct())
                ->setContentId($event->getContentId())
                ->save()
            ;
        }
    }

    public function removeContent(ProductDeleteContentEvent $event)
    {
        $content = ProductAssociatedContentQuery::create()
            ->filterByContentId($event->getContentId())
            ->filterByProduct($event->getProduct())->findOne()
        ;

        if ($content !== null) {
            $content
                ->setDispatcher($this->eventDispatcher)
                ->delete()
            ;
        }
    }

    public function addCategory(ProductAddCategoryEvent $event)
    {
        if (ProductCategoryQuery::create()
            ->filterByProduct($event->getProduct())
            ->filterByCategoryId($event->getCategoryId())
            ->count() <= 0) {
            $productCategory = (new ProductCategory())
                ->setProduct($event->getProduct())
                ->setCategoryId($event->getCategoryId())
                ->setDefaultCategory(false);

            $productCategory
                ->setPosition($productCategory->getNextPosition())
                ->save();
        }
    }

    public function removeCategory(ProductDeleteCategoryEvent $event)
    {
        $productCategory = ProductCategoryQuery::create()
            ->filterByProduct($event->getProduct())
            ->filterByCategoryId($event->getCategoryId())
            ->findOne();

        if ($productCategory != null) {
            $productCategory->delete();
        }
    }

    public function addAccessory(ProductAddAccessoryEvent $event)
    {
        if (AccessoryQuery::create()
            ->filterByAccessory($event->getAccessoryId())
            ->filterByProductId($event->getProduct()->getId())->count() <= 0) {
            $accessory = new Accessory();

            $accessory
                ->setDispatcher($this->eventDispatcher)
                ->setProductId($event->getProduct()->getId())
                ->setAccessory($event->getAccessoryId())
            ->save()
            ;
        }
    }

    public function removeAccessory(ProductDeleteAccessoryEvent $event)
    {
        $accessory = AccessoryQuery::create()
            ->filterByAccessory($event->getAccessoryId())
            ->filterByProductId($event->getProduct()->getId())->findOne()
        ;

        if ($accessory !== null) {
            $accessory
                ->setDispatcher($this->eventDispatcher)
                ->delete()
            ;
        }
    }

    public function setProductTemplate(ProductSetTemplateEvent $event)
    {
        $con = Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);

        $con->beginTransaction();

        try {
            $product = $event->getProduct();

            // Check differences between current coobination and the next one, and clear obsoletes values.
            $nextTemplateId = $event->getTemplateId();
            $currentTemplateId = $product->getTemplateId();
                
            // 1. Process product features.
            
            $currentFeatures = FeatureTemplateQuery::create()
                ->filterByTemplateId($currentTemplateId)
                ->select([ FeatureTemplateTableMap::FEATURE_ID ])
                ->find($con);
    
            $nextFeatures = FeatureTemplateQuery::create()
                ->filterByTemplateId($nextTemplateId)
                ->select([ FeatureTemplateTableMap::FEATURE_ID ])
                ->find($con);
            
            // Find features values we shoud delete. To do this, we have to
            // find all features in $currentFeatures that are not present in $nextFeatures
            $featuresToDelete = array_diff($currentFeatures->getData(), $nextFeatures->getData());
    
            // Delete obsolete features values
            foreach ($featuresToDelete as $featureId) {
                $this->eventDispatcher->dispatch(
                    TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE,
                    new FeatureProductDeleteEvent($product->getId(), $featureId)
                );
            }
            
            // 2. Process product Attributes
            
            $currentAttributes = AttributeTemplateQuery::create()
                ->filterByTemplateId($currentTemplateId)
                ->select([ AttributeTemplateTableMap::ATTRIBUTE_ID ])
                ->find($con);
    
            $nextAttributes = AttributeTemplateQuery::create()
                ->filterByTemplateId($nextTemplateId)
                ->select([ AttributeTemplateTableMap::ATTRIBUTE_ID ])
                ->find($con);
            
            // Find attributes values we shoud delete. To do this, we have to
            // find all attributes in $currentAttributes that are not present in $nextAttributes
            $attributesToDelete = array_diff($currentAttributes->getData(), $nextAttributes->getData());

            // Find PSE which includes $attributesToDelete for the current product/
            $pseToDelete = ProductSaleElementsQuery::create()
                ->filterByProductId($product->getId())
                ->useAttributeCombinationQuery()
                    ->filterByAttributeId($attributesToDelete, Criteria::IN)
                ->endUse()
                ->select([ ProductSaleElementsTableMap::ID ])
                ->find();
    
            // Delete obsolete PSEs
            foreach ($pseToDelete->getData() as $pseId) {
                $this->eventDispatcher->dispatch(
                    TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT,
                    new ProductSaleElementDeleteEvent(
                        $pseId,
                        CurrencyModel::getDefaultCurrency()->getId()
                    )
                );
            }

            // Update the product template
            $template_id = $event->getTemplateId();

            // Set it to null if it's zero.
            if ($template_id <= 0) {
                $template_id = null;
            }

            $product->setTemplateId($template_id)->save($con);

            $product->clearProductSaleElementss();

            $event->setProduct($product);

            // Store all the stuff !
            $con->commit();
        } catch (\Exception $ex) {
            $con->rollBack();

            throw $ex;
        }
    }
    
    /**
     * Changes accessry position, selecting absolute ou relative change.
     *
     * @param UpdatePositionEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @return Object
     */
    public function updateAccessoryPosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        return $this->genericUpdatePosition(AccessoryQuery::create(), $event, $dispatcher);
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param UpdatePositionEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @return Object
     */
    public function updateContentPosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        return $this->genericUpdatePosition(ProductAssociatedContentQuery::create(), $event, $dispatcher);
    }

    /**
     * Update the value of a product feature.
     *
     * @param FeatureProductUpdateEvent $event
     */
    public function updateFeatureProductValue(FeatureProductUpdateEvent $event)
    {
        // Prepare the FeatureAv's ID
        $featureAvId = $event->getFeatureValue();

        // Search for existing FeatureProduct
        $featureProductQuery = FeatureProductQuery::create()
            ->filterByProductId($event->getProductId())
            ->filterByFeatureId($event->getFeatureId())
        ;

        // If it's not a free text value, we can filter by the event's featureValue (which is an ID)
        if ($event->getFeatureValue() !== null && $event->getIsTextValue() === false) {
            $featureProductQuery->filterByFeatureAvId($featureAvId);
        }

        $featureProduct = $featureProductQuery->findOne();

        // If the FeatureProduct does not exist, create it
        if ($featureProduct === null) {
            $featureProduct = new FeatureProduct();

            $featureProduct
                ->setDispatcher($this->eventDispatcher)
                ->setProductId($event->getProductId())
                ->setFeatureId($event->getFeatureId())
            ;

            // If it's a free text value, create a FeatureAv to handle i18n
            if ($event->getIsTextValue() === true) {
                $featureProduct->setFreeTextValue(true);

                $createFeatureAvEvent = new FeatureAvCreateEvent();
                $createFeatureAvEvent
                    ->setFeatureId($event->getFeatureId())
                    ->setLocale($event->getLocale())
                    ->setTitle($event->getFeatureValue());
                $this->eventDispatcher->dispatch(TheliaEvents::FEATURE_AV_CREATE, $createFeatureAvEvent);

                $featureAvId = $createFeatureAvEvent->getFeatureAv()->getId();
            }
        } // Else if the FeatureProduct exists and is a free text value
        elseif ($featureProduct !== null && $event->getIsTextValue() === true) {
            // Get the FeatureAv
            $freeTextFeatureAv = FeatureAvQuery::create()
                ->filterByFeatureProduct($featureProduct)
                ->findOneByFeatureId($event->getFeatureId());

            // Get the FeatureAvI18n by locale
            $freeTextFeatureAvI18n = FeatureAvI18nQuery::create()
                ->filterById($freeTextFeatureAv->getId())
                ->findOneByLocale($event->getLocale());

            // Nothing found for this lang and the new value is not empty : create FeatureAvI18n
            if ($freeTextFeatureAvI18n === null && !empty($featureAvId)) {
                $featureAvI18n = new FeatureAvI18n();
                $featureAvI18n
                    ->setId($freeTextFeatureAv->getId())
                    ->setLocale($event->getLocale())
                    ->setTitle($event->getFeatureValue())
                    ->save();

                $featureAvId = $featureAvI18n->getId();
            } // Else if i18n exists but new value is empty : delete FeatureAvI18n
            elseif ($freeTextFeatureAvI18n !== null && empty($featureAvId)) {
                $freeTextFeatureAvI18n->delete();

                // Check if there are still some FeatureAvI18n for this FeatureAv
                $freeTextFeatureAvI18ns = FeatureAvI18nQuery::create()
                    ->findById($freeTextFeatureAv->getId());

                // If there are no more FeatureAvI18ns for this FeatureAv, remove the corresponding FeatureProduct & FeatureAv
                if (count($freeTextFeatureAvI18ns) == 0) {
                    $deleteFeatureProductEvent = new FeatureProductDeleteEvent($event->getProductId(), $event->getFeatureId());
                    $this->eventDispatcher->dispatch(TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE, $deleteFeatureProductEvent);

                    $deleteFeatureAvEvent = new FeatureAvDeleteEvent($freeTextFeatureAv->getId());
                    $this->eventDispatcher->dispatch(TheliaEvents::FEATURE_AV_DELETE, $deleteFeatureAvEvent);

                    return;
                }
            } // Else if a FeatureAvI18n is found and the new value is not empty : update existing FeatureAvI18n
            elseif ($freeTextFeatureAvI18n !== null && !empty($featureAvId)) {
                $freeTextFeatureAvI18n->setTitle($featureAvId);
                $freeTextFeatureAvI18n->save();

                $featureAvId = $freeTextFeatureAvI18n->getId();
            }
        } // Else the FeatureProduct exists and is not a free text value
        else {
            $featureAvId = $event->getFeatureValue();
        }

        $featureProduct->setFeatureAvId($featureAvId);

        $featureProduct->save();

        $event->setFeatureProduct($featureProduct);
    }

    /**
     * Delete a product feature value
     *
     * @param FeatureProductDeleteEvent $event
     */
    public function deleteFeatureProductValue(FeatureProductDeleteEvent $event)
    {
        FeatureProductQuery::create()
            ->filterByProductId($event->getProductId())
            ->filterByFeatureId($event->getFeatureId())
            ->delete()
        ;
    }

    public function deleteImagePSEAssociations(FileDeleteEvent $event)
    {
        $model = $event->getFileToDelete();

        if ($model instanceof ProductImage) {
            $model->getProductSaleElementsProductImages()->delete();
        }
    }

    public function deleteDocumentPSEAssociations(FileDeleteEvent $event)
    {
        $model = $event->getFileToDelete();

        if ($model instanceof ProductDocument) {
            $model->getProductSaleElementsProductDocuments()->delete();
        }
    }
    
    /**
     * When a feature is removed from a template, the products which are using this feature should be updated.
     *
     * @param TemplateDeleteFeatureEvent $event
     * @param string $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function deleteTemplateFeature(TemplateDeleteFeatureEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        // Detete the removed feature in all products which are using this template
        $products = ProductQuery::create()
            ->filterByTemplateId($event->getTemplate()->getId())
            ->find()
        ;
        
        foreach ($products as $product) {
            $dispatcher->dispatch(
                TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE,
                new FeatureProductDeleteEvent($product->getId(), $event->getFeatureId())
            );
        }
    }
    
    /**
     * When an attribute is removed from a template, the conbinations and PSE of products which are using this template
     * should be updated.
     *
     * @param TemplateDeleteAttributeEvent $event
     * @param string $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function deleteTemplateAttribute(TemplateDeleteAttributeEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        // Detete the removed attribute in all products which are using this template
        $pseToDelete = ProductSaleElementsQuery::create()
            ->useProductQuery()
                ->filterByTemplateId($event->getTemplate()->getId())
            ->endUse()
            ->useAttributeCombinationQuery()
                ->filterByAttributeId($event->getAttributeId())
            ->endUse()
            ->select([ ProductSaleElementsTableMap::ID ])
            ->find();
    
        $currencyId = CurrencyModel::getDefaultCurrency()->getId();
        
        foreach ($pseToDelete->getData() as $pseId) {
            $dispatcher->dispatch(
                TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT,
                new ProductSaleElementDeleteEvent(
                    $pseId,
                    $currencyId
                )
            );
        }
    }

    /**
     * Check if is a product view and if product_id is visible
     *
     * @param ViewCheckEvent $event
     * @param string $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function viewCheck(ViewCheckEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if ($event->getView() == 'product') {
            $product = ProductQuery::create()
                ->filterById($event->getViewId())
                ->filterByVisible(1)
                ->count();

            if ($product == 0) {
                $dispatcher->dispatch(TheliaEvents::VIEW_PRODUCT_ID_NOT_VISIBLE, $event);
            }
        }
    }

    /**
     * @param ViewCheckEvent $event
     * @throws NotFoundHttpException
     */
    public function viewProductIdNotVisible(ViewCheckEvent $event)
    {
        throw new NotFoundHttpException();
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::PRODUCT_CREATE                    => array("create", 128),
            TheliaEvents::PRODUCT_CLONE                     => array("cloneProduct", 128),
            TheliaEvents::PRODUCT_UPDATE                    => array("update", 128),
            TheliaEvents::PRODUCT_DELETE                    => array("delete", 128),
            TheliaEvents::PRODUCT_TOGGLE_VISIBILITY         => array("toggleVisibility", 128),

            TheliaEvents::PRODUCT_UPDATE_POSITION           => array("updatePosition", 128),
            TheliaEvents::PRODUCT_UPDATE_SEO                => array("updateSeo", 128),

            TheliaEvents::PRODUCT_ADD_CONTENT               => array("addContent", 128),
            TheliaEvents::PRODUCT_REMOVE_CONTENT            => array("removeContent", 128),
            TheliaEvents::PRODUCT_UPDATE_CONTENT_POSITION   => array("updateContentPosition", 128),

            TheliaEvents::PRODUCT_ADD_ACCESSORY             => array("addAccessory", 128),
            TheliaEvents::PRODUCT_REMOVE_ACCESSORY          => array("removeAccessory", 128),
            TheliaEvents::PRODUCT_UPDATE_ACCESSORY_POSITION => array("updateAccessoryPosition", 128),

            TheliaEvents::PRODUCT_ADD_CATEGORY              => array("addCategory", 128),
            TheliaEvents::PRODUCT_REMOVE_CATEGORY           => array("removeCategory", 128),

            TheliaEvents::PRODUCT_SET_TEMPLATE              => array("setProductTemplate", 128),

            TheliaEvents::PRODUCT_FEATURE_UPDATE_VALUE      => array("updateFeatureProductValue", 128),
            TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE      => array("deleteFeatureProductValue", 128),
            
            TheliaEvents::TEMPLATE_DELETE_ATTRIBUTE         => array("deleteTemplateAttribute", 128),
            TheliaEvents::TEMPLATE_DELETE_FEATURE           => array("deleteTemplateFeature", 128),
    
            // Those two have to be executed before
            TheliaEvents::IMAGE_DELETE                      => array("deleteImagePSEAssociations", 192),
            TheliaEvents::DOCUMENT_DELETE                   => array("deleteDocumentPSEAssociations", 192),

            TheliaEvents::VIEW_CHECK                        => array('viewCheck', 128),
            TheliaEvents::VIEW_PRODUCT_ID_NOT_VISIBLE       => array('viewProductIdNotVisible', 128),
        );
    }
}
