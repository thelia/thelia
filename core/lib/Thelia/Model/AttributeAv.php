<?php

namespace Thelia\Model;

use Thelia\Model\Base\AttributeAv as BaseAttributeAv;
use Thelia\Core\Event\Attribute\AttributeAvEvent;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\TheliaEvents;

class AttributeAv extends BaseAttributeAv
{
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

        return true;
    }
}
