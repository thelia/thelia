<?php

namespace Thelia\Model;

use Thelia\Model\Base\AttributeTemplate as BaseAttributeTemplate;
use Propel\Runtime\Connection\ConnectionInterface;

class AttributeTemplate extends BaseAttributeTemplate
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

        $this->setPosition($this->getNextPosition());

        return true;
    }
}
