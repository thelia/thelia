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
    /**
     * Check if a delivery module is suitable for the given country.
     *
     * @param Country $country
     * @param Module $module
     * @param State|null $state
     *
     * @return null|AreaDeliveryModule
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function findByCountryAndModule(Country $country, Module $module, State $state = null)
    {
        $response = null;

        $countryInAreaList = CountryAreaQuery::findByCountryAndState($country, $state);

        /** @var CountryArea $countryInArea */
        foreach ($countryInAreaList as $countryInArea) {
            $response = self::create()->filterByAreaId($countryInArea->getAreaId())
                ->filterByModule($module)
                ->findOne()
            ;

            if ($response !== null) {
                break;
            }
        }

        return $response;
    }
}
// AreaDeliveryModuleQuery
