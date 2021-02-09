<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\ExportCategory as BaseExportCategory;
use Thelia\Model\Tools\PositionManagementTrait;

class ExportCategory extends BaseExportCategory
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
}
