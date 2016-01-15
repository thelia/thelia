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

        if (!isset($prices[$area]) || !isset($prices[$area]["slices"])) {
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
