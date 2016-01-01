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
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\AreaQuery;
use Thelia\Model\Area as AreaModel;
use Thelia\Model\CountryAreaQuery;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Class Area
 * @package Thelia\Core\Template\Loop
 * @author Manuel Raynaud <manu@raynaud.io>
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method int[] getCountry()
 * @method int getWithZone()
 * @method int getWithoutZone()
 * @method bool|string getUnassigned()
 * @method int[] getModuleId()
 * @method string[] getOrder()
 */
class Area extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('country'),
            Argument::createIntTypeArgument('with_zone'),
            Argument::createIntTypeArgument('without_zone'),
            Argument::createBooleanOrBothTypeArgument('unassigned'),
            Argument::createIntListTypeArgument('module_id'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType([
                        'id', 'id_reverse',
                        'alpha', 'name_reverse'
                    ])
                ),
                'alpha'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = AreaQuery::create();

        $id = $this->getId();

        if (count($id)) {
            $search->filterById($id, Criteria::IN);
        }

        $withZone = $this->getWithZone();

        if ($withZone) {
            $search->joinAreaDeliveryModule('with_zone')
                ->where('`with_zone`.delivery_module_id '.Criteria::EQUAL.' ?', $withZone, \PDO::PARAM_INT);
        }

        $withoutZone = $this->getWithoutZone();

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

        if (count($modules)) {
            $search
                ->useAreaDeliveryModuleQuery()
                ->filterByDeliveryModuleId($modules, Criteria::IN)
                ->endUse();
        }

        $countries = $this->getCountry();

        if (count($countries)) {
            $search
                ->useCountryAreaQuery()
                ->filterByCountryId($countries, Criteria::IN)
                ->endUse();
        }

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id-reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case "alpha":
                    $search->orderByName(Criteria::ASC);
                    break;
                case "alpha-reverse":
                    $search->orderByName(Criteria::DESC);
                    break;
            }
        }
        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var AreaModel $area */
        foreach ($loopResult->getResultDataCollection() as $area) {
            $loopResultRow = new LoopResultRow($area);

            $loopResultRow
                ->set('ID', $area->getId())
                ->set('NAME', $area->getName())
                ->set('POSTAGE', $area->getPostage())
            ;
            $this->addOutputFields($loopResultRow, $area);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
