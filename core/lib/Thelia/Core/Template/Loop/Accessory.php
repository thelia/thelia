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
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\AccessoryQuery;

/**
 *
 * Accessory loop
 *
 *
 * Class Accessory
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int[] getProduct()
 * @method string[] getOrder()
 *
 * @link http://doc.thelia.net/en/documentation/loop/accessory.html
 */
class Accessory extends Product
{
    protected $accessoryId;
    protected $accessoryPosition;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        $argumentCollection = parent::getArgDefinitions();

        $argumentCollection->addArgument(
            Argument::createIntListTypeArgument('product', null, true)
        );

        $argumentCollection->get('order')->default = "accessory";

        $argumentCollection->get('order')->type->getKey(0)->addValue('accessory');
        $argumentCollection->get('order')->type->getKey(0)->addValue('accessory_reverse');

        return $argumentCollection;
    }

    public function buildModelCriteria()
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
            $this->args->get('order')->setValue(implode(',', $order));
        }
        if ($orderByAccessoryReverse !== false) {
            $search->orderByPosition(Criteria::DESC);
            $order[$orderByAccessoryReverse] = 'given_id';
            $this->args->get('order')->setValue(implode(',', $order));
        }

        $accessories = $this->search($search);

        $this->accessoryIdList = array(0);
        $this->accessoryPosition = $this->accessoryId = array();

        foreach ($accessories as $accessory) {
            $accessoryProductId = $accessory->getAccessory();

            array_push($this->accessoryIdList, $accessoryProductId);

            $this->accessoryPosition[$accessoryProductId] = $accessory->getPosition();
            $this->accessoryId[$accessoryProductId] = $accessory->getId();
        }

        $receivedIdList = $this->getId();

        /* if an Id list is receive, loop will only match accessories from this list */
        if ($receivedIdList === null) {
            $this->args->get('id')->setValue(implode(',', $this->accessoryIdList));
        } else {
            $this->args->get('id')->setValue(implode(',', array_intersect($receivedIdList, $this->accessoryIdList)));
        }

        return parent::buildModelCriteria();
    }

    public function parseResults(LoopResult $results)
    {
        $results = parent::parseResults($results);

        foreach ($results as $loopResultRow) {
            $accessoryProductId = $loopResultRow->get('ID');
            \Thelia\Log\Tlog::getInstance()->notice($this->accessoryId);
            $loopResultRow
                ->set("ID", $this->accessoryId[$accessoryProductId])
                ->set("POSITION", $this->accessoryPosition[$accessoryProductId])
                ->set("ACCESSORY_ID", $accessoryProductId)
                ;
        }

        return $results;
    }
}
