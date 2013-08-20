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

use Thelia\Model\Base\FeatureProductQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\FeatureAvQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 *
 * FeatureValue loop
 *
 *
 * Class FeatureValue
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class FeatureValue extends BaseLoop
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('feature', null, true),
            Argument::createIntTypeArgument('product', null, true),
            Argument::createIntListTypeArgument('feature_available'),
            Argument::createBooleanTypeArgument('exclude_feature_available', 0),
            Argument::createBooleanTypeArgument('exclude_default_values', 0),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('alpha', 'alpha_reverse', 'manual', 'manual_reverse'))
                ),
                'manual'
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
        $search = FeatureProductQuery::create();

        $feature = $this->getFeature();

        $search->filterByFeatureId($feature, Criteria::EQUAL);

        $product = $this->getProduct();

        $search->filterByProductId($product, Criteria::EQUAL);

        $featureAvailable = $this->getFeature_available();

        if (null !== $featureAvailable) {
            $search->filterByFeatureAvId($featureAvailable, Criteria::IN);
        }

        $excludeFeatureAvailable = $this->getExclude_feature_available();
        if($excludeFeatureAvailable == true) {
            $search->filterByFeatureAvId(null, Criteria::NULL);
        }

        $excludeDefaultValues = $this->getExclude_default_values();
        if($excludeDefaultValues == true) {
            $search->filterByByDefault(null, Criteria::NULL);
        }

        $orders  = $this->getOrder();

        foreach($orders as $order) {
            switch ($order) {
                case "alpha":
                    //$search->addAscendingOrderByColumn(\Thelia\Model\Map\FeatureI18nTableMap::TITLE);
                    break;
                case "alpha_reverse":
                    //$search->addDescendingOrderByColumn(\Thelia\Model\Map\FeatureI18nTableMap::TITLE);
                    break;
                case "manual":
                    //$search->orderByPosition(Criteria::ASC);
                    break;
                case "manual_reverse":
                    //$search->orderByPosition(Criteria::DESC);
                    break;
            }
        }

        $featureValues = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($featureValues as $featureValue) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("ID", $featureValue->getId());

            $loopResultRow->set("PERSONAL_VALUE", $featureValue->getByDefault());

            $loopResultRow->set("TITLE", $featureValue->getFeatureAv()->getTitle());
            $loopResultRow->set("CHAPO", $featureValue->getFeatureAv()->getChapo());
            $loopResultRow->set("DESCRIPTION", $featureValue->getFeatureAv()->getDescription());
            $loopResultRow->set("POSTSCRIPTUM", $featureValue->getFeatureAv()->getPostscriptum());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}