<?php

namespace Thelia\Model;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Log\Tlog;
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


    /**
     *
     * if the container is provided, this method will found the module in the container. Reflection is used instead.
     * If it's possible use it with the container.
     *
     * return false if no delivery modules are found, an array of BaseModule otherwise.
     *
     * @param ContainerInterface $container optional
     * @return false|\Thelia\Module\BaseModule[]
     */
    public function retrieveVirtualProductDelivery(ContainerInterface $container = null)
    {
        $modules = $this
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE)
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->find()
        ;

        $result = [];

        /** @var \Thelia\Model\Module $module */
        foreach ($modules as $module) {
            try {
                if (null !== $container) {
                    $instance = $module->getDeliveryModuleInstance($container);
                } else {
                    $instance = $module->createInstance();
                }

                if (true === $instance->handleVirtualProductDelivery()) {
                    $result[] = $instance;
                }
            } catch (\Exception $ex) {
                Tlog::getInstance()->addError("Failed to instantiate module ".$module->getCode(), $ex);
            }
        }

        return empty($result) ? false : $result;
    }
}
// ModuleQuery
