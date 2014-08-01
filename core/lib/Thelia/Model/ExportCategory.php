<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\CategoryQuery;
use Thelia\Model\Base\ExportCategory as BaseExportCategory;
use Thelia\Model\Map\ExportCategoryTableMap;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Model\Tools\PositionManagementTrait;

class ExportCategory extends BaseExportCategory
{
    use PositionManagementTrait;
    use ModelEventDispatcherTrait;
}
