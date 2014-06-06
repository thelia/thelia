<?php

namespace Thelia\Model;

use Thelia\Model\Base\ModuleQuery as BaseModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Skeleton subclass for performing query and update operations on the 'module' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class ModuleQuery extends BaseModuleQuery
{
    protected static $activated = null;
    /**
     * @return array|mixed|\PropelObjectCollection
     */
    public static function getActivated()
    {
        if (null === self::$activated) {
            self::$activated = self::create()
                ->filterByActivate(BaseModule::IS_ACTIVATED)
                ->orderByPosition()
                ->find();
        }

        return self::$activated;
    }

    public static function resetActivated()
    {
        self::$activated = null;
    }

    /**
     * @param  int         $moduleType the module type : classic, payment or delivery. Use BaseModule constant here.
     * @param  int         $id         the module id
     * @return ModuleQuery
     */
    public function filterActivatedByTypeAndId($moduleType, $id)
    {
        return $this
            ->filterByType($moduleType)
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->filterById($id);
    }

} // ModuleQuery
