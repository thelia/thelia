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

use Thelia\Model\Base\OrderCoupon as BaseOrderCoupon;

class OrderCoupon extends BaseOrderCoupon
{
    /**
     * Return the countries for which free shipping is valid.
     */
    public function getFreeShippingForCountries(): mixed
    {
        return OrderCouponCountryQuery::create()->filterByOrderCoupon($this)->find();
    }

    /**
     * Return the modules for which free shipping is valid.
     */
    public function getFreeShippingForModules(): mixed
    {
        return OrderCouponModuleQuery::create()->filterByOrderCoupon($this)->find();
    }
}
