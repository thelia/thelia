<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Condition\ConditionFactory;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\OrderQuery;

/**
 *
 * OrderCoupon loop
 *
 *
 * Class OrderCoupon
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderCoupon extends BaseLoop implements PropelSearchLoopInterface
{
    /**
     * Define all args used in your loop
     *
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
        $search = OrderCouponQuery::create();

        $order = $this->getOrder();

        $search->filterByOrderId($order, Criteria::EQUAL);

        $search->orderById(Criteria::ASC);

        return $search;

    }

    public function parseResults(LoopResult $loopResult)
    {
        $conditionFactory = $this->container->get('thelia.condition.factory');

        /** @var OrderCoupon $orderCoupon */
        foreach ($loopResult->getResultDataCollection() as $orderCoupon) {
            $loopResultRow = new LoopResultRow($orderCoupon);
            $conditions = $conditionFactory->unserializeConditionCollection(
                $orderCoupon->getSerializedConditions()
            );

            $now = time();
            $datediff = $orderCoupon->getExpirationDate()->getTimestamp() - $now;
            $daysLeftBeforeExpiration = floor($datediff/(60*60*24));

            $cleanedConditions = array();

            /*foreach ($conditions->getConditions() as $condition) {
                $cleanedConditions[] = $condition->getToolTip();
            }*/
            $loopResultRow->set("ID", $orderCoupon->getId())
                ->set("CODE", $orderCoupon->getCode())
                ->set("TITLE", $orderCoupon->getTitle())
                ->set("SHORT_DESCRIPTION", $orderCoupon->getShortDescription())
                ->set("DESCRIPTION", $orderCoupon->getDescription())
                ->set("EXPIRATION_DATE", $orderCoupon->getExpirationDate( OrderQuery::create()->findPk($this->getOrder())->getLangId() ))
                ->set("IS_CUMULATIVE", $orderCoupon->getIsCumulative())
                ->set("IS_REMOVING_POSTAGE", $orderCoupon->getIsRemovingPostage())
                ->set("IS_AVAILABLE_ON_SPECIAL_OFFERS", $orderCoupon->getIsAvailableOnSpecialOffers())
                //->set("AMOUNT", $orderCoupon->getAmount())
                //->set("APPLICATION_CONDITIONS", $cleanedConditions)
                ->set("DAY_LEFT_BEFORE_EXPIRATION", $daysLeftBeforeExpiration)
            ;
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
