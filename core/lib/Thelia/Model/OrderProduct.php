<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Order\OrderProductEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\OrderProduct as BaseOrderProduct;

class OrderProduct extends BaseOrderProduct
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    /** @var int */
    protected $cartItemId;

    /**
     * @param mixed $cartItemId
     * @return $this
     */
    public function setCartItemId($cartItemId)
    {
        $this->cartItemId = $cartItemId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCartItemId()
    {
        return $this->cartItemId;
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(
            TheliaEvents::ORDER_PRODUCT_BEFORE_CREATE,
            (new OrderProductEvent($this->getOrder(), null))
                ->setCartItemId($this->cartItemId)
        );

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(
            TheliaEvents::ORDER_PRODUCT_AFTER_CREATE,
            (new OrderProductEvent($this->getOrder(), $this->getId()))
                ->setCartItemId($this->cartItemId)
        );
    }
}
