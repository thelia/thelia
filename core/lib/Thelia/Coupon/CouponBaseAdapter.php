<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Coupon;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponBaseAdapter implements CouponAdapterInterface
{
    /**
     * Return a Cart a CouponManager can process
     *
     * @return \Thelia\Model\Cart
     */
    public function getCart()
    {
        // TODO: Implement getCart() method.
    }

    /**
     * Return an Address a CouponManager can process
     *
     * @return \Thelia\Model\Address
     */
    public function getDeliveryAddress()
    {
        // TODO: Implement getDeliveryAddress() method.
    }

    /**
     * Return an Customer a CouponManager can process
     *
     * @return \Thelia\Model\Customer
     */
    public function getCustomer()
    {
        // TODO: Implement getCustomer() method.
    }

    /**
     * Return Checkout total price
     *
     * @return float
     */
    public function getCheckoutTotalPrice()
    {
        // TODO: Implement getCheckoutTotalPrice() method.
    }

    /**
     * Return Checkout total postage (only) price
     *
     * @return float
     */
    public function getCheckoutPostagePrice()
    {
        // TODO: Implement getCheckoutPostagePrice() method.
    }

    /**
     * Return Products total price
     *
     * @return float
     */
    public function getCheckoutTotalPriceWithoutDiscountAndPostagePrice()
    {
        // TODO: Implement getCheckoutTotalPriceWithoutDiscountAndPostagePrice() method.
    }

    /**
     * Return the number of Products in the Cart
     *
     * @return int
     */
    public function getNbArticlesInTheCart()
    {
        // TODO: Implement getNbArticlesInTheCart() method.
    }

    /**
     * Return all Coupon given during the Checkout
     *
     * @return array Array of CouponInterface
     */
    public function getCurrentCoupons()
    {
        $couponFactory = new CouponFactory();

        // @todo Get from Session
        $couponCodes = array('XMAS', 'SPRINGBREAK');

        $coupons = array();
        foreach ($couponCodes as $couponCode) {
            $coupons[] = $couponFactory->buildCouponFromCode($couponCode);
        }

        return $coupons;
    }


}