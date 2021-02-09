<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\Import as BaseImport;
use Thelia\Model\Tools\PositionManagementTrait;

class Import extends BaseImport
{
    use PositionManagementTrait;

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function addCriteriaToPositionQuery($query)
    {
        $query->filterByImportCategoryId($this->getImportCategoryId());
    }
}
