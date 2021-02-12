<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Model;

use Thelia\Model\Base\AreaDeliveryModuleQuery as BaseAreaDeliveryModuleQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'area_delivery_module' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class AreaDeliveryModuleQuery extends BaseAreaDeliveryModuleQuery
{
    /**
     * Check if a delivery module is suitable for the given country.
     *
     * @return AreaDeliveryModule|null
     *
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
