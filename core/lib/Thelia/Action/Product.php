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
use Thelia\Model\RewritingUrlQuery;
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
     *******************
     *
     * Vars prefixes :
     * op = Original Product
     * cp = Clone Product
     */

    /**
     * @param ProductCloneEvent $event
     */
    public function cloneProduct(ProductCloneEvent $event)
    {
        // Get important datas
        $lang = $event->getLang();
        $opId = $event->getProductId();

        $op = ProductQuery::create()
            ->findOneById($opId);

        $opDefaultI18n = ProductI18nQuery::create()
            ->filterByLocale($lang, Criteria::EQUAL)
            ->findOneById($opId);

        $opDefaultPrice = ProductPriceQuery::create()
            ->findOneByProductSaleElementsId($op->getDefaultSaleElements()->getId());

        $opPSEs = ProductSaleElementsQuery::create()
            ->orderByIsDefault(Criteria::DESC)
            ->findByProductId($opId);

        // Cloning process

        $cp = $this->createClone($op, $opDefaultI18n, $opDefaultPrice, $event);

        $dispatcher = $event->getDispatcher();

        $cp = $this->updateClone($cp, $op, $opDefaultI18n, $opDefaultPrice, $dispatcher);

        $cpPSEId = $cp->getDefaultSaleElements()->getId();

        $this->setCloneFiles($opId, $cp, $dispatcher);

        // PSEs handling
        foreach ($opPSEs as $key => $opPSE) {
            if (!$opPSE->getIsDefault()) {
                $currencyId = ProductPriceQuery::create()
                    ->findOneByProductSaleElementsId($opPSE->getId())
                    ->getCurrencyId();

                $cpPSEId = $this->createClonePSE($cp, $op, $opPSE, $key, $currencyId, $dispatcher);
            }

            $this->updateClonePSE($cp, $cpPSEId, $op, $opPSE, $key, $dispatcher);

            $this->setCloneAttributeCombination($opPSE->getId(), $cpPSEId);

            // Set PSE associated images & documents
            $opPSEImages = ProductSaleElementsProductImageQuery::create()
                ->findByProductSaleElementsId($opPSE->getId());

            $opPSEDocuments = ProductSaleElementsProductDocumentQuery::create()
                ->findByProductSaleElementsId($opPSE->getId());

            if (count($opPSEImages) > 0 || count($opPSEDocuments) > 0) {
                $this->setClonePSEAssociatedImages($cp->getId(), $cpPSEId, $opPSEImages);
                $this->setClonePSEAssociatedDocuments($cp->getId(), $cpPSEId, $opPSEDocuments);
            }
        }

        $this->setCloneFeatureCombination($opId, $cp->getId(), $dispatcher);

        $this->setCloneProductAssociatedContent($opId, $cp, $dispatcher);

        $this->setCloneI18n($lang, $op, $cp, $dispatcher);
    }

    public function createClone(ProductModel $op, $opDefaultI18n, $opDefaultPrice, ProductCloneEvent $event)
    {
        // Build event and dispatch creation of the clone product
        $createCloneEvent = new ProductCreateEvent();
        $createCloneEvent
            ->setTitle($opDefaultI18n->getTitle())
            ->setRef($event->getRef())
            ->setLocale($event->getLang())
            ->setVisible(0)
            ->setVirtual($op->getVirtual())
            ->setTaxRuleId($op->getTaxRuleId())
            ->setDefaultCategory($op->getDefaultCategoryId())
            ->setBasePrice($opDefaultPrice->getPrice())
            ->setCurrencyId($opDefaultPrice->getCurrencyId())
            ->setBaseWeight($op->getDefaultSaleElements()->getWeight());

        $event->getDispatcher()->dispatch(TheliaEvents::PRODUCT_CREATE, $createCloneEvent);

        $event->setCpId($createCloneEvent->getProduct()->getId());

        return $createCloneEvent->getProduct();
    }

    public function updateClone(ProductModel $cp, ProductModel $op, $opDefaultI18n, $opDefaultPrice, EventDispatcherInterface $dispatcher)
    {
        // Set other product's information
        $cpUpdateEvent = new ProductUpdateEvent($cp->getId());
        $cpUpdateEvent
            ->setRef($cp->getRef())
            ->setVisible($cp->getVisible())
            ->setVirtual($cp->getVirtual())
            ->setTitle($opDefaultI18n->getTitle())
            ->setLocale($opDefaultI18n->getLocale())

            ->setBasePrice($opDefaultPrice->getPrice())
            ->setBaseWeight($op->getDefaultSaleElements()->getWeight())
            ->setTaxRuleId($op->getTaxRuleId())
            ->setCurrencyId($opDefaultPrice->getCurrencyId())

            ->setChapo($opDefaultI18n->getChapo())
            ->setDescription($opDefaultI18n->getDescription())
            ->setPostscriptum($opDefaultI18n->getPostscriptum())
            ->setBrandId($op->getBrandId())

            ->setDefaultCategory($op->getDefaultCategoryId());

        $dispatcher->dispatch(TheliaEvents::PRODUCT_UPDATE, $cpUpdateEvent);

        $cp = $cpUpdateEvent->getProduct();

        // Set clone's template
        $cpUpdateTemplateEvent = new ProductSetTemplateEvent($cp, $op->getTemplateId(), $opDefaultPrice->getCurrencyId());
        $dispatcher->dispatch(TheliaEvents::PRODUCT_SET_TEMPLATE, $cpUpdateTemplateEvent);

        return $cp;
    }

    public function setCloneFiles($opId, ProductModel $cp, EventDispatcherInterface $dispatcher)
    {
        $types = ['images', 'documents'];

        $fs = new Filesystem();

        foreach ($types as $type) {
            switch ($type) {
                case 'images':
                    $opFiles = ProductImageQuery::create()
                        ->findByProductId($opId);
                    break;

                case 'documents':
                    $opFiles = ProductDocumentQuery::create()
                        ->findByProductId($opId);
                    break;
            }

            // Set clone's files
            foreach ($opFiles as $opFile) {
                $srcPath = THELIA_LOCAL_DIR . 'media' . DS . $type . DS . 'product' . DS . $opFile->getFile();
                $ext = substr($srcPath, -3);

                if ($fs->exists($srcPath)) {
                    switch ($type) {
                        case 'images':
                            $fileName = $cp->getRef().'.'.$ext;
                            $cpFile = new ProductImage();
                            break;

                        case 'documents':
                            $fileName = substr($opFile->getFile(), 0, -4).'-'.$cp->getRef().'.'.$ext;
                            $cpFile = new ProductDocument();
                            break;
                    }

                    // Copy a temporary file of the source file as it will be deleted by IMAGE_SAVE or DOCUMENT_SAVE event
                    $srcTmp = $srcPath.'.tmp';
                    $fs->copy($srcPath, $srcTmp, true);

                    // Get file mimeType
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $fileMimeType = finfo_file($finfo, $srcPath);
                    finfo_close($finfo);

                    // Get file event's parameters
                    $cpFile
                        ->setProductId($cp->getId())
                        ->setVisible($opFile->getVisible())
                        ->setPosition($opFile->getPosition())
                        ->setLocale($cp->getLocale())
                        ->setTitle($cp->getTitle());

                    $cpCopiedFile = new UploadedFile($srcPath, $fileName, $fileMimeType, filesize($srcPath), null, true);

                    // Create and dispatch event
                    $cpCreateFileEvent = new FileCreateOrUpdateEvent($cp->getId());
                    $cpCreateFileEvent
                        ->setModel($cpFile)
                        ->setUploadedFile($cpCopiedFile)
                        ->setParentName($cp->getTitle());

                    switch ($type) {
                        case 'images':
                            $dispatcher->dispatch(TheliaEvents::IMAGE_SAVE, $cpCreateFileEvent);

                            // Get original product image I18n
                            $opFileI18ns = ProductImageI18nQuery::create()
                                ->findById($opFile->getId());
                            break;

                        case 'documents':
                            $dispatcher->dispatch(TheliaEvents::DOCUMENT_SAVE, $cpCreateFileEvent);

                            // Get original product document I18n
                            $opFileI18ns = ProductDocumentI18nQuery::create()
                                ->findById($opFile->getId());
                            break;
                    }

                    // Set temporary source file as original one
                    $fs->rename($srcTmp, $srcPath);

                    // Set clone files I18n
                    foreach ($opFileI18ns as $opFileI18n) {
                        // Update file with current I18n info. Update or create I18n according to existing or absent Locale in DB
                        $cpFile
                            ->setLocale($opFileI18n->getLocale())
                            ->setTitle($opFileI18n->getTitle())
                            ->setDescription($opFileI18n->getDescription())
                            ->setChapo($opFileI18n->getChapo())
                            ->setPostscriptum($opFileI18n->getPostscriptum());

                        // Create and dispatch event
                        $cpUpdateFileEvent = new FileCreateOrUpdateEvent($cp->getId());
                        $cpUpdateFileEvent->setModel($cpFile);

                        switch ($type) {
                            case 'images':
                                $dispatcher->dispatch(TheliaEvents::IMAGE_UPDATE, $cpUpdateFileEvent);
                                break;

                            case 'documents':
                                $dispatcher->dispatch(TheliaEvents::DOCUMENT_UPDATE, $cpUpdateFileEvent);
                                break;
                        }
                    }
                } else {
                    Tlog::getInstance()->addWarning("Failed to find media file $srcPath");
                }
            }
        }
    }

    public function createClonePSE(ProductModel $cp, $opPSE, $currencyId, EventDispatcherInterface $dispatcher)
    {
        $attrCombiList = AttributeCombinationQuery::create()
            ->findByProductSaleElementsId($opPSE->getId());

        $attributeAvList = [];

        foreach ($attrCombiList as $attrCombi) {
            array_push($attributeAvList, $attrCombi);
        }

        $cpCreatePSEEvent = new ProductSaleElementCreateEvent($cp, $attributeAvList, $currencyId);
        $dispatcher->dispatch(TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT, $cpCreatePSEEvent);

        return $cpCreatePSEEvent->getProductSaleElement()->getId();
    }

    public function updateClonePSE(ProductModel $cp, $cpPSEId, ProductModel $op, $opPSE, $key, EventDispatcherInterface $dispatcher)
    {
        $opPSEPrice = ProductPriceQuery::create()
            ->findOneByProductSaleElementsId($opPSE->getId());

        $cpUpdatePSEEvent = new ProductSaleElementUpdateEvent($cp, $cpPSEId);
        $cpUpdatePSEEvent
            ->setReference($cp->getRef().'-'.($key + 1))
            ->setIsdefault($opPSE->getIsDefault())
            ->setFromDefaultCurrency(0)

            ->setWeight($opPSE->getWeight())
            ->setQuantity($opPSE->getQuantity())
            ->setOnsale($opPSE->getPromo())
            ->setIsnew($opPSE->getNewness())
            ->setEanCode($opPSE->getEanCode())
            ->setTaxRuleId($op->getTaxRuleId())

            ->setPrice($opPSEPrice->getPrice())
            ->setSalePrice($opPSEPrice->getPromoPrice())
            ->setCurrencyId($opPSEPrice->getCurrencyId());

        $dispatcher->dispatch(TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT, $cpUpdatePSEEvent);
    }

    public function setCloneAttributeCombination($opPSEId, $cpPSEId)
    {
        // Get original product attribute for current PSE
        $opAttrCombi = AttributeCombinationQuery::create()
            ->findOneByProductSaleElementsId($opPSEId);

        $cpAttrCombi = $opAttrCombi->copy();
        $cpAttrCombi
            ->setProductSaleElementsId($cpPSEId)
            ->save();
    }

    public function setClonePSEAssociatedImages($cpId, $cpPSEId, $opPSEImages)
    {
        foreach ($opPSEImages as $opPSEImage) {
            $opImagePosition = ProductImageQuery::create()
                ->findOneById($opPSEImage->getProductImageId())
                ->getPosition();

            $cpImageIdToLinkToPSE = ProductImageQuery::create()
                ->filterByProductId($cpId)
                ->findOneByPosition($opImagePosition)
                ->getId();

            $assoc = new ProductSaleElementsProductImage();
            $assoc
                ->setProductSaleElementsId($cpPSEId)
                ->setProductImageId($cpImageIdToLinkToPSE)
                ->save();
        }
    }

    public function setClonePSEAssociatedDocuments($cpId, $cpPSEId, $opPSEDocuments)
    {
        foreach ($opPSEDocuments as $opPSEDocument) {
            $opDocumentPosition = ProductDocumentQuery::create()
                ->findOneById($opPSEDocument->getProductDocumentId())
                ->getPosition();

            $cpDocumentIdToLinkToPSE = ProductDocumentQuery::create()
                ->filterByProductId($cpId)
                ->findOneByPosition($opDocumentPosition)
                ->getId();

            $assoc = new ProductSaleElementsProductDocument();
            $assoc
                ->setProductSaleElementsId($cpPSEId)
                ->setProductDocumentId($cpDocumentIdToLinkToPSE)
                ->save();
        }
    }

    public function setCloneFeatureCombination($opId, $cpId, EventDispatcherInterface $dispatcher)
    {
        // Get original product features
        $opFeatures = FeatureProductQuery::create()
            ->findByProductId($opId);

        // Set clone product features
        foreach ($opFeatures as $opFeature) {
            $cpCreateFeatureEvent = new FeatureProductUpdateEvent($cpId, $opFeature->getFeatureId(), $opFeature->getFeatureAvId());
            $dispatcher->dispatch(TheliaEvents::PRODUCT_FEATURE_UPDATE_VALUE, $cpCreateFeatureEvent);
        }
    }

    public function setCloneProductAssociatedContent($opId, ProductModel $cp, EventDispatcherInterface $dispatcher)
    {
        // Get original product associated contents
        $opAssocConts = ProductAssociatedContentQuery::create()
            ->findByProductId($opId);

        // Set clone product associated contents
        foreach ($opAssocConts as $opAssocCont) {
            $cpCreatePAC = new ProductAddContentEvent($cp, $opAssocCont->getContentId());
            $dispatcher->dispatch(TheliaEvents::PRODUCT_ADD_CONTENT, $cpCreatePAC);
        }
    }

    public function setCloneI18n($lang, ProductModel $op, ProductModel $cp, EventDispatcherInterface $dispatcher)
    {
        // Get original product's I18ns
        $opI18ns = ProductI18nQuery::create()
            ->findById($op->getId());

        $i = 1;

        foreach ($opI18ns as $opI18n) {
            $opSeoUrl = RewritingUrlQuery::create()
                ->filterByViewId($op->getId())
                ->findOneByViewLocale($opI18n->getLocale())
                ->getUrl();

            $SeoRewritings = RewritingUrlQuery::create()
                ->find();

            // Some tests not to duplicate URL
            foreach ($SeoRewritings as $SeoRewriting) {
                while ($SeoRewriting->getUrl() === $opSeoUrl) {
                    if (substr($opSeoUrl, -4) === 'html') {
                        $opSeoUrl = substr($opSeoUrl, 0, -5) . '-' . $i . '.html';
                    } elseif (substr($opSeoUrl, -3) === 'htm') {
                        $opSeoUrl = substr($opSeoUrl, 0, -4) . '-' . $i . '.htm';
                    } elseif (substr($opSeoUrl, -3) === 'php') {
                        $opSeoUrl = substr($opSeoUrl, 0, -4) . '-' . $i . '.php';
                    }
                }
            }

            // Update I18n if it's not the same language as the one at the product creation
            if ($opI18n->getLocale() != $lang) {
                $opPsePrice = ProductPriceQuery::create()
                    ->findOneByProductSaleElementsId($op->getDefaultSaleElements()->getId());

                $cpUpdateEvent = new ProductUpdateEvent($cp->getId());
                $cpUpdateEvent
                    ->setRef($cp->getRef())
                    ->setVisible($cp->getVisible())
                    ->setVirtual($cp->getVirtual())

                    ->setLocale($opI18n->getLocale())
                    ->setTitle($opI18n->getTitle())
                    ->setChapo($opI18n->getChapo())
                    ->setDescription($opI18n->getDescription())
                    ->setPostscriptum($opI18n->getPostscriptum())

                    ->setBasePrice($opPsePrice->getPrice())
                    ->setCurrencyId($opPsePrice->getCurrencyId())
                    ->setBaseWeight($op->getDefaultSaleElements()->getWeight())
                    ->setTaxRuleId($op->getTaxRuleId())
                    ->setBrandId($op->getBrandId())
                    ->setDefaultCategory($op->getDefaultCategoryId());

                $dispatcher->dispatch(TheliaEvents::PRODUCT_UPDATE, $cpUpdateEvent);
            }

            // SEO info
            $cpUpdateSeoEvent = new UpdateSeoEvent($cp->getId());
            $cpUpdateSeoEvent
                ->setLocale($opI18n->getLocale())
                ->setMetaTitle($opI18n->getMetaTitle())
                ->setMetaDescription($opI18n->getMetaDescription())
                ->setMetaKeywords($opI18n->getMetaKeywords())
                ->setUrl($opSeoUrl);
            $dispatcher->dispatch(TheliaEvents::PRODUCT_UPDATE_SEO, $cpUpdateSeoEvent);

            $i++;
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
            TheliaEvents::PRODUCT_CLONE_CREATE              => array("cloneProduct", 128),
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
