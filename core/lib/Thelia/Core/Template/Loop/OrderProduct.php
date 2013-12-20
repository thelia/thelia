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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\Base\OrderProductQuery;

/**
 *
 * OrderProduct loop
 *
 * Class OrderProduct
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderProduct extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('order', null, true)
        );
    }

    public function buildModelCriteria()
    {
        $search = OrderProductQuery::create();

        $search->joinOrderProductTax('opt', Criteria::LEFT_JOIN)
            ->withColumn('SUM(`opt`.AMOUNT)', 'TOTAL_TAX')
            ->withColumn('SUM(`opt`.PROMO_AMOUNT)', 'TOTAL_PROMO_TAX')
            ->groupById();

        $order = $this->getOrder();

        $search->filterByOrderId($order, Criteria::EQUAL);

        $search->orderById(Criteria::ASC);

        return $search;
        
    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $orderProduct) {
            $loopResultRow = new LoopResultRow($orderProduct);

            $price = $orderProduct->getPrice();
            $taxedPrice = $price + round($orderProduct->getVirtualColumn('TOTAL_TAX'), 2);
            $promoPrice = $orderProduct->getPromoPrice();
            $taxedPromoPrice = $promoPrice + round($orderProduct->getVirtualColumn('TOTAL_PROMO_TAX'), 2);

            $loopResultRow->set("ID", $orderProduct->getId())
                ->set("REF", $orderProduct->getProductRef())
                ->set("PRODUCT_SALE_ELEMENTS_REF", $orderProduct->getProductSaleElementsRef())
                ->set("WAS_NEW", $orderProduct->getWasNew() === 1 ? 1 : 0)
                ->set("WAS_IN_PROMO", $orderProduct->getWasInPromo() === 1 ? 1 : 0)
                ->set("WEIGHT", $orderProduct->getWeight())
                ->set("TITLE", $orderProduct->getTitle())
                ->set("CHAPO", $orderProduct->getChapo())
                ->set("DESCRIPTION", $orderProduct->getDescription())
                ->set("POSTSCRIPTUM", $orderProduct->getPostscriptum())
                ->set("QUANTITY", $orderProduct->getQuantity())
                ->set("PRICE", $price)
                ->set("PRICE_TAX", $taxedPrice - $price)
                ->set("TAXED_PRICE", $taxedPrice)
                ->set("PROMO_PRICE", $promoPrice)
                ->set("PROMO_PRICE_TAX", $taxedPromoPrice - $promoPrice)
                ->set("TAXED_PROMO_PRICE", $taxedPromoPrice)
                ->set("TAX_RULE_TITLE", $orderProduct->getTaxRuleTitle())
                ->set("TAX_RULE_DESCRIPTION", $orderProduct->getTaxRuledescription())
                ->set("PARENT", $orderProduct->getParent())
                ->set("EAN_CODE", $orderProduct->getEanCode())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;

    }
}
