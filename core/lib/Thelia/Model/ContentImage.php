<?php

namespace Thelia\Model;

use Thelia\Model\Base\ContentImage as BaseContentImage;
use Propel\Runtime\Connection\ConnectionInterface;

class ContentImage extends BaseContentImage
{
    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * Calculate next position relative to our parent
     */
    protected function addCriteriaToPositionQuery($query) {
        $query->filterByContent($this->getContent());
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
        return $this->getContentId();
    }
}