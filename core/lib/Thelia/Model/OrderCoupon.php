<?php

namespace Thelia\Model;

use Thelia\Model\Base\OrderCoupon as BaseOrderCoupon;

class OrderCoupon extends BaseOrderCoupon
{
    /**
     * Return the countries for which free shipping is valid
     * @return array|mixed|\Propel\Runtime\Collection\ObjectCollection
     */
    public function getFreeShippingForCountries()
    {
        return OrderCouponCountryQuery::create()->filterByOrderCoupon($this)->find();
    }

    /**
     * Return the modules for which free shipping is valid
     *
     * @return array|mixed|\Propel\Runtime\Collection\ObjectCollection
     */
    public function getFreeShippingForModules()
    {
        return OrderCouponModuleQuery::create()->filterByOrderCoupon($this)->find();
    }
}
