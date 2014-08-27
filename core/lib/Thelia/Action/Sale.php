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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Sale\ProductSaleStatusUpdateEvent;
use Thelia\Core\Event\Sale\SaleActiveStatusCheckEvent;
use Thelia\Core\Event\Sale\SaleClearStatusEvent;
use Thelia\Core\Event\Sale\SaleCreateEvent;
use Thelia\Core\Event\Sale\SaleDeleteEvent;
use Thelia\Core\Event\Sale\SaleToggleActivityEvent;
use Thelia\Core\Event\Sale\SaleUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\ProductPriceQuery;
use Thelia\Model\Country as CountryModel;
use Thelia\Model\Map\SaleTableMap;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Sale as SaleModel;
use Thelia\Model\SaleOffsetCurrency;
use Thelia\Model\SaleOffsetCurrencyQuery;
use Thelia\Model\SaleProduct;
use Thelia\Model\SaleProductQuery;
use Thelia\Model\SaleQuery;
use Thelia\TaxEngine\Calculator;

/**
 * Class Sale
 *
 * @package Thelia\Action
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class Sale extends BaseAction implements EventSubscriberInterface
{

    /**
     * Update the promo status of the sale's selected products and combinations
     *
     * @param  ProductSaleStatusUpdateEvent $event
     * @throws \RuntimeException
     */
    public function updateProductsSaleStatus(ProductSaleStatusUpdateEvent $event)
    {
        $taxCalculator = new Calculator();

        $sale = $event->getSale();

        // Get all selected product sale elements for this sale
        if (null !== $saleProducts = SaleProductQuery::create()->filterBySale($sale)) {

            $saleOffsetByCurrency = $sale->getPriceOffsets();

            $offsetType = $sale->getPriceOffsetType();

            $con = Propel::getWriteConnection(SaleTableMap::DATABASE_NAME);

            $con->beginTransaction();

            try {

                /** @var SaleProduct $saleProduct */
                foreach ($saleProducts as $saleProduct) {

                    $taxCalculator->load(
                        $saleProduct->getProduct($con),
                        CountryModel::getShopLocation()
                    );

                    // If no attribute AV id is defined, consider ALL product combinations
                    if (is_null($saleProduct->getAttributeAvId())) {
                        ProductSaleElementsQuery::create()
                            ->filterByProductId($saleProduct->getProductId())
                            ->update([ 'Promo' => $sale->getActive()], $con)
                        ;

                        $pseList = ProductSaleElementsQuery::create()
                            ->filterByProductId($saleProduct->getProductId());

                        /** @var ProductSaleElements $pse */
                        foreach ($pseList as $pse) {

                            /** @var SaleOffsetCurrency $offsetByCurrency */
                            foreach ($saleOffsetByCurrency as $currencyId => $offset) {

                                $productPrice = ProductPriceQuery::create()
                                    ->filterByProductSaleElementsId($pse->getId())
                                    ->filterByCurrencyId($currencyId)
                                    ->findOne($con);

                                if (null !== $productPrice) {

                                    // Get the taxed price
                                    $priceWithTax = $taxCalculator->getTaxedPrice($productPrice->getPrice());

                                    // Remove the price offset to get the taxed promo price
                                    switch ($offsetType) {
                                        case SaleModel::OFFSET_TYPE_AMOUNT :
                                            $promoPrice = max(0, $priceWithTax - $offset);
                                            break;

                                        case SaleModel::OFFSET_TYPE_PERCENTAGE :
                                            $promoPrice = $priceWithTax * (1 - $offset / 100);
                                            break;

                                        default:
                                            $promoPrice = $priceWithTax;
                                    }

                                    // and then get the untaxed promo price.
                                    $promoPrice = $taxCalculator->getUntaxedPrice($promoPrice);

                                    $productPrice
                                        ->setPromoPrice($promoPrice)
                                        ->save($con)
                                    ;
                                }
                            }
                        }
                    } else {
                        // Consider only combinations which contains the selected AttributeAv ID
                        // TODO not yet coded.
                        throw new \RuntimeException("Not yet implemented !");
                    }
                }

                $con->commit();

            } catch (PropelException $e) {
                $con->rollback();
                throw $e;
            }
        }
    }

    /**
     * Create a new Sale
     *
     * @param SaleCreateEvent $event
     */
    public function create(SaleCreateEvent $event)
    {
        $sale = new SaleModel();

        $sale
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setSaleLabel($event->getSaleLabel())
            ->save()
        ;

        $event->setSale($sale);
    }

    /**
     * Process update sale
     *
     * @param  SaleUpdateEvent $event
     * @throws PropelException
     */
    public function update(SaleUpdateEvent $event)
    {
        if (null !== $sale = SaleQuery::create()->findPk($event->getSaleId())) {

            $sale->setDispatcher($event->getDispatcher());

            $con = Propel::getWriteConnection(SaleTableMap::DATABASE_NAME);

            $con->beginTransaction();

            try {

                // Disable all promo flag on sale's current selected products,
                // to get a correct selection, even if a product has been de-selected.
                $sale->setActive(false);

                $event->getDispatcher()->dispatch(
                    TheliaEvents::UPDATE_PRODUCT_SALE_STATUS,
                    new ProductSaleStatusUpdateEvent($sale)
                );

                $sale
                    ->setActive($event->getActive())
                    ->setStartDate($event->getStartDate())
                    ->setEndDate($event->getEndDate())
                    ->setPriceOffsetType($event->getPriceOffsetType())
                    ->setDisplayInitialPrice($event->getDisplayInitialPrice())
                    ->setLocale($event->getLocale())
                    ->setSaleLabel($event->getSaleLabel())
                    ->setTitle($event->getTitle())
                    ->setDescription($event->getDescription())
                    ->setChapo($event->getChapo())
                    ->setPostscriptum($event->getPostscriptum())
                    ->save($con)
                ;

                $event->setSale($sale);

                // Update price offsets
                SaleOffsetCurrencyQuery::create()->filterBySaleId($sale->getId())->delete($con);

                foreach ($event->getPriceOffsets() as $currencyId => $priceOffset) {
                    $saleOffset = new SaleOffsetCurrency();

                    $saleOffset
                        ->setCurrencyId($currencyId)
                        ->setSaleId($sale->getId())
                        ->setPriceOffsetValue($priceOffset)
                        ->save($con)
                    ;
                }

                // Update products
                SaleProductQuery::create()->filterBySaleId($sale->getId())->delete($con);

                foreach ($event->getProducts() as $productId => $attributeIdArray) {

                    if (empty($attributeIdArray)) {
                        $saleProduct = new SaleProduct();

                        $saleProduct
                            ->setSaleId($sale->getId())
                            ->setProductId($productId)
                            ->setAttributeAvId(null)
                            ->save($con)
                        ;
                    } else {
                        foreach ($attributeIdArray as $attributeId) {
                            $saleProduct = new SaleProduct();

                            $saleProduct
                                ->setSaleId($sale->getId())
                                ->setProductId($productId)
                                ->setAttributeAvId($attributeId)
                                ->save($con)
                            ;
                        }
                    }
                }

                // Update related products sale status
                $event->getDispatcher()->dispatch(
                    TheliaEvents::UPDATE_PRODUCT_SALE_STATUS,
                    new ProductSaleStatusUpdateEvent($sale)
                );

                $con->commit();

            } catch (PropelException $e) {
                $con->rollback();
                throw $e;
            }
        }
    }

    /**
     * Toggle Sale activity
     *
     * @param SaleToggleActivityEvent $event
     */
    public function toggleActivity(SaleToggleActivityEvent $event)
    {
        $sale = $event->getSale();

        $sale
            ->setDispatcher($event->getDispatcher())
            ->setActive(!$sale->getActive())
            ->save();

        // Update related products sale status
        $event->getDispatcher()->dispatch(
            TheliaEvents::UPDATE_PRODUCT_SALE_STATUS,
            new ProductSaleStatusUpdateEvent($sale)
        );

        $event->setSale($sale);
    }

    /**
     * Delete a sale
     *
     * @param SaleDeleteEvent $event
     */
    public function delete(SaleDeleteEvent $event)
    {
        if (null !== $sale = SaleQuery::create()->findPk($event->getSaleId())) {

            $sale->setDispatcher($event->getDispatcher())->delete();

            $event->setSale($sale);
        }

        // Update related products sale status, if required
        if ($sale->getActive()) {
            $sale->setActive(false);

            // Update related products sale status
            $event->getDispatcher()->dispatch(
                TheliaEvents::UPDATE_PRODUCT_SALE_STATUS,
                new ProductSaleStatusUpdateEvent($sale)
            );
        }
    }

    /**
     * Clear all sales
     *
     * @param  SaleClearStatusEvent                      $event
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function clearStatus(SaleClearStatusEvent $event)
    {
        $con = Propel::getWriteConnection(SaleTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            // Set the active status of all Sales to false
            SaleQuery::create()
                ->filterByActive(true)
                ->update([ 'Active' => false ], $con)
            ;

            // Reset all sale status on PSE
            ProductSaleElementsQuery::create()
                ->filterByPromo(true)
                ->update([ 'Promo' => false], $con)
            ;

            $con->commit();

        } catch (PropelException $e) {
            $con->rollback();
            throw $e;
        }
    }

    /**
     * This method check the activation and deactivation dates of sales, and perform
     * the required action depending on the current date.
     *
     * @param  SaleActiveStatusCheckEvent                $event
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function checkSaleActivation(SaleActiveStatusCheckEvent $event)
    {
        $con = Propel::getWriteConnection(SaleTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {

            $now = time();

            // Disable expired sales
            if (null !== $salesToDisable = SaleQuery::create()
                    ->filterByActive(true)
                    ->filterByEndDate($now, Criteria::GREATER_THAN)) {

                /** @var SaleModel $sale */
                foreach ($salesToDisable as $sale) {
                    $sale->setActive(false)->save();

                    // Update related products sale status
                    $event->getDispatcher()->dispatch(
                        TheliaEvents::UPDATE_PRODUCT_SALE_STATUS,
                        new ProductSaleStatusUpdateEvent($sale)
                    );
                }
            }

            // Enable sales that should be enabled.
            if (null !== $salesToDisable = SaleQuery::create()
                    ->filterByActive(false)
                    ->filterByStartDate($now, Criteria::GREATER_THAN)
                    ->filterByEndDate($now, Criteria::LESS_THAN))
                {
                /** @var SaleModel $sale */
                foreach ($salesToDisable as $sale) {

                    $sale->setActive(true)->save();

                    // Update related products sale status
                    $event->getDispatcher()->dispatch(
                        TheliaEvents::UPDATE_PRODUCT_SALE_STATUS,
                        new ProductSaleStatusUpdateEvent($sale)
                    );
                }
            }

            $con->commit();

        } catch (PropelException $e) {
            $con->rollback();
            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::SALE_CREATE     => array('create', 128),
            TheliaEvents::SALE_UPDATE     => array('update', 128),
            TheliaEvents::SALE_DELETE     => array('delete', 128),

            TheliaEvents::SALE_TOGGLE_ACTIVITY => array('toggleActivity', 128),

            TheliaEvents::SALE_CLEAR_SALE_STATUS => array('clearStatus', 128),

            TheliaEvents::UPDATE_PRODUCT_SALE_STATUS => array('updateProductsSaleStatus', 128),

            TheliaEvents::CHECK_SALE_ACTIVATION_EVENT => array('checkSaleActivation', 128),
        );
    }
}
