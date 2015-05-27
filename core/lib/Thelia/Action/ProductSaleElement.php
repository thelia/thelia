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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCloneEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Product as ProductModel;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductPrice;
use Thelia\Model\AttributeCombination;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementDeleteEvent;
use Thelia\Model\ProductSaleElementsProductDocument;
use Thelia\Model\ProductSaleElementsProductDocumentQuery;
use Thelia\Model\ProductSaleElementsProductImage;
use Thelia\Model\ProductSaleElementsProductImageQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Model\ProductPriceQuery;
use Propel\Runtime\Propel;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\Map\AttributeCombinationTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Event\Product\ProductCombinationGenerationEvent;
use Propel\Runtime\Connection\ConnectionInterface;

class ProductSaleElement extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new product sale element, with or without combination
     *
     * @param  ProductSaleElementCreateEvent $event
     * @throws \Exception
     */
    public function create(ProductSaleElementCreateEvent $event)
    {
        $con = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);

        $con->beginTransaction();

        try {
            // Check if we have a PSE without combination, this is the "default" PSE. Attach the combination to this PSE
            $salesElement = ProductSaleElementsQuery::create()
                ->filterByProductId($event->getProduct()->getId())
                ->joinAttributeCombination(null, Criteria::LEFT_JOIN)
                ->add(AttributeCombinationTableMap::PRODUCT_SALE_ELEMENTS_ID, null, Criteria::ISNULL)
                ->findOne($con);

            if ($salesElement == null) {
                // Create a new default product sale element
                $salesElement = $event->getProduct()->createProductSaleElement($con, 0, 0, 0, $event->getCurrencyId(), false);
            } else {
                // This (new) one is the default
                $salesElement->setIsDefault(true)->save($con);
            }

            // Attach combination, if defined.
            $combinationAttributes = $event->getAttributeAvList();

            if (count($combinationAttributes) > 0) {
                foreach ($combinationAttributes as $attributeAvId) {
                    $attributeAv = AttributeAvQuery::create()->findPk($attributeAvId);

                    if ($attributeAv !== null) {
                        $attributeCombination = new AttributeCombination();

                        $attributeCombination
                            ->setAttributeAvId($attributeAvId)
                            ->setAttribute($attributeAv->getAttribute())
                            ->setProductSaleElements($salesElement)
                            ->save($con);
                    }
                }
            }

            $event->setProductSaleElement($salesElement);

            // Store all the stuff !
            $con->commit();
        } catch (\Exception $ex) {
            $con->rollback();

            throw $ex;
        }
    }

    /**
     * Update an existing product sale element
     *
     * @param  ProductSaleElementUpdateEvent $event
     * @throws \Exception
     */
    public function update(ProductSaleElementUpdateEvent $event)
    {
        $salesElement = ProductSaleElementsQuery::create()->findPk($event->getProductSaleElementId());

        $con = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);

        $con->beginTransaction();

        try {
            // Update the product's tax rule
            $event->getProduct()->setTaxRuleId($event->getTaxRuleId())->save($con);

            // If product sale element is not defined, create it.
            if ($salesElement == null) {
                $salesElement = new ProductSaleElements();

                $salesElement->setProduct($event->getProduct());
            }

            // If this PSE is the default one, be sure to have *only one* default for this product
            if ($event->getIsDefault()) {
                ProductSaleElementsQuery::create()
                    ->filterByProduct($event->getProduct())
                    ->filterByIsDefault(true)
                    ->filterById($event->getProductSaleElementId(), Criteria::NOT_EQUAL)
                    ->update(['IsDefault' => false], $con)
                ;
            }

            // Update sale element
            $salesElement
                ->setRef($event->getReference())
                ->setQuantity($event->getQuantity())
                ->setPromo($event->getOnsale())
                ->setNewness($event->getIsnew())
                ->setWeight($event->getWeight())
                ->setIsDefault($event->getIsDefault())
                ->setEanCode($event->getEanCode())
                ->save()
            ;

            // Update/create price for current currency
            $productPrice = ProductPriceQuery::create()
                ->filterByCurrencyId($event->getCurrencyId())
                ->filterByProductSaleElementsId($salesElement->getId())
                ->findOne($con);

            // If price is not defined, create it.
            if ($productPrice == null) {
                $productPrice = new ProductPrice();

                $productPrice
                    ->setProductSaleElements($salesElement)
                    ->setCurrencyId($event->getCurrencyId())
                ;
            }

            // Check if we have to store the price
            $productPrice->setFromDefaultCurrency($event->getFromDefaultCurrency());

            if ($event->getFromDefaultCurrency() == 0) {
                // Store the price
                $productPrice
                    ->setPromoPrice($event->getSalePrice())
                    ->setPrice($event->getPrice())
                ;
            } else {
                // Do not store the price.
                $productPrice
                    ->setPromoPrice(0)
                    ->setPrice(0)
                ;
            }

            $productPrice->save($con);

            // Store all the stuff !
            $con->commit();
        } catch (\Exception $ex) {
            $con->rollback();

            throw $ex;
        }
    }

    /**
     * Delete a product sale element
     *
     * @param  ProductSaleElementDeleteEvent $event
     * @throws \Exception
     */
    public function delete(ProductSaleElementDeleteEvent $event)
    {
        if (null !== $pse = ProductSaleElementsQuery::create()->findPk($event->getProductSaleElementId())) {
            $product = $pse->getProduct();

            $con = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);

            $con->beginTransaction();

            try {
                $pse->delete($con);

                if ($product->countSaleElements($con) <= 0) {
                    // If we just deleted the last PSE, create a default one
                    $product->createProductSaleElement($con, 0, 0, 0, $event->getCurrencyId(), true);
                } elseif ($pse->getIsDefault()) {
                    // If we deleted the default PSE, make the last created one the default
                    $newDefaultPse = ProductSaleElementsQuery::create()
                        ->filterByProductId($product->getId())
                        ->filterById($pse->getId(), Criteria::NOT_EQUAL)
                        ->orderByCreatedAt(Criteria::DESC)
                        ->findOne($con)
                    ;

                    if (null !== $newDefaultPse) {
                        $newDefaultPse->setIsDefault(true)->save($con);
                    }
                }

                // Store all the stuff !
                $con->commit();
            } catch (\Exception $ex) {
                $con->rollback();

                throw $ex;
            }
        }
    }

    /**
     * Generate combinations. All existing combinations for the product are deleted.
     *
     * @param  ProductCombinationGenerationEvent $event
     * @throws \Exception
     */
    public function generateCombinations(ProductCombinationGenerationEvent $event)
    {
        $con = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);

        $con->beginTransaction();

        try {
            // Delete all product's productSaleElement
            ProductSaleElementsQuery::create()->filterByProductId($event->product->getId())->delete();

            $isDefault = true;

            // Create all combinations
            foreach ($event->getCombinations() as $combinationAttributesAvIds) {
                // Create the PSE
                $saleElement = $event->getProduct()->createProductSaleElement(
                    $con,
                    $event->getWeight(),
                    $event->getPrice(),
                    $event->getSalePrice(),
                    $event->getCurrencyId(),
                    $isDefault,
                    $event->getOnsale(),
                    $event->getIsnew(),
                    $event->getQuantity(),
                    $event->getEanCode(),
                    $event->getReference()
                );

                $isDefault = false;

                $this->createCombination($con, $saleElement, $combinationAttributesAvIds);
            }

            // Store all the stuff !
            $con->commit();
        } catch (\Exception $ex) {
            $con->rollback();

            throw $ex;
        }
    }

    /**
     * Create a combination for a given product sale element
     *
     * @param ConnectionInterface $con                   the Propel connection
     * @param ProductSaleElements $salesElement          the product sale element
     * @param array               $combinationAttributes an array oif attributes av IDs
     */
    protected function createCombination(ConnectionInterface $con, ProductSaleElements $salesElement, $combinationAttributes)
    {
        foreach ($combinationAttributes as $attributeAvId) {
            $attributeAv = AttributeAvQuery::create()->findPk($attributeAvId);

            if ($attributeAv !== null) {
                $attributeCombination = new AttributeCombination();

                $attributeCombination
                    ->setAttributeAvId($attributeAvId)
                    ->setAttribute($attributeAv->getAttribute())
                    ->setProductSaleElements($salesElement)
                ->save($con);
            }
        }
    }

    /*******************
     * CLONING PROCESS *
     *******************/

    /**
     * Clone product's PSEs and associated datas
     *
     * @param ProductSaleElementCloneEvent $event
     */
    public function clonePSE(ProductSaleElementCloneEvent $event)
    {
        $originalProduct = $event->getOriginalProduct();
        $clonedProduct = $event->getClonedProduct();
        $dispatcher = $event->getDispatcher();

        // Get original product's PSEs
        $originalProductPSEs = ProductSaleElementsQuery::create()
            ->orderByIsDefault(Criteria::DESC)
            ->findByProductId($originalProduct->getId());

        // Handle PSEs
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
                $this->clonePSEAssociatedImages($clonedProduct->getId(), $clonedProductPSEId, $originalProductPSEImages);
            }

            // PSE associated documents
            $originalProductPSEDocuments = ProductSaleElementsProductDocumentQuery::create()
                ->findByProductSaleElementsId($originalProductPSE->getId());

            if (null !== $originalProductPSEDocuments) {
                $this->clonePSEAssociatedDocuments($clonedProduct->getId(), $clonedProductPSEId, $originalProductPSEDocuments);
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

    public function clonePSEAssociatedImages($clonedProductId, $clonedProductPSEId, $originalProductPSEImages)
    {
        foreach ($originalProductPSEImages as $originalProductPSEImage) {
            $originalProductImagePosition = ProductImageQuery::create()
                ->select(['POSITION'])
                ->findPk($originalProductPSEImage->getProductImageId());

            $clonedProductImageIdToLinkToPSE = ProductImageQuery::create()
                ->filterByProductId($clonedProductId)
                ->filterByPosition($originalProductImagePosition)
                ->select(['ID'])
                ->findOne();

            $assoc = new ProductSaleElementsProductImage();
            $assoc
                ->setProductSaleElementsId($clonedProductPSEId)
                ->setProductImageId($clonedProductImageIdToLinkToPSE)
                ->save();
        }
    }

    public function clonePSEAssociatedDocuments($clonedProductId, $clonedProductPSEId, $originalProductPSEDocuments)
    {
        foreach ($originalProductPSEDocuments as $originalProductPSEDocument) {
            $originalProductDocumentPosition = ProductDocumentQuery::create()
                ->select(['POSITION'])
                ->findPk($originalProductPSEDocument->getProductDocumentId());

            $clonedProductDocumentIdToLinkToPSE = ProductDocumentQuery::create()
                ->filterByProductId($clonedProductId)
                ->filterByPosition($originalProductDocumentPosition)
                ->select(['ID'])
                ->findOne();

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
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT    => array("create", 128),
            TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT => array("update", 128),
            TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT => array("delete", 128),
            TheliaEvents::PRODUCT_COMBINATION_GENERATION      => array("generateCombinations", 128),
            TheliaEvents::PSE_CLONE                           => array("clonePSE", 128)
        );
    }
}
