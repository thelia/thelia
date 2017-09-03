<?php

namespace Thelia\Model;

use Thelia\Model\Base\OrderStatus as BaseOrderStatus;

class OrderStatus extends BaseOrderStatus
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;
    use \Thelia\Model\Tools\PositionManagementTrait;

    const CODE_NOT_PAID = "not_paid";
    const CODE_PAID = "paid";
    const CODE_PROCESSING = "processing";
    const CODE_SENT = "sent";
    const CODE_CANCELED = "canceled";
    const CODE_REFUNDED = "refunded";
}
