<?php

namespace Thelia\Model;

use Thelia\Model\Base\ModuleHook as BaseModuleHook;

class ModuleHook extends BaseModuleHook
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementTrait;

    const MAX_POSITION = 1000;
}
