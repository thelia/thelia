<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Module;

use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Country;
use Thelia\Model\State;

abstract class AbstractDeliveryModuleWithState extends BaseModule implements DeliveryModuleWithStateInterface
{
    // This class is the base class for delivery modules
    // It may contains common methods in the future.

    /**
     * @return bool
     */
    public function handleVirtualProductDelivery()
    {
        return false;
    }

    /**
     * Return the first area that matches the given  country for the given module
     * @param State $state
     * @return Area|null
     */
    public function getAreaForCountry(Country $country, State $state = null)
    {
        $area = null;

        if (null !== $areaDeliveryModule = AreaDeliveryModuleQuery::create()->findByCountryAndModule(
                $country,
                $this->getModuleModel(),
                $state
            )) {
            $area = $areaDeliveryModule->getArea();
        }

        return $area;
    }

    public function getDeliveryMode()
    {
        return "delivery";
    }
}
