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

use Thelia\Model\Base\SaleUnitConfigQuery as BaseSaleUnitConfigQuery;

class SaleUnitConfigQuery extends BaseSaleUnitConfigQuery
{
    /**
     * The store settings a sale unit sets for itself, by name.
     *
     * @return array<string, string>
     */
    public static function findValuesOfSaleUnit(int $saleUnitId): array
    {
        $values = [];

        foreach (self::create()->filterBySaleUnitId($saleUnitId)->find() as $config) {
            $values[$config->getName()] = (string) $config->getValue();
        }

        return $values;
    }
}
