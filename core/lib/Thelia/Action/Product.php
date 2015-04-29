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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductQuery;
use Thelia\Model\Product as ProductModel;
use Thelia\Model\ProductAssociatedContent;
use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\ProductCategory;
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

            // Those two has to be executed before
            TheliaEvents::IMAGE_DELETE                      => array("deleteImagePSEAssociations", 192),
            TheliaEvents::DOCUMENT_DELETE                      => array("deleteDocumentPSEAssociations", 192),
        );
    }
}
