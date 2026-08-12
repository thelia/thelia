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
use Thelia\Model\State;

abstract class AbstractDeliveryModuleWithState extends BaseModule implements DeliveryModuleWithStateInterface
{
    use OrderPostageBuilderTrait;

    // This class is the base class for delivery modules
    // It may contains common methods in the future.

    public function handleVirtualProductDelivery(): bool
    {
        return false;
    }

    /**
     * Return the first area that matches the given  country for the given module.
     */
    public function getAreaForCountry(Country $country, ?State $state = null): ?Area
    {
        $area = null;

        if (null !== $areaDeliveryModule = AreaDeliveryModuleQuery::create()->findByCountryAndModule(
            $country,
            $this->getModuleModel(),
            $state,
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
