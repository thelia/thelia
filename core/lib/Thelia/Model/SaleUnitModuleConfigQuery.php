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

namespace Thelia\Model;

use Thelia\Model\Base\SaleUnitModuleConfigQuery as BaseSaleUnitModuleConfigQuery;

class SaleUnitModuleConfigQuery extends BaseSaleUnitModuleConfigQuery
{
    /**
     * The values a sale unit gives the settings of a module, by name.
     *
     * @return array<string, string|null>
     */
    public static function findValuesOfModule(int $saleUnitId, int $moduleId): array
    {
        $values = [];

        foreach (self::create()->filterBySaleUnitId($saleUnitId)->filterByModuleId($moduleId)->find() as $config) {
            $values[$config->getName()] = $config->getValue();
        }

        return $values;
    }
}
