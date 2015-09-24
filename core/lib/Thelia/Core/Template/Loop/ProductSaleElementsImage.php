<?php
/*************************************************************************************/
/* This file is part of the Thelia package.                                          */
/*                                                                                   */
/* Copyright (c) OpenStudio                                                          */
/* email : dev@thelia.net                                                            */
/* web : http://www.thelia.net                                                       */
/*                                                                                   */
/* For the full copyright and license information, please view the LICENSE.txt       */
/* file that was distributed with this source code.                                  */
/*************************************************************************************/

namespace Thelia\Core\Template\Loop;

use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ProductSaleElementsProductImageQuery;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Class ProductSaleElementsImage
 * @package Thelia\Core\Template\Loop
 * @author Benjamin Perche <benjamin@thelia.net>
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method int[] getProductSaleElementsId()
 * @method int{] getProductImageId()
 * @method string[] getOrder()
 */
class ProductSaleElementsImage extends BaseLoop implements PropelSearchLoopInterface
{
    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\ProductSaleElementsProductImage $productSaleElementImage */
        foreach ($loopResult->getResultDataCollection() as $productSaleElementImage) {
            $row = new LoopResultRow();

            $row
                ->set("ID", $productSaleElementImage->getId())
                ->set("PRODUCT_SALE_ELEMENTS_ID", $productSaleElementImage->getProductSaleElementsId())
                ->set("PRODUCT_IMAGE_ID", $productSaleElementImage->getProductImageId())
            ;

            $loopResult->addRow($row);
        }

        return $loopResult;
    }

    /**
     * Definition of loop arguments
     *
     * example :
     *
     * public function getArgDefinitions()
     * {
     *  return new ArgumentCollection(
     *
     *       Argument::createIntListTypeArgument('id'),
     *           new Argument(
     *           'ref',
     *           new TypeCollection(
     *               new Type\AlphaNumStringListType()
     *           )
     *       ),
     *       Argument::createIntListTypeArgument('category'),
     *       Argument::createBooleanTypeArgument('new'),
     *       ...
     *   );
     * }
     *
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument("id"),
            Argument::createIntListTypeArgument("product_sale_elements_id"),
            Argument::createIntListTypeArgument("product_image_id"),
            Argument::createEnumListTypeArgument(
                "order",
                [
                    "position",
                    "position-reverse"
                ],
                "position"
            )
        );
    }

    /**
     * this method returns a Propel ModelCriteria
     *
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    public function buildModelCriteria()
    {
        $query = ProductSaleElementsProductImageQuery::create();

        if (null !== $id = $this->getId()) {
            $query->filterById($id);
        }

        if (null !== $pseId = $this->getProductSaleElementsId()) {
            $query->filterByProductSaleElementsId($pseId);
        }

        if (null !== $productImageId = $this->getProductImageId()) {
            $query->filterByProductImageId($id);
        }

        foreach ($this->getOrder() as $order) {
            switch ($order) {
                case "position":
                    $query
                        ->useProductImageQuery()
                            ->orderByPosition(Criteria::ASC)
                        ->endUse()
                    ;
                    break;

                case "position-reverse":
                    $query
                        ->useProductImageQuery()
                            ->orderByPosition(Criteria::DESC)
                        ->endUse()
                    ;
                    break;
            }
        }

        return $query;
    }
}
