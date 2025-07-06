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

namespace Thelia\Service\Model;

use Propel\Runtime\Collection\ObjectCollection;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

readonly class DeliveryModuleService
{
    public function getDeliveryModules(): array|ObjectCollection
    {
        $moduleQuery = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE);

        return $moduleQuery->find();
    }
}
