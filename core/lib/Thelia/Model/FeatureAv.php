<?php

namespace Thelia\Model;

use Thelia\Model\Base\FeatureAv as BaseFeatureAv;
use Thelia\Core\Event\TheliaEvents;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Feature\FeatureAvEvent;

class FeatureAv extends BaseFeatureAv
{
    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * when dealing with position, be sure to work insite the current feature.
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByFeatureId($this->getFeatureId());
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
