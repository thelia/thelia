<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\OrderProduct as BaseOrderProduct;

class OrderProduct extends BaseOrderProduct
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    /**
     * @var int $cartIemId
     * @deprecated Since 2.1.3 because it is a typo, will be removed in 2.3
     */
    protected $cartIemId;

    protected $cartItemId;

    /**
     * @param mixed $cartItemId
     */
    public function setCartItemId($cartItemId)
    {
        $this->cartItemId = $cartItemId;

        /**
         * We have to ensure that if the setter is called, it sets this propriety too
         * for backward compatibility.
         *
         * example:
         *  A module creates a stub of this class
         *  It directly accesses to the $cartIemId propriety
         */
        $this->cartIemId = $cartItemId;

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
     * @param mixed $cartItemId
     * @deprecated Since 2.1.3 because it is a typo, will be removed in 2.3
     */
    public function setCartIemId($cartItemId)
    {
        trigger_error(
            'Thelia\Model\OrderProduct::setCartIemId is deprecated since version 2.1.3 and will be removed in 2.3. '.
            'You must use Thelia\Model\OrderProduct::setCartItemId instead',
            E_DEPRECATED
        );

        return $this->setCartItemId($cartItemId);
    }

    /**
     * @return mixed
     * @deprecated Since 2.1.3 because it is a typo, will be removed in 2.3
     */
    public function getCartIemId()
    {
        trigger_error(
            'Thelia\Model\OrderProduct::getCartIemId is deprecated since version 2.1.3 and will be removed in 2.3. '.
            'You must use Thelia\Model\OrderProduct::getCartItemId instead',
            E_DEPRECATED
        );

        return $this->getCartItemId();
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
