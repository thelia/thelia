<?php

namespace Thelia\Model;

use Thelia\Model\Base\FeatureTemplate as BaseFeatureTemplate;
use Propel\Runtime\Connection\ConnectionInterface;

class FeatureTemplate extends BaseFeatureTemplate
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

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
        // Set the current position for the new object
        $this->setPosition($this->getNextPosition());

        return true;
    }
}
