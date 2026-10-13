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
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Map\OrderShipmentItemTableMap;
use Thelia\Model\Map\OrderShipmentTableMap;
use Thelia\Model\OrderShipment as OrderShipmentModel;
use Thelia\Model\OrderShipmentQuery;

/**
 * The shipments of an order: the parcels it left in, each with its number and tracking number.
 *
 * @method int[]|null    getId()
 * @method int|null      getOrder()
 * @method string[]|null getStatus()
 */
class OrderShipment extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('order'),
            Argument::createEnumListTypeArgument('status', OrderShipmentModel::STATUSES),
        );
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $search = OrderShipmentQuery::create();

        $search->withColumn(
            \sprintf(
                '(SELECT COUNT(*) FROM %s WHERE %s = %s)',
                OrderShipmentItemTableMap::TABLE_NAME,
                OrderShipmentItemTableMap::COL_ORDER_SHIPMENT_ID,
                OrderShipmentTableMap::COL_ID,
            ),
            'ITEM_COUNT',
        );

        if (null !== $id = $this->getId()) {
            $search->filterById($id, Criteria::IN);
        }

        if (null !== $order = $this->getOrder()) {
            $search->filterByOrderId($order, Criteria::EQUAL);
        }

        if (null !== $status = $this->getStatus()) {
            $search->filterByStatus($status, Criteria::IN);
        }

        $search->orderById(Criteria::ASC);

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var OrderShipmentModel $shipment */
        foreach ($loopResult->getResultDataCollection() as $shipment) {
            $loopResultRow = new LoopResultRow($shipment);

            $loopResultRow
                ->set('ID', $shipment->getId())
                ->set('REF', $shipment->getRef())
                ->set('STATUS', $shipment->getStatus())
                ->set('ORDER_ID', $shipment->getOrderId())
                ->set('DELIVERY_REF', $shipment->getDeliveryRef())
                ->set('DELIVERY_MODULE_ID', $shipment->getDeliveryModuleId())
                ->set('DELIVERY_MODULE_TITLE', $shipment->getDeliveryModuleTitle())
                ->set('SHIPPED_AT', $shipment->getShippedAt())
                ->set('CREATED_AT', $shipment->getCreatedAt())
                ->set('ITEM_COUNT', (int) $shipment->getVirtualColumn('ITEM_COUNT'));
            $this->addOutputFields($loopResultRow, $shipment);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
