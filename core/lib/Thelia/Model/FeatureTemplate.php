<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\FeatureTemplate as BaseFeatureTemplate;

class FeatureTemplate extends BaseFeatureTemplate
{
    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * Calculate next position relative to our template
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByTemplateId($this->getTemplateId());
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
