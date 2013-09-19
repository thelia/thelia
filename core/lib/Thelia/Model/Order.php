<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Order as BaseOrder;

class Order extends BaseOrder
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    public $chosenDeliveryAddress = null;
    public $chosenInvoiceAddress = null;

    /**
     * {@inheritDoc}
     */
    /*public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::ORDER_SET_REFERENCE, new OrderEvent($this));
    }*/

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
