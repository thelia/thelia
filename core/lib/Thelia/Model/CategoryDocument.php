<?php

namespace Thelia\Model;

use Thelia\Model\Base\CategoryDocument as BaseCategoryDocument;
use Propel\Runtime\Connection\ConnectionInterface;

class CategoryDocument extends BaseCategoryDocument
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;
    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * Calculate next position relative to our parent
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByCategory($this->getCategory());
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
        $this->setCategoryId($parentId);

        return $this;
    }

    /**
     * Get Document parent id
     *
     * @return int parent id
     */
    public function getParentId()
    {
        return $this->getCategoryId();
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        $this->reorderBeforeDelete(
            array(
                "category_id" => $this->getCategoryId(),
            )
        );

        return true;
    }
}
