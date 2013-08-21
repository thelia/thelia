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
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Log\Tlog;

use Thelia\Model\Base\AttributeCombinationQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 *
 * Attribute Combination loop
 *
 * Class AttributeCombination
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class AttributeCombination extends BaseLoop
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('product_sale_element', null, true),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('attribute_availability', 'attribute_availability_reverse', 'attribute', 'attribute_reverse'))
                ),
                'attribute'
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
        $search = AttributeCombinationQuery::create();

        $productSaleElement = $this->getProduct_sale_element();

        $search->filterByProductSaleElementsId($productSaleElement, Criteria::EQUAL);

        $orders  = $this->getOrder();

        foreach($orders as $order) {
            switch ($order) {
                case "attribute_availability":
                    //$search->addAscendingOrderByColumn(\Thelia\Model\Map\AttributeI18nTableMap::TITLE);
                    break;
                case "attribute_availability_reverse":
                    //$search->addDescendingOrderByColumn(\Thelia\Model\Map\AttributeI18nTableMap::TITLE);
                    break;
                case "attribute":
                    //$search->orderByPosition(Criteria::ASC);
                    break;
                case "attribute_reverse":
                    //$search->orderByPosition(Criteria::DESC);
                    break;
            }
        }

        $attributeCombinations = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($attributeCombinations as $attributeCombination) {
            $loopResultRow = new LoopResultRow();

            $attribute = $attributeCombination->getAttribute();
            $attributeAvailability = $attributeCombination->getAttributeAv();

            $loopResultRow
                ->set("ATTRIBUTE_TITLE", $attribute->getTitle())
                ->set("ATTRIBUTE_CHAPO", $attribute->getChapo())
                ->set("ATTRIBUTE_DESCRIPTION", $attribute->getDescription())
                ->set("ATTRIBUTE_POSTSCRIPTUM", $attribute->getPostscriptum())
                ->set("ATTRIBUTE_AVAILABILITY_TITLE", $attributeAvailability->getTitle())
                ->set("ATTRIBUTE_AVAILABILITY_CHAPO", $attributeAvailability->getChapo())
                ->set("ATTRIBUTE_AVAILABILITY_DESCRIPTION", $attributeAvailability->getDescription())
                ->set("ATTRIBUTE_AVAILABILITY_POSTSCRIPTUM", $attributeAvailability->getPostscriptum());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}