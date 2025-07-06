<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\AccessoryQuery;

/**
 * Accessory loop.
 *
 * Class Accessory
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]    getProduct()
 * @method string[] getOrder()
 *
 * @see http://doc.thelia.net/en/documentation/loop/accessory.html
 */
class Accessory extends Product
{
    protected $accessoryId;

    protected $accessoryPosition;

    protected function getArgDefinitions(): ArgumentCollection
    {
        $argumentCollection = parent::getArgDefinitions();

        $argumentCollection->addArgument(
            Argument::createIntListTypeArgument('product', null, true)
        );

        $argumentCollection->get('order')->default = 'accessory';

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
        $orderByAccessory = array_search('accessory', $order, true);
        $orderByAccessoryReverse = array_search('accessory_reverse', $order, true);
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

        $accessoryIdList = [0];
        $this->accessoryPosition = [];
        $this->accessoryId = [];

        foreach ($accessories as $accessory) {
            $accessoryProductId = $accessory->getAccessory();

            $accessoryIdList[] = $accessoryProductId;

            $this->accessoryPosition[$accessoryProductId] = $accessory->getPosition();
            $this->accessoryId[$accessoryProductId] = $accessory->getId();
        }

        $receivedIdList = $this->getId();

        /* if an Id list is receive, loop will only match accessories from this list */
        if ($receivedIdList === null) {
            $this->args->get('id')->setValue(implode(',', $accessoryIdList));
        } else {
            $this->args->get('id')->setValue(implode(',', array_intersect($receivedIdList, $accessoryIdList)));
        }

        return parent::buildModelCriteria();
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        $loopResult = parent::parseResults($loopResult);

        foreach ($loopResult as $loopResultRow) {
            $accessoryProductId = $loopResultRow->get('ID');

            $loopResultRow
                ->set('ID', $this->accessoryId[$accessoryProductId])
                ->set('POSITION', $this->accessoryPosition[$accessoryProductId])
                ->set('ACCESSORY_ID', $accessoryProductId)
            ;
        }

        return $loopResult;
    }
}
