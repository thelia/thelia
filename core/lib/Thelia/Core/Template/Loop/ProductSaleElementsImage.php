<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Thelia\Core\Template\Loop;

use Thelia\Model\ProductSaleElementsProductImage;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ProductSaleElementsProductImageQuery;

/**
 * Class ProductSaleElementsImage.
 *
 * @author Benjamin Perche <benjamin@thelia.net>
 *
 * @method int[]    getId()
 * @method int[]    getProductSaleElementsId()
 * @method int[]    getProductImageId()
 * @method string[] getOrder()
 */
class ProductSaleElementsImage extends BaseLoop implements PropelSearchLoopInterface
{
    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var ProductSaleElementsProductImage $productSaleElementImage */
        foreach ($loopResult->getResultDataCollection() as $productSaleElementImage) {
            $row = new LoopResultRow($productSaleElementImage);

            $row
                ->set('ID', $productSaleElementImage->getId())
                ->set('PRODUCT_SALE_ELEMENTS_ID', $productSaleElementImage->getProductSaleElementsId())
                ->set('PRODUCT_IMAGE_ID', $productSaleElementImage->getProductImageId())
            ;

            $this->addOutputFields($row, $productSaleElementImage);
            $loopResult->addRow($row);
        }

        return $loopResult;
    }

    /**
     * Definition of loop arguments.
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
     */
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('product_sale_elements_id'),
            Argument::createIntListTypeArgument('product_image_id'),
            Argument::createEnumListTypeArgument(
                'order',
                [
                    'position',
                    'position-reverse',
                ],
                'position'
            )
        );
    }

    /**
     * this method returns a Propel ModelCriteria.
     *
     * @return ModelCriteria
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
            $query->filterByProductImageId($productImageId);
        }

        foreach ($this->getOrder() as $order) {
            switch ($order) {
                case 'position':
                    $query
                        ->useProductImageQuery()
                            ->orderByPosition(Criteria::ASC)
                        ->endUse()
                    ;
                    break;
                case 'position-reverse':
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
