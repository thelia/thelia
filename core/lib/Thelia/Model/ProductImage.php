<?php

namespace Thelia\Model;

use Thelia\Model\Base\ProductImage as BaseProductImage;
use Propel\Runtime\Connection\ConnectionInterface;

class ProductImage extends BaseProductImage
{
    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * Calculate next position relative to our parent
     */
    protected function addCriteriaToPositionQuery($query) {
        $query->filterByProduct($this->getProduct());
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        return true;
    }

    /**
     * Get Image parent id
     *
     * @return int parent id
     */
    public function getParentId()
    {
        return $this->getProductId();
    }
}
