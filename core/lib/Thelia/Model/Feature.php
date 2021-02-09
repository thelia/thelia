<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Feature\FeatureEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Feature as BaseFeature;

class Feature extends BaseFeature
{
    use \Thelia\Model\Tools\PositionManagementTrait;

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
