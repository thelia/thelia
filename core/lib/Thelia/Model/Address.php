<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Address\AddressEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Address as BaseAddress;

class Address extends BaseAddress
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    /**
     * put the the current address as default one
     */
    public function makeItDefault()
    {
        AddressQuery::create()->filterByCustomerId($this->getCustomerId())
            ->update(array('IsDefault' => '0'));

        $this->setIsDefault(1);
        $this->save();
    }

    /**
     * Code to be run before inserting to database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_CREATEADDRESS, new AddressEvent($this));

        return true;
    }

    /**
     * Code to be run after inserting to database
     * @param ConnectionInterface $con
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATEADDRESS, new AddressEvent($this));
    }

    /**
     * Code to be run before updating the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATEADDRESS, new AddressEvent($this));

        return true;
    }

    /**
     * Code to be run after updating the object in database
     * @param ConnectionInterface $con
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATEADDRESS, new AddressEvent($this));
    }

    /**
     * Code to be run before deleting the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        if ($this->getIsDefault()) {
            return false;
        }

        $this->dispatchEvent(TheliaEvents::BEFORE_DELETEADDRESS, new AddressEvent($this));

        return true;
    }

    /**
     * Code to be run after deleting the object in database
     * @param ConnectionInterface $con
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETEADDRESS, new AddressEvent($this));
    }
}
