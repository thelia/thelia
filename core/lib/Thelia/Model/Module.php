<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\Module as BaseModule;

class Module extends BaseModule {

    public function postSave(ConnectionInterface $con = null)
    {
        ModuleQuery::resetActivated();
    }
}
