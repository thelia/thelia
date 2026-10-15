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

namespace Thelia\Core\Event\Order;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\OrderShipment;

/**
 * Carries a shipment through the announcements of its life: opened, shipped, cancelled.
 */
class OrderShipmentEvent extends ActionEvent
{
    public function __construct(
        protected OrderShipment $orderShipment,
    ) {
    }

    public function getOrderShipment(): OrderShipment
    {
        return $this->orderShipment;
    }

    public function setOrderShipment(OrderShipment $orderShipment): static
    {
        $this->orderShipment = $orderShipment;

        return $this;
    }
}
