<?php

namespace Thelia\Model;

use Thelia\Model\Base\ModuleHook as BaseModuleHook;

class ModuleHook extends BaseModuleHook
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementTrait;

    const MAX_POSITION = 1000;

    /**
     * Calculate next position relative to our default category
     */
    protected function addCriteriaToPositionQuery($query)
    {
        // Find products in the same category
        /*$produits = ModuleHookQuery::create()
            ->filterByEvent($this->getEvent())
            ->select('id')
            ->find();*/

        // Filtrer la requete sur ces produits
        //if ($produits != null) $query->filterById($produits, Criteria::IN);
        $query->filterByHookId($this->getHookId());
    }

}
