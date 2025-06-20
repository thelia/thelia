<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Thelia\Module;

use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Country;

abstract class AbstractDeliveryModule extends BaseModule implements DeliveryModuleInterface
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
     * Return the first area that matches the given  country for the given module.
     *
     * @return Area|null
     */
    public function getAreaForCountry(Country $country)
    {
        $area = null;

        if (null !== $areaDeliveryModule = AreaDeliveryModuleQuery::create()->findByCountryAndModule(
            $country,
            $this->getModuleModel()
        )) {
            $area = $areaDeliveryModule->getArea();
        }

        return $area;
    }

    public function getDeliveryMode()
    {
        return 'delivery';
    }
}
