<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template\Loop;

use Thelia\Core\Template\Loop\Product;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\LoopResult;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\AccessoryQuery;
use Thelia\Type;

/**
 *
 * Accessory loop
 *
 *
 * Class Accessory
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Accessory extends Product
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        $argumentCollection = parent::getArgDefinitions();

        $argumentCollection->addArgument(
            Argument::createIntTypeArgument('product', null, true)
        );

        $argumentCollection->get('order')->default = "accessory";

        $argumentCollection->get('order')->type->getKey(0)->addValue('accessory');
        $argumentCollection->get('order')->type->getKey(0)->addValue('accessory_reverse');

        return $argumentCollection;
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = AccessoryQuery::create();

        $product = $this->getProduct();

        $search->filterByProductId($product, Criteria::IN);

        $order = $this->getOrder();
        $orderByAccessory = array_search('accessory', $order);
        $orderByAccessoryReverse = array_search('accessory_reverse', $order);
        if ($orderByAccessory !== false) {
            $search->orderByPosition(Criteria::ASC);
            $order[$orderByAccessory] = 'given_id';
            $this->args->get('order')->setValue( implode(',', $order) );
        }
        if ($orderByAccessoryReverse !== false) {
            $search->orderByPosition(Criteria::DESC);
            $order[$orderByAccessoryReverse] = 'given_id';
            $this->args->get('order')->setValue( implode(',', $order) );
        }

        $accessories = $this->search($search);

        $accessoryIdList = array(0);
        $accessoryPosition = $accessoryId = array();

        foreach ($accessories as $accessory) {

            $accessoryProductId = $accessory->getAccessory();

            array_push($accessoryIdList, $accessoryProductId);

            $accessoryPosition[$accessoryProductId] = $accessory->getPosition();
            $accessoryId[$accessoryProductId] = $accessory->getId();
        }

        $receivedIdList = $this->getId();

        /* if an Id list is receive, loop will only match accessories from this list */
        if ($receivedIdList === null) {
            $this->args->get('id')->setValue( implode(',', $accessoryIdList) );
        } else {
            $this->args->get('id')->setValue( implode(',', array_intersect($receivedIdList, $accessoryIdList)) );
        }

        $loopResult = parent::exec($pagination);

        foreach ($loopResult as $loopResultRow) {

            $accessoryProductId = $loopResultRow->get('ID');

            $loopResultRow
                ->set("ID"      , $accessoryId[$accessoryProductId])
                ->set("POSITION", $accessoryPosition[$accessoryProductId])
                ;
        }

        return $loopResult;
    }
}
