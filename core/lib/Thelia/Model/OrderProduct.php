<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Order\OrderProductEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\OrderProduct as BaseOrderProduct;

class OrderProduct extends BaseOrderProduct
{


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
        parent::preInsert($con);

        if (
            null !== $con
            && method_exists($con, 'getEventDispatcher')
            && null !== $con->getEventDispatcher()
        ) {
            $con->getEventDispatcher()->dispatch(
                (new OrderProductEvent($this->getOrder(), null))
                    ->setCartItemId($this->cartItemId),
                TheliaEvents::ORDER_PRODUCT_BEFORE_CREATE

            );
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        parent::postInsert($con);

        if (
            null !== $con
            && method_exists($con, 'getEventDispatcher')
            && null !== $con->getEventDispatcher()
        ) {
            $con->getEventDispatcher()->dispatch(
                (new OrderProductEvent($this->getOrder(), $this->getId()))
                    ->setCartItemId($this->cartItemId),
                TheliaEvents::ORDER_PRODUCT_AFTER_CREATE

            );
        }
    }
}
