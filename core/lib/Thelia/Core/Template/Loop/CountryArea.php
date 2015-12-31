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
use Thelia\Model\CountryAreaQuery;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 *
 * Country Area loop
 *
 *
 * Class CountryArea
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method int[] getArea()
 * @method int[] getCountry()
 * @method int[] getState()
 * @method string[] getOrder()
 */
class CountryArea extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('area'),
            Argument::createIntListTypeArgument('country'),
            Argument::createIntListTypeArgument('state'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        [
                            'id', 'id_reverse',
                            'area', 'area_reverse',
                            'country', 'country_reverse',
                            'state', 'state_reverse',
                        ]
                    )
                ),
                'id'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = CountryAreaQuery::create();

        $id = $this->getId();
        if (count($id)) {
            $search->filterById($id, Criteria::IN);
        }

        $areas = $this->getArea();
        if (count($areas)) {
            $search->filterByAreaId($areas, Criteria::IN);
        }

        $countries = $this->getCountry();
        if (count($countries)) {
            $search->filterByCountryId($countries, Criteria::IN);
        }

        $states = $this->getState();
        if (count($states)) {
            $search->filterByStateId($states, Criteria::IN);
        }

        $orders = $this->getOrder();
        foreach ($orders as $order) {
            switch ($order) {
                case "id":
                    $search->orderById(Criteria::ASC);
                    break;
                case "id_reverse":
                    $search->orderById(Criteria::DESC);
                    break;
                case "area":
                    $search->orderByAreaId(Criteria::ASC);
                    break;
                case "area_reverse":
                    $search->orderByAreaId(Criteria::DESC);
                    break;
                case "country":
                    $search->orderByCountryId(Criteria::ASC);
                    break;
                case "country_reverse":
                    $search->orderByCountryId(Criteria::DESC);
                    break;
                case "state":
                    $search->orderByStateId(Criteria::ASC);
                    break;
                case "state_reverse":
                    $search->orderByStateId(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\CountryArea $countryArea */
        foreach ($loopResult->getResultDataCollection() as $countryArea) {
            $loopResultRow = new LoopResultRow($countryArea);
            $loopResultRow
                ->set("ID", $countryArea->getId())
                ->set("AREA_ID", $countryArea->getAreaId())
                ->set("COUNTRY_ID", $countryArea->getCountryId())
                ->set("STATE_ID", $countryArea->getStateId())
            ;

            $this->addOutputFields($loopResultRow, $countryArea);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
