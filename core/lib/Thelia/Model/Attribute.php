<?php

namespace Thelia\Model;

use Thelia\Model\Base\Attribute as BaseAttribute;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Attribute\AttributeEvent;

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
