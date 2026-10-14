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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\OrderShipmentQuery as BaseOrderShipmentQuery;

class OrderShipmentQuery extends BaseOrderShipmentQuery
{
    /**
     * The first parcel of an order is its oldest shipment that was not cancelled: the one
     * that stands for the order and carries the tracking number the order shows.
     */
    public function findFirstParcelOfOrder(int $orderId): ?OrderShipment
    {
        return $this
            ->filterByOrderId($orderId)
            ->filterByStatus(OrderShipment::STATUS_CANCELLED, Criteria::NOT_EQUAL)
            ->orderById()
            ->findOne();
    }
}
