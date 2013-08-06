<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\CartItem as BaseCartItem;

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



}
