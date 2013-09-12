<?php

namespace Thelia\Model;

use Thelia\Model\Base\AttributeAv as BaseAttributeAv;
use Thelia\Core\Event\AttributeValueEvent;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\TheliaEvents;
use Propel\Runtime\ActiveQuery\Criteria;

class AttributeAv extends BaseAttributeAv {

    use \Thelia\Model\Tools\ModelEventDispatcherTrait;
    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * Get the position of the next inserted object
     */
    public function getNextPosition($parent = null) {

        $last = $this->createQuery()
            ->filterByAttributeId($this->getAttributeId())
            ->orderByPosition(Criteria::DESC)
            ->limit(1)
            ->findOne()
        ;

        return $last != null ? $last->getPosition() + 1 : 1;
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_CREATEATTRIBUTE_VALUE, new AttributeValueEvent($this));

        // Set the current position for the new object
        $this->setPosition($this->getNextPosition());

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATEATTRIBUTE_VALUE, new AttributeValueEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATEATTRIBUTE_VALUE, new AttributeValueEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATEATTRIBUTE_VALUE, new AttributeValueEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETEATTRIBUTE_VALUE, new AttributeValueEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETEATTRIBUTE_VALUE, new AttributeValueEvent($this));
    }
}