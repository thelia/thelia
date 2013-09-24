<?php

namespace Thelia\Model;

use Thelia\Model\Base\FolderDocument as BaseFolderDocument;
use Propel\Runtime\Connection\ConnectionInterface;

class FolderDocument extends BaseFolderDocument
{
    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * Calculate next position relative to our parent
     */
    protected function addCriteriaToPositionQuery($query) {
        $query->filterByFolder($this->getFolder());
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        return true;
    }
}
