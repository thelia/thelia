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

namespace Thelia\Model;

use Propel\Runtime\Propel;
use Thelia\Constraint\Rule\CouponRuleInterface;
use Thelia\Coupon\CouponRuleCollection;
use Thelia\Model\Base\Coupon as BaseCoupon;
use Thelia\Model\Map\CouponTableMap;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Used to provide an effect (mostly a discount)
 * at the end of the Customer checkout tunnel
 * It will be usable for a Customer only if it matches the Coupon criteria (Rules)
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class Coupon extends BaseCoupon
{

    use \Thelia\Model\Tools\ModelEventDispatcherTrait;


    /**
     * Create or Update this Coupon
     *
     * @param string    $code                       Coupon Code
     * @param string    $title                      Coupon title
     * @param float     $amount                     Amount removed from the Total Checkout
     * @param string    $effect                     Coupon effect
     * @param bool      $isRemovingPostage          Is removing Postage
     * @param string    $shortDescription           Coupon short description
     * @param string    $description                Coupon description
     * @param boolean   $isEnabled                  Enable/Disable
     * @param \DateTime $expirationDate             Coupon expiration date
     * @param boolean   $isAvailableOnSpecialOffers Is available on special offers
     * @param boolean   $isCumulative               Is cumulative
     * @param int       $maxUsage                   Coupon quantity
     * @param string    $locale                     Coupon Language code ISO (ex: fr_FR)
     *
     * @throws \Exception
     */
    function createOrUpdate($code, $title, $amount, $effect, $isRemovingPostage, $shortDescription, $description, $isEnabled, $expirationDate, $isAvailableOnSpecialOffers, $isCumulative, $maxUsage, $locale = null)
    {
        $this->setCode($code)
            ->setTitle($title)
            ->setShortDescription($shortDescription)
            ->setDescription($description)
            ->setType($effect)
            ->setAmount($amount)
            ->setIsRemovingPostage($isRemovingPostage)
            ->setType($amount)
            ->setIsEnabled($isEnabled)
            ->setExpirationDate($expirationDate)
            ->setIsAvailableOnSpecialOffers($isAvailableOnSpecialOffers)
            ->setIsCumulative($isCumulative)
            ->setMaxUsage($maxUsage);

        // Set object language (i18n)
        if (!is_null($locale)) {
            $this->setLocale($locale);
        }

        $con = Propel::getWriteConnection(CouponTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $this->save($con);
            $con->commit();

        } catch(\Exception $e) {
            $con->rollback();
            throw $e;
        }
    }

    /**
     * Create or Update this coupon rule
     *
     * @param string $serializableRules Serialized rules ready to be saved
     * @param string $locale            Coupon Language code ISO (ex: fr_FR)
     *
     * @throws \Exception
     */
    function createOrUpdateRules($serializableRules, $locale)
    {
        $this->setSerializedRules($serializableRules);

        // Set object language (i18n)
        if (!is_null($locale)) {
            $this->setLocale($locale);
        }

        $con = Propel::getWriteConnection(CouponTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $this->save($con);
            $con->commit();

        } catch(\Exception $e) {
            $con->rollback();
            throw $e;
        }
    }



}
