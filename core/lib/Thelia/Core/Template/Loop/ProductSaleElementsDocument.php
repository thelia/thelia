<?php

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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ProductSaleElementsProductDocumentQuery;

/**
 * Class ProductSaleElementsDocument.
 *
 * @author Benjamin Perche <benjamin@thelia.net>
 *
 * @method int[]    getId()
 * @method int[]    getProductSaleElementsId()
 * @method int[]    getProductDocumentId()
 * @method string[] getOrder()
 */
class ProductSaleElementsDocument extends BaseLoop implements PropelSearchLoopInterface
{
    /**
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\ProductSaleElementsProductDocument $productSaleElementDocument */
        foreach ($loopResult->getResultDataCollection() as $productSaleElementDocument) {
            $row = new LoopResultRow($productSaleElementDocument);

            $row
                ->set('ID', $productSaleElementDocument->getId())
                ->set('PRODUCT_SALE_ELEMENTS_ID', $productSaleElementDocument->getProductSaleElementsId())
                ->set('PRODUCT_DOCUMENT_ID', $productSaleElementDocument->getProductDocumentId())
            ;

            $this->addOutputFields($row, $productSaleElementDocument);
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
     *
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('product_sale_elements_id'),
            Argument::createIntListTypeArgument('product_document_id'),
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
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    public function buildModelCriteria()
    {
        $query = ProductSaleElementsProductDocumentQuery::create();

        if (null !== $id = $this->getId()) {
            $query->filterById($id);
        }

        if (null !== $pseId = $this->getProductSaleElementsId()) {
            $query->filterByProductSaleElementsId($pseId);
        }

        if (null !== $productDocumentId = $this->getProductDocumentId()) {
            $query->filterByProductDocumentId($productDocumentId);
        }

        foreach ($this->getOrder() as $order) {
            switch ($order) {
                case 'position':
                    $query
                        ->useProductDocumentQuery()
                            ->orderByPosition(Criteria::ASC)
                        ->endUse()
                    ;
                    break;
                case 'position-reverse':
                    $query
                        ->useProductDocumentQuery()
                            ->orderByPosition(Criteria::DESC)
                        ->endUse()
                    ;
                    break;
            }
        }

        return $query;
    }
}
