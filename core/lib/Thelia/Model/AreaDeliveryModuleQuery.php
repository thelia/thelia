<?php

namespace Thelia\Model;

use Thelia\Model\Base\AreaDeliveryModuleQuery as BaseAreaDeliveryModuleQuery;
use Thelia\Module\BaseModule;

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
    /**
     * Check if a delivery module is suitable for the given country.
     *
     * @param Country $country
     * @param Module $module
     * @return null|AreaDeliveryModule
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function findByCountryAndModule(Country $country, Module $module)
    {
        $response = null;

        $countryInAreaList = CountryAreaQuery::create()->filterByCountryId($country->getId())->find();

        foreach ($countryInAreaList as $countryInArea) {
            $response = $this->filterByAreaId($countryInArea->getAreaId())
                ->filterByModule($module)
                ->findOne();

            if ($response !== null) {
                break;
            }
        }

        return $response;
    }
}
// AreaDeliveryModuleQuery
