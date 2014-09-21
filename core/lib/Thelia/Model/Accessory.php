<?php

namespace Thelia\Model;

use Thelia\Model\Base\Accessory as BaseAccessory;
use Thelia\Core\Event\TheliaEvents;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\AccessoryEvent;

class Accessory extends BaseAccessory
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * Calculate next position relative to our product
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByProductId($this->getProductId());
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        $this->dispatchEvent(TheliaEvents::BEFORE_CREATEACCESSORY, new AccessoryEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATEACCESSORY, new AccessoryEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATEACCESSORY, new AccessoryEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATEACCESSORY, new AccessoryEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETEACCESSORY, new AccessoryEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETEACCESSORY, new AccessoryEvent($this));
    }
}
