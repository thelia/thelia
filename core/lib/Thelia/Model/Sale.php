<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Sale\SaleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Sale as BaseSale;
use Thelia\Model\Tools\ModelEventDispatcherTrait;

class Sale extends BaseSale
{
    use ModelEventDispatcherTrait;

    /**
     * The price offsets types, either amount or percentage
     */
    const OFFSET_TYPE_PERCENTAGE = 10;
    const OFFSET_TYPE_AMOUNT = 20;


    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_CREATESALE, new SaleEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATESALE, new SaleEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATESALE, new SaleEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATESALE, new SaleEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETESALE, new SaleEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETESALE, new SaleEvent($this));
    }
}