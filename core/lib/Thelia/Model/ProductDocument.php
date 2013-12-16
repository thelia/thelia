<?php

namespace Thelia\Model;

use Thelia\Model\Base\ProductDocument as BaseProductDocument;
use Propel\Runtime\Connection\ConnectionInterface;

class ProductDocument extends BaseProductDocument
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;
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
     * Set Document parent id
     *
     * @param int $parentId parent id
     *
     * @return $this
     */
    public function setParentId($parentId)
    {
        $this->setProductId($parentId);

        return $this;
    }

    /**
     * Get Document parent id
     *
     * @return int parent id
     */
    public function getParentId()
    {
        return $this->getProductId();
    }

}
