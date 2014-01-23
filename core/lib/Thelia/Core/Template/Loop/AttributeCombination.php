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

use Thelia\Model\Base\AttributeCombinationQuery;
use Thelia\Model\Map\AttributeAvTableMap;
use Thelia\Model\Map\AttributeTableMap;
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
class AttributeCombination extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('product_sale_elements', null, true),
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
        $search = AttributeCombinationQuery::create();

        /* manage attribute translations */
        $this->configureI18nProcessing(
            $search,
            array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'),
            AttributeTableMap::TABLE_NAME,
            'ATTRIBUTE_ID'
        );

        /* manage attributeAv translations */
        $this->configureI18nProcessing(
            $search,
            array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'),
            AttributeAvTableMap::TABLE_NAME,
            'ATTRIBUTE_AV_ID'
        );

        $productSaleElements = $this->getProduct_sale_elements();

        $search->filterByProductSaleElementsId($productSaleElements, Criteria::EQUAL);

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case "alpha":
                    $search->addAscendingOrderByColumn(AttributeTableMap::TABLE_NAME . '_i18n_TITLE');
                    break;
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn(AttributeTableMap::TABLE_NAME . '_i18n_TITLE');
                    break;
            }
        }

        return $search;

    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $attributeCombination) {
            $loopResultRow = new LoopResultRow($attributeCombination);

            $loopResultRow
                ->set("LOCALE",$this->locale)
                ->set("ATTRIBUTE_TITLE", $attributeCombination->getVirtualColumn(AttributeTableMap::TABLE_NAME . '_i18n_TITLE'))
                ->set("ATTRIBUTE_CHAPO", $attributeCombination->getVirtualColumn(AttributeTableMap::TABLE_NAME . '_i18n_CHAPO'))
                ->set("ATTRIBUTE_DESCRIPTION", $attributeCombination->getVirtualColumn(AttributeTableMap::TABLE_NAME . '_i18n_DESCRIPTION'))
                ->set("ATTRIBUTE_POSTSCRIPTUM", $attributeCombination->getVirtualColumn(AttributeTableMap::TABLE_NAME . '_i18n_POSTSCRIPTUM'))
                ->set("ATTRIBUTE_AVAILABILITY_TITLE", $attributeCombination->getVirtualColumn(AttributeAvTableMap::TABLE_NAME . '_i18n_TITLE'))
                ->set("ATTRIBUTE_AVAILABILITY_CHAPO", $attributeCombination->getVirtualColumn(AttributeAvTableMap::TABLE_NAME . '_i18n_CHAPO'))
                ->set("ATTRIBUTE_AVAILABILITY_DESCRIPTION", $attributeCombination->getVirtualColumn(AttributeAvTableMap::TABLE_NAME . '_i18n_DESCRIPTION'))
                ->set("ATTRIBUTE_AVAILABILITY_POSTSCRIPTUM", $attributeCombination->getVirtualColumn(AttributeAvTableMap::TABLE_NAME . '_i18n_POSTSCRIPTUM'));

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;

    }
}
