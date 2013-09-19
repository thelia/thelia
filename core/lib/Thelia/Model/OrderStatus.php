<?php

namespace Thelia\Model;

use Thelia\Model\Base\OrderStatus as BaseOrderStatus;

class OrderStatus extends BaseOrderStatus
{
    const CODE_NOT_PAID = "not_paid";
    const CODE_PAID = "paid";
    const CODE_PROCESSED = "processed";
    const CODE_SENT = "sent";
    const CODE_CANCELED = "canceled";
}
