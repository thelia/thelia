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

namespace Colissimo\Loop;

use Colissimo\Colissimo;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

/**
 *
 * Price loop
 *
 *
 * Class Price
 * @package Colissimo\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Price extends BaseLoop implements ArraySearchLoopInterface
{
    /* set countable to false since we need to preserve keys */
    protected $countable = false;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('area', null, true)
        );
    }

    public function buildArray()
    {
        $area = $this->getArea();

        $prices = Colissimo::getPrices();

        if(!isset($prices[$area]) || !isset($prices[$area]["slices"])) {
            return array();
        }

        $areaPrices = $prices[$area]["slices"];
        ksort($areaPrices);

        return $areaPrices;
    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $maxWeight => $price) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("MAX_WEIGHT", $maxWeight)
                ->set("PRICE", $price);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;

    }
}
