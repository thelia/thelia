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

use Thelia\Model\Base\FeatureAvQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 *
 * FeatureAvailable loop
 *
 *
 * Class FeatureAvailable
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class FeatureAvailable extends BaseLoop
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('feature'),
            Argument::createIntListTypeArgument('exclude'),
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
        $search = FeatureAvQuery::create();

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $feature = $this->getFeature();

        if(null !== $feature) {
            $search->filterByFeatureId($feature, Criteria::IN);
        }

        $orders  = $this->getOrder();

        foreach($orders as $order) {
            switch ($order) {
                case "alpha":
                    $search->addAscendingOrderByColumn(\Thelia\Model\Map\FeatureAvI18nTableMap::TITLE);
                    break;
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn(\Thelia\Model\Map\FeatureAvI18nTableMap::TITLE);
                    break;
                case "manual":
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case "manual_reverse":
                    $search->orderByPosition(Criteria::DESC);
                    break;
            }
        }

        /**
         * Criteria::INNER_JOIN in second parameter for joinWithI18n  exclude query without translation.
         *
         * @todo : verify here if we want results for row without translations.
         */

        $search->joinWithI18n(
            $this->request->getSession()->getLocale(),
            (ConfigQuery::read("default_lang_without_translation", 1)) ? Criteria::LEFT_JOIN : Criteria::INNER_JOIN
        );

        $features = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($features as $feature) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("ID", $feature->getId());
            $loopResultRow->set("TITLE",$feature->getTitle());
            $loopResultRow->set("CHAPO", $feature->getChapo());
            $loopResultRow->set("DESCRIPTION", $feature->getDescription());
            $loopResultRow->set("POSTSCRIPTUM", $feature->getPostscriptum());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}