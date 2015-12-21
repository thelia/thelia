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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\OrderProductTax as OrderProductTaxModel;

/**
 *
 * OrderProductTax loop
 *
 *
 * Class OrderProductTax
 * @package Thelia\Core\Template\Loop
 * @author Zzuutt
 *
 * {@inheritdoc}
 * @method int getOrderProduct()
 */
class OrderProductTax extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('order_product', null, true)
        );
    }

    public function buildModelCriteria()
    {
        $search = OrderProductTaxQuery::create();

        $orderProduct = $this->getOrderProduct();

        $search->filterByOrderProductId($orderProduct, Criteria::EQUAL);

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var OrderProductTaxModel $orderProductTax */
        foreach ($loopResult->getResultDataCollection() as $orderProductTax) {
            $loopResultRow = new LoopResultRow($orderProductTax);
            $loopResultRow->set("ID", $orderProductTax->getId())
                ->set("TITLE", $orderProductTax->getTitle())
                ->set("DESCRIPTION", $orderProductTax->getDescription())
                ->set("AMOUNT", $orderProductTax->getAmount())
                ->set("PROMO_AMOUNT", $orderProductTax->getPromoAmount())
            ;
            $this->addOutputFields($loopResultRow, $orderProductTax);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
