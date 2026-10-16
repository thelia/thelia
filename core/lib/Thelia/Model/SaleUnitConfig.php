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

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\SaleUnitConfig as BaseSaleUnitConfig;

/**
 * A store setting a sale unit sets for itself, read by ConfigQuery::read() instead of
 * the national value while the configuration of that unit is in scope.
 */
class SaleUnitConfig extends BaseSaleUnitConfig
{
    public function postSave(?ConnectionInterface $con = null): void
    {
        ConfigQuery::forgetSaleUnitValues();

        parent::postSave($con);
    }

    public function postDelete(?ConnectionInterface $con = null): void
    {
        ConfigQuery::forgetSaleUnitValues();

        parent::postDelete($con);
    }
}
