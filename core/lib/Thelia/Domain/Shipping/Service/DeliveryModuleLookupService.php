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

namespace Thelia\Domain\Shipping\Service;

use Thelia\Api\State\Collection\DeliveryModuleCollection;

final class DeliveryModuleLookupService
{
    public function findDeliveryModeByModuleId(int $moduleId, DeliveryModuleCollection $collection): ?string
    {
        $allModules = $collection->getAll();

        foreach ($allModules as $module) {
            if (($module['id'] ?? null) === $moduleId) {
                return $module['deliveryMode'] ?? null;
            }
        }

        return null;
    }
}
