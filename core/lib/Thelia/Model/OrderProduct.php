<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\OrderProduct as BaseOrderProduct;

class OrderProduct extends BaseOrderProduct
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    protected $cartIemId;

    /**
     * @param mixed $cartIemId
     */
    public function setCartIemId($cartIemId)
    {
        $this->cartIemId = $cartIemId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCartIemId()
    {
        return $this->cartIemId;
    }



    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::ORDER_PRODUCT_BEFORE_CREATE, (new OrderEvent($this->getOrder()))->setCartItemId($this->cartIemId));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::ORDER_PRODUCT_AFTER_CREATE, (new OrderEvent($this->getOrder()))->setCartItemId($this->cartIemId));
    }
}
