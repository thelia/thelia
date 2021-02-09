<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Attribute\AttributeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Attribute as BaseAttribute;

class Attribute extends BaseAttribute
{
    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        // Set the current position for the new object
        $this->setPosition($this->getNextPosition());

        parent::preInsert($con);

        return true;
    }
}
