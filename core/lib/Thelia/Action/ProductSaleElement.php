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
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\Product\ProductCombinationGenerationEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementDeleteEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Loop\ProductSaleElementsDocument;
use Thelia\Core\Template\Loop\ProductSaleElementsImage;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeCombination;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\Map\AttributeCombinationTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsProductDocument;
use Thelia\Model\ProductSaleElementsProductDocumentQuery;
use Thelia\Model\ProductSaleElementsProductImage;
use Thelia\Model\ProductSaleElementsProductImageQuery;
use Thelia\Model\ProductSaleElementsQuery;

class ProductSaleElement extends BaseAction implements EventSubscriberInterface
{
    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

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
                ->add(AttributeCombinationTableMap::COL_PRODUCT_SALE_ELEMENTS_ID, null, Criteria::ISNULL)
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

            if (\count($combinationAttributes) > 0) {
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

            $defaultStatus = $event->getIsDefault();

            // If this PSE will become the default one, be sure to have *only one* default for this product
            if ($defaultStatus) {
                ProductSaleElementsQuery::create()
                    ->filterByProduct($event->getProduct())
                    ->filterByIsDefault(true)
                    ->filterById($event->getProductSaleElementId(), Criteria::NOT_EQUAL)
                    ->update(['IsDefault' => false], $con)
                ;
            } else {
                // We will not allow the default PSE to become non default if no other default PSE exists for this product.
                if ($salesElement->getIsDefault() && ProductSaleElementsQuery::create()
                        ->filterByProduct($event->getProduct())
                        ->filterByIsDefault(true)
                        ->filterById($salesElement->getId(), Criteria::NOT_EQUAL)
                        ->count() === 0) {
                    // Prevent setting the only default PSE to non-default
                    $defaultStatus = true;
                }
            }

            // Update sale element
            $salesElement
                ->setRef($event->getReference())
                ->setQuantity($event->getQuantity())
                ->setPromo($event->getOnsale())
                ->setNewness($event->getIsnew())
                ->setWeight($event->getWeight())
                ->setIsDefault($defaultStatus)
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
                // If we are deleting the last PSE of the product, don(t delete it, but insteaf
                // transform it into a PSE detached for any attribute combination, so that product
                // prices, weight, stock and attributes will not be lost.
                if ($product->countSaleElements($con) === 1) {
                    $pse
                        ->setIsDefault(true)
                        ->save($con);
                    // Delete the related attribute combination.
                    AttributeCombinationQuery::create()
                        ->filterByProductSaleElementsId($pse->getId())
                        ->delete($con);
                } else {
                    // Delete the PSE
                    $pse->delete($con);
                    // If we deleted the default PSE, make the last created one the default
                    if ($pse->getIsDefault()) {
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
     * @throws \Propel\Runtime\Exception\PropelException
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
     * @param ProductCloneEvent $event
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function clonePSE(ProductCloneEvent $event)
    {
        $clonedProduct = $event->getClonedProduct();

        // Get original product's PSEs
        $originalProductPSEs = ProductSaleElementsQuery::create()
            ->orderByIsDefault(Criteria::DESC)
            ->findByProductId($event->getOriginalProduct()->getId());

        /**
         * Handle PSEs
         *
         * @var int  $key
         * @var ProductSaleElements $originalProductPSE
         */
        foreach ($originalProductPSEs as $key => $originalProductPSE) {
            $currencyId = ProductPriceQuery::create()
                ->filterByProductSaleElementsId($originalProductPSE->getId())
                ->select('CURRENCY_ID')
                ->findOne();

            // The default PSE, created at the same time as the clone product, is overwritten
            $clonedProductPSEId = $this->createClonePSE($event, $originalProductPSE, $currencyId);

            $this->updateClonePSE($event, $clonedProductPSEId, $originalProductPSE, $key);

            // PSE associated images
            $originalProductPSEImages = ProductSaleElementsProductImageQuery::create()
                ->findByProductSaleElementsId($originalProductPSE->getId());

            if (null !== $originalProductPSEImages) {
                $this->clonePSEAssociatedFiles($clonedProduct->getId(), $clonedProductPSEId, $originalProductPSEImages, $type = 'image');
            }

            // PSE associated documents
            $originalProductPSEDocuments = ProductSaleElementsProductDocumentQuery::create()
                ->findByProductSaleElementsId($originalProductPSE->getId());

            if (null !== $originalProductPSEDocuments) {
                $this->clonePSEAssociatedFiles($clonedProduct->getId(), $clonedProductPSEId, $originalProductPSEDocuments, $type = 'document');
            }
        }
    }

    /**
     * @param ProductCloneEvent $event
     * @param ProductSaleElements $originalProductPSE
     * @param $currencyId
     * @return int
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function createClonePSE(ProductCloneEvent $event, ProductSaleElements $originalProductPSE, $currencyId)
    {
        $attributeCombinationList = AttributeCombinationQuery::create()
            ->filterByProductSaleElementsId($originalProductPSE->getId())
            ->select(['ATTRIBUTE_AV_ID'])
            ->find();

        $clonedProductCreatePSEEvent = new ProductSaleElementCreateEvent($event->getClonedProduct(), $attributeCombinationList, $currencyId);
        $this->eventDispatcher->dispatch($clonedProductCreatePSEEvent,TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT, );

        return $clonedProductCreatePSEEvent->getProductSaleElement()->getId();
    }

    public function updateClonePSE(ProductCloneEvent $event, $clonedProductPSEId, ProductSaleElements $originalProductPSE, $key)
    {
        $originalProductPSEPrice = ProductPriceQuery::create()
            ->findOneByProductSaleElementsId($originalProductPSE->getId());

        $clonedProductUpdatePSEEvent = new ProductSaleElementUpdateEvent($event->getClonedProduct(), $clonedProductPSEId);
        $clonedProductUpdatePSEEvent
            ->setReference($event->getClonedProduct()->getRef().'-'.($key + 1))
            ->setIsdefault($originalProductPSE->getIsDefault())
            ->setFromDefaultCurrency(0)

            ->setWeight($originalProductPSE->getWeight())
            ->setQuantity($originalProductPSE->getQuantity())
            ->setOnsale($originalProductPSE->getPromo())
            ->setIsnew($originalProductPSE->getNewness())
            ->setEanCode($originalProductPSE->getEanCode())
            ->setTaxRuleId($event->getOriginalProduct()->getTaxRuleId())

            ->setPrice($originalProductPSEPrice->getPrice())
            ->setSalePrice($originalProductPSEPrice->getPromoPrice())
            ->setCurrencyId($originalProductPSEPrice->getCurrencyId());

        $this->eventDispatcher->dispatch($clonedProductUpdatePSEEvent, TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);
    }

    /**
     * @param $clonedProductId
     * @param $clonedProductPSEId
     * @param $originalProductPSEFiles
     * @param $type
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function clonePSEAssociatedFiles($clonedProductId, $clonedProductPSEId, $originalProductPSEFiles, $type)
    {
        /** @var ProductSaleElementsDocument|ProductSaleElementsImage $originalProductPSEFile */
        foreach ($originalProductPSEFiles as $originalProductPSEFile) {
            $originalProductFilePositionQuery = [];
            $originalProductPSEFileId = null;

            // Get file's original position
            switch ($type) {
                case 'image':
                    $originalProductFilePositionQuery = ProductImageQuery::create();
                    $originalProductPSEFileId = $originalProductPSEFile->getProductImageId();
                    break;
                case 'document':
                    $originalProductFilePositionQuery = ProductDocumentQuery::create();
                    $originalProductPSEFileId = $originalProductPSEFile->getProductDocumentId();
                    break;
            }
            $originalProductFilePosition = $originalProductFilePositionQuery
                ->select(['POSITION'])
                ->findPk($originalProductPSEFileId);

            // Get cloned file ID to link to the cloned PSE
            switch ($type) {
                case 'image':
                    $clonedProductFileIdToLinkToPSEQuery = ProductImageQuery::create();
                    break;
                case 'document':
                    $clonedProductFileIdToLinkToPSEQuery = ProductDocumentQuery::create();
                    break;
            }

            $clonedProductFileIdToLinkToPSE = $clonedProductFileIdToLinkToPSEQuery
                ->filterByProductId($clonedProductId)
                ->filterByPosition($originalProductFilePosition)
                ->select(['ID'])
                ->findOne();

            // Save association
            switch ($type) {
                case 'image':
                    $assoc = new ProductSaleElementsProductImage();
                    $assoc->setProductImageId($clonedProductFileIdToLinkToPSE);
                    break;
                case 'document':
                    $assoc = new ProductSaleElementsProductDocument();
                    $assoc->setProductDocumentId($clonedProductFileIdToLinkToPSE);
                    break;
            }
            $assoc
                ->setProductSaleElementsId($clonedProductPSEId)
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
