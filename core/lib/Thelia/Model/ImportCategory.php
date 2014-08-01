<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\ImportCategory as BaseImportCategory;
use Thelia\Model\Map\ImportCategoryTableMap;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Model\Tools\PositionManagementTrait;

class ImportCategory extends BaseImportCategory
{
    use PositionManagementTrait;
    use ModelEventDispatcherTrait;
}
