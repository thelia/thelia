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
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\AreaQuery;

/**
 * Class Area
 * @package Thelia\Core\Template\Loop
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Area extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     *
     * define all args used in your loop
     *
     *
     * example :
     *
     * public function getArgDefinitions()
     * {
     *  return new ArgumentCollection(
     *       Argument::createIntListTypeArgument('id'),
     *           new Argument(
     *           'ref',
     *           new TypeCollection(
     *               new Type\AlphaNumStringListType()
     *           )
     *       ),
     *       Argument::createIntListTypeArgument('category'),
     *       Argument::createBooleanTypeArgument('new'),
     *       Argument::createBooleanTypeArgument('promo'),
     *       Argument::createFloatTypeArgument('min_price'),
     *       Argument::createFloatTypeArgument('max_price'),
     *       Argument::createIntTypeArgument('min_stock'),
     *       Argument::createFloatTypeArgument('min_weight'),
     *       Argument::createFloatTypeArgument('max_weight'),
     *       Argument::createBooleanTypeArgument('current'),
     *
     *   );
     * }
     *
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('with_zone'),
            Argument::createIntTypeArgument('without_zone'),
            Argument::createBooleanOrBothTypeArgument('unassigned')
        );
    }

    public function buildModelCriteria()
    {
        $id = $this->getId();

        $search = AreaQuery::create();

        if ($id) {
            $search->filterById($id, Criteria::IN);
        }

        $withZone = $this->getWith_zone();

        if ($withZone) {
            $search->joinAreaDeliveryModule('with_zone')
                ->where('`with_zone`.delivery_module_id '.Criteria::EQUAL.' ?', $withZone, \PDO::PARAM_INT);
        }

        $withoutZone = $this->getWithout_zone();

        if ($withoutZone) {
            $search->joinAreaDeliveryModule('without_zone', Criteria::LEFT_JOIN)
                ->addJoinCondition('without_zone', 'delivery_module_id '.Criteria::EQUAL.' ?', $withoutZone, null, \PDO::PARAM_INT)
                ->where('`without_zone`.delivery_module_id '.Criteria::ISNULL);
        }

        $notAssigned = $this->getUnassigned();

        if ($notAssigned) {
            $search
                ->joinAreaDeliveryModule('unassigned', Criteria::LEFT_JOIN)
                ->where('`unassigned`.delivery_module_id ' . Criteria::ISNULL);
        }

        return $search;

    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $area) {
            $loopResultRow = new LoopResultRow($area);

            $loopResultRow
                ->set('ID', $area->getId())
                ->set('NAME', $area->getName())
                ->set('POSTAGE', $area->getPostage())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;

    }

}
