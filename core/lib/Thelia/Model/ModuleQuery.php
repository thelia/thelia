<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\ModuleQuery as BaseModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Skeleton subclass for performing query and update operations on the 'module' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class ModuleQuery extends BaseModuleQuery
{
    /**
     * List of active modules in ascending order
     *
     * @var \Propel\Runtime\Collection\ObjectCollection
     */
    protected static $activatedAsc;

    /**
     * List of active modules in descending order
     *
     * @var \Propel\Runtime\Collection\ObjectCollection
     */
    protected static $activatedDesc;

    /**
     * List of active modules in specified order
     *
     * @param string $order
     *
     * @deprecated Use ModuleQuery::getActivatedAsc() or ModuleQuery::getActivatedDesc() instead
     *
     * @throws \InvalidArgumentException
     *
     * @return \Propel\Runtime\Collection\ObjectCollection
     */
    public static function getActivated($order = Criteria::ASC)
    {
        if ($order === Criteria::ASC) {
            return self::getActivatedAsc();
        } elseif ($order === Criteria::DESC) {
            return self::getActivatedDesc();
        } else {
            throw new \InvalidArgumentException(__METHOD__ . ' must receive Criteria::ASC (' . Criteria::ASC . ') or Criteria::DESC (' . Criteria::DESC . ') as argument.');
        }
    }

    /**
     * Get list of active modules in ascending order
     *
     * @return \Propel\Runtime\Collection\ObjectCollection
     */
    public static function getActivatedAsc()
    {
        if (null === self::$activatedAsc) {
            self::$activatedAsc = self::create()
                ->filterByActivate(BaseModule::IS_ACTIVATED)
                ->orderByPosition(Criteria::ASC)
                ->find();
        }

        return self::$activatedAsc;
    }

    /**
     * Get list of active modules in descending order
     *
     * @return \Propel\Runtime\Collection\ObjectCollection
     */
    public static function getActivatedDesc()
    {
        if (null === self::$activatedDesc) {
            self::$activatedDesc = self::create()
                ->filterByActivate(BaseModule::IS_ACTIVATED)
                ->orderByPosition(Criteria::DESC)
                ->find();
        }

        return self::$activatedDesc;
    }

    /**
     * Cleans activated collection caches
     */
    public static function resetActivated()
    {
        self::$activatedAsc = null;
        self::$activatedDesc = null;
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
