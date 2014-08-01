<?php

namespace Thelia\Model;

use Thelia\Model\Base\ImportCategory as BaseImportCategory;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Model\Tools\PositionManagementTrait;

class ImportCategory extends BaseImportCategory
{
    use PositionManagementTrait;
    use ModelEventDispatcherTrait;
}
