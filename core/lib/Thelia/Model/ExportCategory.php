<?php

namespace Thelia\Model;

use Thelia\Model\Base\ExportCategory as BaseExportCategory;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Model\Tools\PositionManagementTrait;

class ExportCategory extends BaseExportCategory
{
    use PositionManagementTrait;
    use ModelEventDispatcherTrait;
}
