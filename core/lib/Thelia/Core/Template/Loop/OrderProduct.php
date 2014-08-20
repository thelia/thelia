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

use Thelia\Model\OrderProductQuery;

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
            Argument::createIntTypeArgument('order', null, true),
            Argument::createIntListTypeArgument('id')
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

        if (null !== $this->getId()) {
            $search->filterById($this->getId(), Criteria::IN);
        }

        $search->orderById(Criteria::ASC);

        return $search;

    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $orderProduct) {
            $loopResultRow = new LoopResultRow($orderProduct);

            $price = $orderProduct->getPrice();
            $taxedPrice = $price + $orderProduct->getVirtualColumn('TOTAL_TAX');
            $promoPrice = $orderProduct->getPromoPrice();
            $taxedPromoPrice = $promoPrice + $orderProduct->getVirtualColumn('TOTAL_PROMO_TAX');

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
