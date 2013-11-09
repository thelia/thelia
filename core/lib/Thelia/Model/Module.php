<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\Module as BaseModule;
use Thelia\Model\Tools\ModelEventDispatcherTrait;

class Module extends BaseModule
{
    use ModelEventDispatcherTrait;

    public function postSave(ConnectionInterface $con = null)
    {
        ModuleQuery::resetActivated();
    }

    /**
     * @return the module's base directory path, relative to THELIA_MODULE_DIR
     */
    public function getBaseDir() {
        return ucfirst($this->getCode());
    }

    /**
     * @return the module's config directory path, relative to THELIA_MODULE_DIR
     */
    public function getConfigPath() {
        return $this->getBaseDir() . DS . "Config";
    }

    /**
     * @return the module's i18N directory path, relative to THELIA_MODULE_DIR
     */
    public function getI18nPath() {
        return $this->getBaseDir() . DS . "I18n";
    }
}
