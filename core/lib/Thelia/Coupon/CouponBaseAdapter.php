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
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\TranslatorInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Cart\CartTrait;
use Thelia\Model\Currency;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 * @todo implements
 *
 */
class CouponBaseAdapter implements CouponAdapterInterface
{
    use CartTrait {
        CartTrait::getCart as getCartFromTrait;
    }

    /** @var ContainerInterface Service Container */
    protected $container = null;

    /** @var Translator Service Translator  */
    protected $translator = null;

    /**
     * Constructor
     *
     * @param ContainerInterface $container  Service container
     */
    function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Return a Cart a CouponManager can process
     *
     * @return \Thelia\Model\Cart
     */
    public function getCart()
    {
        return $this->getCartFromTrait($this->getRequest());
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
        return $this->container->get('thelia.securityContext')->getCustomerUser();
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
    public function getCartTotalPrice()
    {
        // TODO: Implement getCartTotalPrice() method.
    }

    /**
     * Return the Checkout currency EUR|USD
     *
     * @return Currency
     */
    public function getCheckoutCurrency()
    {
        $this->getRequest()->getSession()->getCurrency();
    }


    /**
     * Return the number of Products in the Cart
     *
     * @return int
     */
    public function getNbArticlesInCart()
    {
        // TODO: Implement getNbArticlesInCart() method.
    }

    /**
     * Return all Coupon given during the Checkout
     *
     * @return array Array of CouponInterface
     */
    public function getCurrentCoupons()
    {
        $couponFactory = $this->container->get('thelia.coupon.factory');

        // @todo get from cart
        $couponCodes = array('XMAS', 'SPRINGBREAK');

        $coupons = array();
        foreach ($couponCodes as $couponCode) {
            $coupons[] = $couponFactory->buildCouponFromCode($couponCode);
        }

        return $coupons;
    }

    /**
     * Find one Coupon in the database from its code
     *
     * @param string $code Coupon code
     *
     * @return Coupon
     */
    public function findOneCouponByCode($code)
    {
        $couponQuery = CouponQuery::create();

        return $couponQuery->findOneByCode($code);
    }

    /**
     * Save a Coupon in the database
     *
     * @param CouponInterface $coupon Coupon
     *
     * @return $this
     */
    public function saveCoupon(CouponInterface $coupon)
    {
//        $couponModel = new Coupon();
//        $couponModel->setCode($coupon->getCode());
//        $couponModel->setType(get_class($coupon));
//        $couponModel->setTitle($coupon->getTitle());
//        $couponModel->setShortDescription($coupon->getShortDescription());
//        $couponModel->setDescription($coupon->getDescription());
//        $couponModel->setAmount($coupon->getDiscount());
//        $couponModel->setIsUsed(0);
//        $couponModel->setIsEnabled(1);
//        $couponModel->set
//        $couponModel->set
//        $couponModel->set
//        $couponModel->set
//        $couponModel->set
//        $couponModel->set
//        $couponModel->set
    }

    /**
     * Return plateform Container
     *
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Return platform TranslatorInterface
     *
     * @return TranslatorInterface
     */
    public function getTranslator()
    {
        return $this->container->get('thelia.translator');
    }


    /**
     * Return the main currency
     * THe one used to set prices in BackOffice
     *
     * @return string
     */
    public function getMainCurrency()
    {
        // TODO: Implement getMainCurrency() method.
    }

    /**
     * Return request
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->container->get('request');
    }
}
