<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\Module as BaseModule;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Core\Event\TheliaEvents;

class Module extends BaseModule
{
    use ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementTrait;

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
     * @return the module's base directory path, relative to THELIA_MODULE_DIR
     */
    public function getAbsoluteBaseDir() {
        return THELIA_MODULE_DIR . $this->getBaseDir();
    }

    /**
     * @return the module's config directory path, relative to THELIA_MODULE_DIR
     */
    public function getConfigPath() {
        return $this->getBaseDir() . DS . "Config";
    }

    /**
     * @return the module's config absolute directory path
     */
    public function getAbsoluteConfigPath() {
        return THELIA_MODULE_DIR . $this->getConfigPath();
    }

    /**
     * @return the module's i18N directory path, relative to THELIA_MODULE_DIR
     */
    public function getI18nPath() {
        return $this->getBaseDir() . DS . "I18n";
    }

    /**
     * @return the module's i18N absolute directory path
     */
    public function getAbsoluteI18nPath() {
        return THELIA_MODULE_DIR . $this->getI18nPath();
    }

    /**
     * Return the absolute path to one of the module's template directories
     *
     * @param int $templateSubdirName the name of the, probably one of TemplateDefinition::xxx_SUBDIR constants
     */
    public function getAbsoluteTemplateDirectoryPath($templateSubdirName) {
        return sprintf("%s%stemplates%s%s", $this->getAbsoluteBaseDir(), DS, DS, $templateSubdirName);
    }

    /**
     * Calculate next position relative to module type
     */
    protected function addCriteriaToPositionQuery($query) {
        $query->filterByType($this->getType());
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        return true;
    }
}