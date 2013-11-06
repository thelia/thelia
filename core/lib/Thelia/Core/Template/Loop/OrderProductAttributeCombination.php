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
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\Base\OrderProductAttributeCombinationQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 *
 * OrderProductOrderProductAttributeCombination loop
 *
 * Class OrderProductAttributeCombination
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderProductAttributeCombination extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('order_product', null, true),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('alpha', 'alpha_reverse'))
                ),
                'alpha'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = OrderProductAttributeCombinationQuery::create();

        $orderProduct = $this->getOrder_product();

        $search->filterByOrderProductId($orderProduct, Criteria::EQUAL);

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case "alpha":
                    $search->orderByAttributeTitle(Criteria::ASC);
                    break;
                case "alpha_reverse":
                    $search->orderByAttributeTitle(Criteria::DESC);
                    break;
            }
        }

        return $search;
        
    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $orderAttributeCombination) {
            $loopResultRow = new LoopResultRow($orderAttributeCombination);

            $loopResultRow
                ->set("ATTRIBUTE_TITLE", $orderAttributeCombination->getAttributeTitle())
                ->set("ATTRIBUTE_CHAPO", $orderAttributeCombination->getAttributeChapo())
                ->set("ATTRIBUTE_DESCRIPTION", $orderAttributeCombination->getAttributeDescription())
                ->set("ATTRIBUTE_POSTSCRIPTUM", $orderAttributeCombination->getAttributePostscriptum())
                ->set("ATTRIBUTE_AVAILABILITY_TITLE", $orderAttributeCombination->getAttributeAvTitle())
                ->set("ATTRIBUTE_AVAILABILITY_CHAPO", $orderAttributeCombination->getAttributeAvChapo())
                ->set("ATTRIBUTE_AVAILABILITY_DESCRIPTION", $orderAttributeCombination->getAttributeAvDescription())
                ->set("ATTRIBUTE_AVAILABILITY_POSTSCRIPTUM", $orderAttributeCombination->getAttributeAvPostscriptum())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;

    }
}
