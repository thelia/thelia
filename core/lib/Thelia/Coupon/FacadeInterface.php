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

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\Coupon;

/**
 * Allow to assist in getting relevant data on the current application state
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
interface FacadeInterface
{

    /**
     * Constructor
     *
     * @param ContainerInterface $container Service container
     */
    public function __construct(ContainerInterface $container);

    /**
     * Return a Cart a CouponManager can process
     *
     * @return \Thelia\Model\Cart
     */
    public function getCart();

    /**
     * Return an Address a CouponManager can process
     *
     * @return \Thelia\Model\Address
     */
    public function getDeliveryAddress();

    /**
     * Return an Customer a CouponManager can process
     *
     * @return \Thelia\Model\Customer
     */
    public function getCustomer();

    /**
     * Return Checkout total price
     *
     * @return float
     */
    public function getCheckoutTotalPrice();

    /**
     * Return Products total price
     * CartTotalPrice = Checkout total - discount - postage
     *
     * @return float
     */
    public function getCartTotalPrice();

    /**
     * Return the Checkout currency EUR|USD
     *
     * @return string
     */
    public function getCheckoutCurrency();

    /**
     * Return Checkout total postage (only) price
     *
     * @return float
     */
    public function getCheckoutPostagePrice();

    /**
     * Return the number of Products in the Cart
     *
     * @return int
     */
    public function getNbArticlesInCart();

    /**
     * Return all Coupon given during the Checkout
     *
     * @return array Array of CouponInterface
     */
    public function getCurrentCoupons();

    /**
     * Find one Coupon in the database from its code
     *
     * @param string $code Coupon code
     *
     * @return Coupon
     */
    public function findOneCouponByCode($code);

    /**
     * Return platform Container
     *
     * @return Container
     */
    public function getContainer();

    /**
     * Return platform TranslatorInterface
     *
     * @return TranslatorInterface
     */
    public function getTranslator();

    /**
     * Return the main currency
     * THe one used to set prices in BackOffice
     *
     * @return string
     */
    public function getMainCurrency();

    /**
     * Return request
     *
     * @return Request
     */
    public function getRequest();

    /**
     * Return Condition Evaluator
     *
     * @return ConditionEvaluator
     */
    public function getConditionEvaluator();

    /**
     * Return all available currencies
     *
     * @return array of Currency
     */
    public function getAvailableCurrencies();

    /**
     * Return the event dispatcher,
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcher
     */
    public function getDispatcher();

}
