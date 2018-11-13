<?php

namespace Thelia\Model;

use Thelia\Model\Base\AttributeAv as BaseAttributeAv;
use Thelia\Core\Event\Attribute\AttributeAvEvent;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\TheliaEvents;

class AttributeAv extends BaseAttributeAv
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * when dealing with position, be sure to work insite the current attribute.
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByAttributeId($this->getAttributeId());
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        // Set the current position for the new object
        $this->setPosition($this->getNextPosition());

        $this->dispatchEvent(TheliaEvents::BEFORE_CREATEATTRIBUTE_AV, new AttributeAvEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        parent::postInsert($con);

        $this->dispatchEvent(TheliaEvents::AFTER_CREATEATTRIBUTE_AV, new AttributeAvEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        parent::preUpdate($con);

        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATEATTRIBUTE_AV, new AttributeAvEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        parent::postUpdate($con);

        $this->dispatchEvent(TheliaEvents::AFTER_UPDATEATTRIBUTE_AV, new AttributeAvEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        parent::preDelete($con);

        $this->dispatchEvent(TheliaEvents::BEFORE_DELETEATTRIBUTE_AV, new AttributeAvEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        parent::postDelete($con);

        $this->dispatchEvent(TheliaEvents::AFTER_DELETEATTRIBUTE_AV, new AttributeAvEvent($this));
    }
}
