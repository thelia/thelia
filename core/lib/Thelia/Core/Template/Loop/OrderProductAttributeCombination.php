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

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\Base\OrderProductAttributeCombinationQuery;
use Thelia\Model\Map\AttributeAvTableMap;
use Thelia\Model\Map\AttributeTableMap;
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
class OrderProductAttributeCombination extends BaseI18nLoop
{
    public $timestampable = true;

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

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
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

        $attributeCombinations = $this->search($search, $pagination);

        $loopResult = new LoopResult($attributeCombinations);

        foreach ($attributeCombinations as $attributeCombination) {
            $loopResultRow = new LoopResultRow($loopResult, $attributeCombination, $this->versionable, $this->timestampable, $this->countable);

            $loopResultRow
                ->set("LOCALE",$locale)
                ->set("ATTRIBUTE_TITLE", $attributeCombination->getAttributeTitle())
                ->set("ATTRIBUTE_CHAPO", $attributeCombination->getAttributeChapo())
                ->set("ATTRIBUTE_DESCRIPTION", $attributeCombination->getAttributeDescription())
                ->set("ATTRIBUTE_POSTSCRIPTUM", $attributeCombination->getAttributePostscriptum())
                ->set("ATTRIBUTE_AVAILABILITY_TITLE", $attributeCombination->getAttributeAvTitle())
                ->set("ATTRIBUTE_AVAILABILITY_CHAPO", $attributeCombination->getAttributeAvChapo())
                ->set("ATTRIBUTE_AVAILABILITY_DESCRIPTION", $attributeCombination->getAttributeAvDescription())
                ->set("ATTRIBUTE_AVAILABILITY_POSTSCRIPTUM", $attributeCombination->getAttributeAvPostscriptum())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
