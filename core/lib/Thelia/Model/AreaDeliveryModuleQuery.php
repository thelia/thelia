<?php

namespace Thelia\Model;

use Thelia\Model\Base\AreaDeliveryModuleQuery as BaseAreaDeliveryModuleQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'area_delivery_module' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class AreaDeliveryModuleQuery extends BaseAreaDeliveryModuleQuery
{
    public function findByCountryAndModule(Country $country, Module $module)
    {
        $response = null;

        if (null !== $country->getAreaId()) {
            $response = $this->filterByAreaId($country->getAreaId())
                ->filterByModule($module)
                ->findOne();
        }

        return $response;
    }
} // AreaDeliveryModuleQuery
