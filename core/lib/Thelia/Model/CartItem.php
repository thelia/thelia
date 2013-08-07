<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\CartItem as BaseCartItem;
use Thelia\Model\ConfigQuery;

class CartItem extends BaseCartItem
{
    protected $dispatcher;

    public function setDisptacher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function postInsert(ConnectionInterface $con = null)
    {
        if ($this->dispatcher) {
            $cartEvent = new CartEvent($this->getCart());

            $this->dispatcher->dispatch(TheliaEvents::CART_ADDITEM, $cartEvent);
        }
    }

    public function postUpdate(ConnectionInterface $con = null)
    {
        if ($this->dispatcher) {
            $cartEvent = new CartEvent($this->getCart());

            $this->dispatcher->dispatch(TheliaEvents::CART_MODIFYITEM, $cartEvent);
        }
    }


    /**
     * @param $value
     * @return $this
     */
    public function addQuantity($value)
    {
        $currentQuantity = $this->getQuantity();

        $newQuantity = $currentQuantity + $value;

        if($newQuantity <= 0)
        {
            $newQuantity = $currentQuantity;
        }

        if(ConfigQuery::read("verifyStock", 1) == 1)
        {
            $productSaleElements = $this->getProductSaleElements();

            if($productSaleElements->getQuantity() < $newQuantity) {
                $newQuantity = $currentQuantity;
            }
        }

        $this->setQuantity($currentQuantity);

        return $this;
    }

}
