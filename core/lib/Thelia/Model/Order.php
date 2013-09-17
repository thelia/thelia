<?php

namespace Thelia\Model;

use Thelia\Model\Base\Order as BaseOrder;

class Order extends BaseOrder
{
    public $chosenDeliveryAddress = null;
    public $chosenInvoiceAddress = null;

    /**
     * calculate the total amount
     *
     * @TODO create body method
     *
     * @return int
     */
    public function getTotalAmount()
    {
        return 2;
    }
}
