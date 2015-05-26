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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementDeleteEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Log\Tlog;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductDocumentI18nQuery;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductImageI18nQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\Product as ProductModel;
use Thelia\Model\ProductAssociatedContent;
use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\ProductCategory;
use Thelia\Model\ProductSaleElementsProductDocument;
use Thelia\Model\ProductSaleElementsProductDocumentQuery;
use Thelia\Model\ProductSaleElementsProductImage;
use Thelia\Model\ProductSaleElementsProductImageQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\Accessory;
use Thelia\Model\FeatureProduct;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\Product\ProductToggleVisibilityEvent;
use Thelia\Core\Event\Product\ProductAddContentEvent;
use Thelia\Core\Event\Product\ProductDeleteContentEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Core\Event\FeatureProduct\FeatureProductUpdateEvent;
use Thelia\Core\Event\FeatureProduct\FeatureProductDeleteEvent;
use Thelia\Core\Event\Product\ProductSetTemplateEvent;
use Thelia\Core\Event\Product\ProductDeleteCategoryEvent;
use Thelia\Core\Event\Product\ProductAddCategoryEvent;
use Thelia\Core\Event\Product\ProductAddAccessoryEvent;
use Thelia\Core\Event\Product\ProductDeleteAccessoryEvent;
use Propel\Runtime\Propel;

class Product extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new product entry
     *
     * @param \Thelia\Core\Event\Product\ProductCreateEvent $event
     */
    public function create(ProductCreateEvent $event)
    {
        $product = new ProductModel();

        $product
            ->setDispatcher($event->getDispatcher())

            ->setRef($event->getRef())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setVisible($event->getVisible() ? 1 : 0)
            ->setVirtual($event->getVirtual() ? 1 : 0)

            // Set the default tax rule to this product
            ->setTaxRule(TaxRuleQuery::create()->findOneByIsDefault(true))

            ->create(
                $event->getDefaultCategory(),
                $event->getBasePrice(),
                $event->getCurrencyId(),
                $event->getTaxRuleId(),
                $event->getBaseWeight()
            )
        ;

        // Set the product template, if one is defined in the category tree
        $parentCatId = $event->getDefaultCategory();

        while ($parentCatId > 0) {
            if (null === $cat = CategoryQuery::create()->findPk($parentCatId)) {
                break;
            }

            if ($cat->getDefaultTemplateId()) {
                $product->setTemplateId($cat->getDefaultTemplateId())->save();
                break;
            }

            $parentCatId = $cat->getParent();
        }

        $event->setProduct($product);
    }

    /*******************
     * CLONING PROCESS *
     *******************/

    /**
     * @param ProductCloneEvent $event
     */
    public function cloneProduct(ProductCloneEvent $event)
    {
        // Get important datas
        $lang = $event->getLang();
        $originalProduct = $event->getOriginalProduct();
        $dispatcher = $event->getDispatcher();

        $originalProductDefaultI18n = ProductI18nQuery::create()
            ->findPk([$originalProduct->getId(), $lang]);

        $originalProductDefaultPrice = ProductPriceQuery::create()
            ->findOneByProductSaleElementsId($originalProduct->getDefaultSaleElements()->getId());
/*
        $originalProductPSEs = ProductSaleElementsQuery::create()
            ->orderByIsDefault(Criteria::DESC)
            ->findByProductId($originalProduct->getId());
*/
        // Cloning process

        $clonedProduct = $this->createClone($originalProduct, $originalProductDefaultI18n, $originalProductDefaultPrice, $event);

        $this->removeCloneDefaultPSE($clonedProduct, $originalProductDefaultPrice, $dispatcher);

        $clonedProduct = $this->updateClone($clonedProduct, $originalProduct, $originalProductDefaultPrice, $dispatcher);

        $this->setCloneFeatureCombination($originalProduct->getId(), $clonedProduct->getId(), $dispatcher);

        $this->setCloneAssociatedContent($originalProduct->getId(), $clonedProduct, $dispatcher);

/*
        $this->setCloneFiles($originalProduct->getId(), $clonedProduct, $dispatcher);

        // PSEs handling
        foreach ($originalProductPSEs as $key => $originalProductPSE) {
            $currencyId = ProductPriceQuery::create()
                ->findOneByProductSaleElementsId($originalProductPSE->getId())
                ->getCurrencyId();

            $clonedProductPSEId = $this->createClonePSE($clonedProduct, $originalProductPSE, $currencyId, $dispatcher);

            $this->updateClonePSE($clonedProduct, $clonedProductPSEId, $originalProduct, $originalProductPSE, $key, $dispatcher);

            // PSE associated images
            $originalProductPSEImages = ProductSaleElementsProductImageQuery::create()
                ->findByProductSaleElementsId($originalProductPSE->getId());

            if (null !== $originalProductPSEImages) {
                $this->setClonePSEAssociatedImages($clonedProduct->getId(), $clonedProductPSEId, $originalProductPSEImages);
            }

            // PSE associated documents
            $originalProductPSEDocuments = ProductSaleElementsProductDocumentQuery::create()
                ->findByProductSaleElementsId($originalProductPSE->getId());

            if (null !== $originalProductPSEDocuments) {
                $this->setClonePSEAssociatedDocuments($clonedProduct->getId(), $clonedProductPSEId, $originalProductPSEDocuments);
            }
        }
*/
    }

    public function createClone(ProductModel $originalProduct, $originalProductDefaultI18n, $originalProductDefaultPrice, ProductCloneEvent $event)
    {
        // Build event and dispatch creation of the clone product
        $createCloneEvent = new ProductCreateEvent();
        $createCloneEvent
            ->setTitle($originalProductDefaultI18n->getTitle())
            ->setRef($event->getRef())
            ->setLocale($event->getLang())
            ->setVisible(0)
            ->setVirtual($originalProduct->getVirtual())
            ->setTaxRuleId($originalProduct->getTaxRuleId())
            ->setDefaultCategory($originalProduct->getDefaultCategoryId())
            ->setBasePrice($originalProductDefaultPrice->getPrice())
            ->setCurrencyId($originalProductDefaultPrice->getCurrencyId())
            ->setBaseWeight($originalProduct->getDefaultSaleElements()->getWeight());

        $event->getDispatcher()->dispatch(TheliaEvents::PRODUCT_CREATE, $createCloneEvent);

        // Set cloned product ID to ProductCloneEvent for redirection
        $event->setClonedProduct($createCloneEvent->getProduct());

        return $createCloneEvent->getProduct();
    }

    public function removeCloneDefaultPSE(ProductModel $clonedProduct, $originalProductDefaultPrice, EventDispatcherInterface $dispatcher)
    {
        $removeClonePSEEvent = new ProductSaleElementDeleteEvent($clonedProduct->getDefaultSaleElements()->getId(), $originalProductDefaultPrice->getCurrencyId());
        $dispatcher->dispatch(TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT, $removeClonePSEEvent);
    }

    public function updateClone(ProductModel $clonedProduct, ProductModel $originalProduct, $originalProductDefaultPrice, EventDispatcherInterface $dispatcher)
    {
        // Get original product's I18ns
        $originalProductI18ns = ProductI18nQuery::create()
            ->findById($originalProduct->getId());

        foreach ($originalProductI18ns as $originalProductI18n) {

            $clonedProductUpdateEvent = new ProductUpdateEvent($clonedProduct->getId());
            $clonedProductUpdateEvent
                ->setRef($clonedProduct->getRef())
                ->setVisible($clonedProduct->getVisible())
                ->setVirtual($clonedProduct->getVirtual())

                ->setLocale($originalProductI18n->getLocale())
                ->setTitle($originalProductI18n->getTitle())
                ->setChapo($originalProductI18n->getChapo())
                ->setDescription($originalProductI18n->getDescription())
                ->setPostscriptum($originalProductI18n->getPostscriptum())

                ->setBasePrice($originalProductDefaultPrice->getPrice())
                ->setCurrencyId($originalProductDefaultPrice->getCurrencyId())
                ->setBaseWeight($originalProduct->getDefaultSaleElements()->getWeight())
                ->setTaxRuleId($originalProduct->getTaxRuleId())
                ->setBrandId($originalProduct->getBrandId())
                ->setDefaultCategory($originalProduct->getDefaultCategoryId());

            $dispatcher->dispatch(TheliaEvents::PRODUCT_UPDATE, $clonedProductUpdateEvent);

            // SEO info
            $originalProductSeoUrl = $clonedProduct->generateRewrittenUrl($originalProductI18n->getLocale());

            $clonedProductUpdateSeoEvent = new UpdateSeoEvent($clonedProduct->getId());
            $clonedProductUpdateSeoEvent
                ->setLocale($originalProductI18n->getLocale())
                ->setMetaTitle($originalProductI18n->getMetaTitle())
                ->setMetaDescription($originalProductI18n->getMetaDescription())
                ->setMetaKeywords($originalProductI18n->getMetaKeywords())
                ->setUrl($originalProductSeoUrl);
            $dispatcher->dispatch(TheliaEvents::PRODUCT_UPDATE_SEO, $clonedProductUpdateSeoEvent);
        }

        $clonedProduct = $clonedProductUpdateEvent->getProduct();

        // Set clone's template
        $clonedProductUpdateTemplateEvent = new ProductSetTemplateEvent($clonedProduct, $originalProduct->getTemplateId(), $originalProductDefaultPrice->getCurrencyId());
        $dispatcher->dispatch(TheliaEvents::PRODUCT_SET_TEMPLATE, $clonedProductUpdateTemplateEvent);

        return $clonedProduct;
    }

    public function setCloneFeatureCombination($originalProductId, $clonedProductId, EventDispatcherInterface $dispatcher)
    {
        // Get original product features
        $originalProductFeatures = FeatureProductQuery::create()
            ->findByProductId($originalProductId);

        // Set clone product features
        foreach ($originalProductFeatures as $originalProductFeature) {
            $clonedProductCreateFeatureEvent = new FeatureProductUpdateEvent($clonedProductId, $originalProductFeature->getFeatureId(), $originalProductFeature->getFeatureAvId());
            $dispatcher->dispatch(TheliaEvents::PRODUCT_FEATURE_UPDATE_VALUE, $clonedProductCreateFeatureEvent);
        }
    }

    public function setCloneAssociatedContent($originalProductId, ProductModel $clonedProduct, EventDispatcherInterface $dispatcher)
    {
        // Get original product associated contents
        $originalProductAssocConts = ProductAssociatedContentQuery::create()
            ->findByProductId($originalProductId);

        // Set clone product associated contents
        foreach ($originalProductAssocConts as $originalProductAssocCont) {
            $clonedProductCreatePAC = new ProductAddContentEvent($clonedProduct, $originalProductAssocCont->getContentId());
            $dispatcher->dispatch(TheliaEvents::PRODUCT_ADD_CONTENT, $clonedProductCreatePAC);
        }
    }


    public function setCloneFiles($originalProductId, ProductModel $clonedProduct, EventDispatcherInterface $dispatcher)
    {
        $types = ['images', 'documents'];

        $fs = new Filesystem();

        foreach ($types as $type) {
            switch ($type) {
                case 'images':
                    $originalProductFiles = ProductImageQuery::create()
                        ->findByProductId($originalProductId);
                    break;

                case 'documents':
                    $originalProductFiles = ProductDocumentQuery::create()
                        ->findByProductId($originalProductId);
                    break;
            }

            // Set clone's files
            foreach ($originalProductFiles as $originalProductFile) {
                $srcPath = $originalProductFile->getUploadDir() . DS . $originalProductFile->getFile();

                if ($fs->exists($srcPath)) {
                    $ext = pathinfo($srcPath, PATHINFO_EXTENSION);

                    switch ($type) {
                        case 'images':
                            $fileName = $clonedProduct->getRef().'.'.$ext;
                            $clonedProductFile = new ProductImage();
                            break;

                        case 'documents':
                            $fileName = pathinfo($originalProductFile->getFile(), PATHINFO_FILENAME).'-'.$clonedProduct->getRef().'.'.$ext;
                            $clonedProductFile = new ProductDocument();
                            break;
                    }

                    // Copy a temporary file of the source file as it will be deleted by IMAGE_SAVE or DOCUMENT_SAVE event
                    $srcTmp = $srcPath.'.tmp';
                    $fs->copy($srcPath, $srcTmp, true);

                    // Get file mimeType
                    $finfo = new \finfo();
                    $fileMimeType = $finfo->file($srcPath, FILEINFO_MIME_TYPE);

                    // Get file event's parameters
                    $clonedProductFile
                        ->setProductId($clonedProduct->getId())
                        ->setVisible($originalProductFile->getVisible())
                        ->setPosition($originalProductFile->getPosition())
                        ->setLocale($clonedProduct->getLocale())
                        ->setTitle($clonedProduct->getTitle());

                    $clonedProductCopiedFile = new UploadedFile($srcPath, $fileName, $fileMimeType, filesize($srcPath), null, true);

                    // Create and dispatch event
                    $clonedProductCreateFileEvent = new FileCreateOrUpdateEvent($clonedProduct->getId());
                    $clonedProductCreateFileEvent
                        ->setModel($clonedProductFile)
                        ->setUploadedFile($clonedProductCopiedFile)
                        ->setParentName($clonedProduct->getTitle());

                    switch ($type) {
                        case 'images':
                            $dispatcher->dispatch(TheliaEvents::IMAGE_SAVE, $clonedProductCreateFileEvent);

                            // Get original product image I18n
                            $originalProductFileI18ns = ProductImageI18nQuery::create()
                                ->findById($originalProductFile->getId());
                            break;

                        case 'documents':
                            $dispatcher->dispatch(TheliaEvents::DOCUMENT_SAVE, $clonedProductCreateFileEvent);

                            // Get original product document I18n
                            $originalProductFileI18ns = ProductDocumentI18nQuery::create()
                                ->findById($originalProductFile->getId());
                            break;
                    }

                    // Set temporary source file as original one
                    $fs->rename($srcTmp, $srcPath);

                    // Set clone files I18n
                    foreach ($originalProductFileI18ns as $originalProductFileI18n) {
                        // Update file with current I18n info. Update or create I18n according to existing or absent Locale in DB
                        $clonedProductFile
                            ->setLocale($originalProductFileI18n->getLocale())
                            ->setTitle($originalProductFileI18n->getTitle())
                            ->setDescription($originalProductFileI18n->getDescription())
                            ->setChapo($originalProductFileI18n->getChapo())
                            ->setPostscriptum($originalProductFileI18n->getPostscriptum());

                        // Create and dispatch event
                        $clonedProductUpdateFileEvent = new FileCreateOrUpdateEvent($clonedProduct->getId());
                        $clonedProductUpdateFileEvent->setModel($clonedProductFile);

                        switch ($type) {
                            case 'images':
                                $dispatcher->dispatch(TheliaEvents::IMAGE_UPDATE, $clonedProductUpdateFileEvent);
                                break;

                            case 'documents':
                                $dispatcher->dispatch(TheliaEvents::DOCUMENT_UPDATE, $clonedProductUpdateFileEvent);
                                break;
                        }
                    }
                } else {
                    Tlog::getInstance()->addWarning("Failed to find media file $srcPath");
                }
            }
        }
    }


    public function createClonePSE(ProductModel $clonedProduct, $originalProductPSE, $currencyId, EventDispatcherInterface $dispatcher)
    {
        $attributeCombinationList = AttributeCombinationQuery::create()
            ->select(['ATTRIBUTE_AV_ID'])
            ->findByProductSaleElementsId($originalProductPSE->getId());


        $clonedProductCreatePSEEvent = new ProductSaleElementCreateEvent($clonedProduct, $attributeCombinationList, $currencyId);
        $dispatcher->dispatch(TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT, $clonedProductCreatePSEEvent);

        return $clonedProductCreatePSEEvent->getProductSaleElement()->getId();
    }

    public function updateClonePSE(ProductModel $clonedProduct, $clonedProductPSEId, ProductModel $originalProduct, $originalProductPSE, $key, EventDispatcherInterface $dispatcher)
    {
        $originalProductPSEPrice = ProductPriceQuery::create()
            ->findOneByProductSaleElementsId($originalProductPSE->getId());

        $clonedProductUpdatePSEEvent = new ProductSaleElementUpdateEvent($clonedProduct, $clonedProductPSEId);
        $clonedProductUpdatePSEEvent
            ->setReference($clonedProduct->getRef().'-'.($key + 1))
            ->setIsdefault($originalProductPSE->getIsDefault())
            ->setFromDefaultCurrency(0)

            ->setWeight($originalProductPSE->getWeight())
            ->setQuantity($originalProductPSE->getQuantity())
            ->setOnsale($originalProductPSE->getPromo())
            ->setIsnew($originalProductPSE->getNewness())
            ->setEanCode($originalProductPSE->getEanCode())
            ->setTaxRuleId($originalProduct->getTaxRuleId())

            ->setPrice($originalProductPSEPrice->getPrice())
            ->setSalePrice($originalProductPSEPrice->getPromoPrice())
            ->setCurrencyId($originalProductPSEPrice->getCurrencyId());

        $dispatcher->dispatch(TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT, $clonedProductUpdatePSEEvent);
    }

    public function setClonePSEAssociatedImages($clonedProductId, $clonedProductPSEId, $originalProductPSEImages)
    {
        foreach ($originalProductPSEImages as $originalProductPSEImage) {
            $originalProductImagePosition = ProductImageQuery::create()
                ->findPk($originalProductPSEImage->getProductImageId())
                ->getPosition();

            $clonedProductImageIdToLinkToPSE = ProductImageQuery::create()
                ->filterByProductId($clonedProductId)
                ->findOneByPosition($originalProductImagePosition)
                ->getId();

            $assoc = new ProductSaleElementsProductImage();
            $assoc
                ->setProductSaleElementsId($clonedProductPSEId)
                ->setProductImageId($clonedProductImageIdToLinkToPSE)
                ->save();
        }
    }

    public function setClonePSEAssociatedDocuments($clonedProductId, $clonedProductPSEId, $originalProductPSEDocuments)
    {
        foreach ($originalProductPSEDocuments as $originalProductPSEDocument) {
            $originalProductDocumentPosition = ProductDocumentQuery::create()
                ->findPk($originalProductPSEDocument->getProductDocumentId())
                ->getPosition();

            $clonedProductDocumentIdToLinkToPSE = ProductDocumentQuery::create()
                ->filterByProductId($clonedProductId)
                ->findOneByPosition($originalProductDocumentPosition)
                ->getId();

            $assoc = new ProductSaleElementsProductDocument();
            $assoc
                ->setProductSaleElementsId($clonedProductPSEId)
                ->setProductDocumentId($clonedProductDocumentIdToLinkToPSE)
                ->save();
        }
    }

    /***************
     * END CLONING *
     ***************/

    /**
     * Change a product
     *
     * @param \Thelia\Core\Event\Product\ProductUpdateEvent $event
     */
    public function update(ProductUpdateEvent $event)
    {
        if (null !== $product = ProductQuery::create()->findPk($event->getProductId())) {
            $con = Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);
            $con->beginTransaction();

            try {
                $product
                    ->setDispatcher($event->getDispatcher())
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

                // Update default category (ifd required)
                $product->updateDefaultCategory($event->getDefaultCategory());

                $event->setProduct($product);
                $con->commit();
            } catch (PropelException $e) {
                $con->rollBack();
                throw $e;
            }
        }
    }

    /**
     * Change a product SEO
     *
     * @param \Thelia\Core\Event\UpdateSeoEvent $event
     */
    public function updateSeo(UpdateSeoEvent $event)
    {
        return $this->genericUpdateSeo(ProductQuery::create(), $event);
    }

    /**
     * Delete a product entry
     *
     * @param \Thelia\Core\Event\Product\ProductDeleteEvent $event
     */
    public function delete(ProductDeleteEvent $event)
    {
        if (null !== $product = ProductQuery::create()->findPk($event->getProductId())) {
            $product
                ->setDispatcher($event->getDispatcher())
                ->delete()
            ;

            $event->setProduct($product);
        }
    }

    /**
     * Toggle product visibility. No form used here
     *
     * @param ActionEvent $event
     */
    public function toggleVisibility(ProductToggleVisibilityEvent $event)
    {
        $product = $event->getProduct();

        $product
            ->setDispatcher($event->getDispatcher())
            ->setVisible($product->getVisible() ? false : true)
            ->save()
            ;

        $event->setProduct($product);
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param ProductChangePositionEvent $event
     */
    public function updatePosition(UpdatePositionEvent $event)
    {
        $this->genericUpdatePosition(ProductQuery::create(), $event);
    }

    public function addContent(ProductAddContentEvent $event)
    {
        if (ProductAssociatedContentQuery::create()
            ->filterByContentId($event->getContentId())
             ->filterByProduct($event->getProduct())->count() <= 0) {
            $content = new ProductAssociatedContent();

            $content
                ->setDispatcher($event->getDispatcher())
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
                ->setDispatcher($event->getDispatcher())
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
            $productCategory = new ProductCategory();

            $productCategory
                ->setProduct($event->getProduct())
                ->setCategoryId($event->getCategoryId())
                ->setDefaultCategory(false)
                ->save()
            ;
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
                ->setDispatcher($event->getDispatcher())
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
                ->setDispatcher($event->getDispatcher())
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

            // Delete all product feature relations
            if (null !== $featureProducts = FeatureProductQuery::create()->findByProductId($product->getId())) {
                /** @var \Thelia\Model\FeatureProduct $featureProduct */
                foreach ($featureProducts as $featureProduct) {
                    $eventDelete = new FeatureProductDeleteEvent($product->getId(), $featureProduct->getFeatureId());

                    $event->getDispatcher()->dispatch(TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE, $eventDelete);
                }
            }

            // Delete all product attributes sale elements
            AttributeCombinationQuery::create()
                ->filterByProductSaleElements($product->getProductSaleElementss())
                ->delete($con)
            ;

            //Delete all productSaleElements except the default one (to keep price, weight, ean, etc...)
            ProductSaleElementsQuery::create()
                ->filterByProduct($product)
                ->filterByIsDefault(1, Criteria::NOT_EQUAL)
                ->delete($con)
            ;

            // Update the product template
            $template_id = $event->getTemplateId();

            // Set it to null if it's zero.
            if ($template_id <= 0) {
                $template_id = null;
            }

            $product->setTemplateId($template_id)->save($con);

            //Be sure that the product has a default productSaleElements
            /** @var \Thelia\Model\ProductSaleElements $defaultPse */
            if (null == $defaultPse = ProductSaleElementsQuery::create()
                    ->filterByProduct($product)
                    ->filterByIsDefault(1)
                    ->findOne()) {
                // Create a new default product sale element
                $product->createProductSaleElement($con, 0, 0, 0, $event->getCurrencyId(), true);
            }

            $product->clearProductSaleElementss();

            $event->setProduct($product);

            // Store all the stuff !
            $con->commit();
        } catch (\Exception $ex) {
            $con->rollback();

            throw $ex;
        }
    }

    /**
     * Changes accessry position, selecting absolute ou relative change.
     *
     * @param ProductChangePositionEvent $event
     */
    public function updateAccessoryPosition(UpdatePositionEvent $event)
    {
        return $this->genericUpdatePosition(AccessoryQuery::create(), $event);
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param ProductChangePositionEvent $event
     */
    public function updateContentPosition(UpdatePositionEvent $event)
    {
        return $this->genericUpdatePosition(ProductAssociatedContentQuery::create(), $event);
    }

    /**
     * Update the value of a product feature.
     *
     * @param FeatureProductUpdateEvent $event
     */
    public function updateFeatureProductValue(FeatureProductUpdateEvent $event)
    {
        // If the feature is not free text, it may have one ore more values.
        // If the value exists, we do not change it
        // If the value does not exists, we create it.
        //
        // If the feature is free text, it has only a single value.
        // Etiher create or update it.

        $featureProductQuery = FeatureProductQuery::create()
            ->filterByFeatureId($event->getFeatureId())
            ->filterByProductId($event->getProductId())
        ;

        if ($event->getIsTextValue() !== true) {
            $featureProductQuery->filterByFeatureAvId($event->getFeatureValue());
        }

        $featureProduct = $featureProductQuery->findOne();

        if ($featureProduct == null) {
            $featureProduct = new FeatureProduct();

            $featureProduct
                ->setDispatcher($event->getDispatcher())

                ->setProductId($event->getProductId())
                ->setFeatureId($event->getFeatureId())
            ;
        }

        if ($event->getIsTextValue() == true) {
            $featureProduct->setFreeTextValue($event->getFeatureValue());
        } else {
            $featureProduct->setFeatureAvId($event->getFeatureValue());
        }

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

            // Those two have to be executed before
            TheliaEvents::IMAGE_DELETE                      => array("deleteImagePSEAssociations", 192),
            TheliaEvents::DOCUMENT_DELETE                   => array("deleteDocumentPSEAssociations", 192),
        );
    }
}
