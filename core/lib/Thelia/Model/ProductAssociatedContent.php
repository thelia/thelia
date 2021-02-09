<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Product\ProductAssociatedContentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\ProductAssociatedContent as BaseProductAssociatedContent;

class ProductAssociatedContent extends BaseProductAssociatedContent
{
    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * Calculate next position relative to our product
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByProductId($this->getProductId());
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
