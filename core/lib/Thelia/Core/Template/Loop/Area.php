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
            Argument::createBooleanOrBothTypeArgument('unassigned'),
            Argument::createIntListTypeArgument('module_id')
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

        $modules = $this->getModuleId();

        if ($modules) {
            $search
                ->useAreaDeliveryModuleQuery()
                ->filterByDeliveryModuleId($modules, Criteria::IN)
                ->endUse();
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
