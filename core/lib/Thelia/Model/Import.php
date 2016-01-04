<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\Import as BaseImport;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Model\Tools\PositionManagementTrait;

class Import extends BaseImport
{
    use PositionManagementTrait;
    use ModelEventDispatcherTrait;

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function addCriteriaToPositionQuery($query)
    {
        $query->filterByImportCategoryId($this->getImportCategoryId());
    }
}
