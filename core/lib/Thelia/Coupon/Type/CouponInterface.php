<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Coupon\Type;

use Propel\Runtime\Collection\ObjectCollection;
use Thelia\Condition\ConditionCollection;
use Thelia\Coupon\FacadeInterface;

/**
 * Represents a Coupon ready to be processed in a Checkout process
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
interface CouponInterface
{
    /**
     * Get I18n name
     *
     * @return string
     */
    public function getName();

    /**
     * Get I18n tooltip
     *
     * @return string
     */
    public function getToolTip();

    /**
     * Get Coupon Manager service Id
     *
     * @return string
     */
    public function getServiceId();

    /**
     * Set Coupon
     *
     * @param FacadeInterface  $facade                     Provides necessary value from Thelia
     * @param string           $code                       Coupon code (ex: XMAS)
     * @param string           $title                      Coupon title (ex: Coupon for XMAS)
     * @param string           $shortDescription           Coupon short description
     * @param string           $description                Coupon description
     * @param array            $effects                    Coupon effects params
     * @param bool             $isCumulative               If Coupon is cumulative
     * @param bool             $isRemovingPostage          If Coupon is removing postage
     * @param bool             $isAvailableOnSpecialOffers If available on Product already
     *                                                     on special offer price
     * @param bool             $isEnabled                  False if Coupon is disabled by admin
     * @param int              $maxUsage                   How many usage left
     * @param \Datetime        $expirationDate             When the Code is expiring
     * @param ObjectCollection $freeShippingForCountries   list of countries which shipping is free. All if empty
     * @param ObjectCollection $freeShippingForModules     list of modules for which shipping is free. All if empty
     * @param bool             $perCustomerUsageCount      true if usage count is per customer only
     */
    public function set(
        FacadeInterface $facade,
        $code,
        $title,
        $shortDescription,
        $description,
        array $effects,
        $isCumulative,
        $isRemovingPostage,
        $isAvailableOnSpecialOffers,
        $isEnabled,
        $maxUsage,
        \DateTime $expirationDate,
        $freeShippingForCountries,
        $freeShippingForModules,
        $perCustomerUsageCount
    );

    /**
     * Return Coupon code (ex: XMAS)
     *
     * @return string
     */
    public function getCode();

    /**
     * Return Coupon title (ex: Coupon for XMAS)
     *
     * @return string
     */
    public function getTitle();

    /**
     * Return Coupon short description
     *
     * @return string
     */
    public function getShortDescription();

    /**
     * Return Coupon description
     *
     * @return string
     */
    public function getDescription();

    /**
     * If Coupon is cumulative or prevent any accumulation
     * If is cumulative you can sum Coupon effects
     * If not cancel all other Coupon and take the last given
     *
     * @return bool
     */
    public function isCumulative();

    /**
     * If Coupon is removing Checkout Postage
     *
     * @return bool
     */
    public function isRemovingPostage();

    /**
     * Return condition to validate the Coupon or not
     *
     * @return ConditionCollection A set of ConditionInterface
     */
    public function getConditions();

    /**
     * Replace the existing Conditions by those given in parameter
     * If one Condition is badly implemented, no Condition will be added
     *
     * @param ConditionCollection $conditions ConditionInterface to add
     *
     * @return $this
     * @throws \Thelia\Exception\InvalidConditionException
     */
    public function setConditions(ConditionCollection $conditions);

    /**
     * Return Coupon expiration date
     *
     * @return \DateTime
     */
    public function getExpirationDate();

    /**
     * Check if the Coupon can be used against a
     * product already with a special offer price
     *
     * @return boolean
     */
    public function isAvailableOnSpecialOffers();

    /**
     * Check if the Coupon can be used against a
     * product already with a special offer price
     *
     * @return boolean
     */
    public function getPerCustomerUsageCount();

    /**
     * Check if Coupon has been disabled by admin
     *
     * @return boolean
     */
    public function isEnabled();

    /**
     * Return how many time the Coupon can be used again
     * Ex : -1 unlimited
     *
     * @return int
     */
    public function getMaxUsage();

    /**
     * Check if the Coupon is already Expired
     *
     * @return bool
     */
    public function isExpired();

    /**
     * Return an amount thant will be subtracted to the cart total, or zero.
     *
     * This method could also perform something else than the calculating an amount to subtract from the cart. It may
     * add a product to the cart, for example. In this case, an amount of 0 will be returned.
     *
     * WARNING: this method could be called several times, so perform suitable checks before performing cart
     * manipulations, so that the coupon effect will not be applied several times.
     *
     * @return float Amount removed from the cart total
     */
    public function exec();

    /**
     * Check if the current Coupon is matching its conditions
     * Thelia variables are given by the FacadeInterface
     *
     * @return bool
     */
    public function isMatching();

    /**
     * Draw the input displayed in the BackOffice
     * allowing Admin to set its Coupon effect
     *
     * @return string HTML string
     */
    public function drawBackOfficeInputs();

    /**
     * @return ObjectCollection list of country IDs for which shipping is free. All if empty
     */
    public function getFreeShippingForCountries();

    /**
     * @return ObjectCollection list of module IDs for which shipping is free. All if empty
     */
    public function getFreeShippingForModules();

    /**
     * Create the effect array from the list of fields
     *
     * @param array $data the input form data (e.g. $form->getData())
     *
     * @return array a filedName => fieldValue array
     */
    public function getEffects($data);

    /**
     * Clear all the data the coupon may have stored, called after an order is completed.
     */
    public function clear();

    /**
     * @return bool true if the coupon is currently in use in the current order process, false otherwise
     */
    public function isInUse();
}
