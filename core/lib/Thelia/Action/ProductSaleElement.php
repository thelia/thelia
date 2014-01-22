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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductPrice;
use Thelia\Model\AttributeCombination;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementDeleteEvent;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Model\ProductPriceQuery;
use Propel\Runtime\Propel;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\Currency;
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
     * @throws Exception
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
                $salesElement = $event->getProduct()->createProductSaleElement($con, 0, 0, 0, $event->getCurrencyId(), true);
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
     * @param ProductSaleElementUpdateEvent $event
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
     * @param ProductSaleElementDeleteEvent $event
     */
    public function delete(ProductSaleElementDeleteEvent $event)
    {
        if (null !== $pse = ProductSaleElementsQuery::create()->findPk($event->getProductSaleElementId())) {

            $product = $pse->getProduct();

            $con = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);

            $con->beginTransaction();

            try {

                $pse->delete($con);

                if ($product->countSaleElements() <= 0) {
                    // If we just deleted the last PSE, create a default one
                    $product->createProductSaleElement($con, 0, 0, 0, $event->getCurrencyId(), true);
                } elseif ($pse->getIsDefault()) {

                    // If we deleted the default PSE, make the last created one the default
                    $pse = ProductSaleElementsQuery::create()
                        ->filterByProductId($product->getId())
                        ->orderByCreatedAt(Criteria::DESC)
                        ->findOne($con)
                    ;

                    $pse->setIsDefault(true)->save($con);
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
     * @param ProductCombinationGenerationEvent $event
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
     * @param ProductSaleElement  $salesElement          the product sale element
     * @param unknown             $combinationAttributes an array oif attributes av IDs
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

        );
    }
}
