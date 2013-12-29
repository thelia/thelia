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
use Thelia\Coupon\ConditionCollection;
use Thelia\Model\Base\Coupon as BaseCoupon;
use Thelia\Model\Exception\InvalidArgumentException;
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
     * @param array     $effects                    Ready to be serialized in JSON effect params
     * @param string    $type                       Coupon type
     * @param bool      $isRemovingPostage          Is removing Postage
     * @param string    $shortDescription           Coupon short description
     * @param string    $description                Coupon description
     * @param boolean   $isEnabled                  Enable/Disable
     * @param \DateTime $expirationDate             Coupon expiration date
     * @param boolean   $isAvailableOnSpecialOffers Is available on special offers
     * @param boolean   $isCumulative               Is cumulative
     * @param int       $maxUsage                   Coupon quantity
     * @param string    $defaultSerializedRule      Serialized default rule added if none found
     * @param string    $locale                     Coupon Language code ISO (ex: fr_FR)
     *
     * @throws \Exception
     */
    function createOrUpdate($code, $title, array $effects, $type, $isRemovingPostage, $shortDescription, $description, $isEnabled, $expirationDate, $isAvailableOnSpecialOffers, $isCumulative, $maxUsage, $defaultSerializedRule, $locale = null)
    {
        $this->setCode($code)
            ->setType($type)
            ->setEffects($effects)
            ->setIsRemovingPostage($isRemovingPostage)
            ->setIsEnabled($isEnabled)
            ->setExpirationDate($expirationDate)
            ->setIsAvailableOnSpecialOffers($isAvailableOnSpecialOffers)
            ->setIsCumulative($isCumulative)
            ->setMaxUsage($maxUsage);
        $this->setTitle($title)
            ->setShortDescription($shortDescription)
            ->setDescription($description);

        // If no rule given, set default rule
        if (null === $this->getSerializedConditions()) {
            $this->setSerializedConditions($defaultSerializedRule);
        }

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
     * Create or Update this coupon condition
     *
     * @param string $serializableConditions Serialized conditions ready to be saved
     * @param string $locale                 Coupon Language code ISO (ex: fr_FR)
     *
     * @throws \Exception
     */
    public function createOrUpdateConditions($serializableConditions, $locale)
    {
        $this->setSerializedConditions($serializableConditions);

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
     * Get the amount removed from the coupon to the cart
     *
     * @return float
     */
    public function getAmount()
    {
        $amount = $this->getEffects()['amount'];

        return floatval($amount);
    }

    /**
     * Get the Coupon effects
     *
     * @return array
     * @throws Exception\InvalidArgumentException
     */
    public function getEffects()
    {
        $effects = $this->unserializeEffects($this->getSerializedEffects());

        if (null === $effects['amount']) {
            throw new InvalidArgumentException('Missing key \'amount\' in Coupon effect coming from database');
        }

        return $effects;
    }

    /**
     * Get the Coupon effects
     *
     * @param array $effects Effect ready to be serialized
     *                       Needs at least the key 'amount'
     *                       with the amount removed from the cart
     *
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function setEffects(array $effects)
    {
        if (null === $effects['amount']) {
            throw new InvalidArgumentException('Missing key \'amount\' in Coupon effect ready to be serialized array');
        }

        $this->setSerializedEffects($this->serializeEffects($effects));

        return $this;
    }

    /**
     * Return unserialized effects
     *
     * @param string $serializedEffects Serialized effect string to unserialize
     *
     * @return array
     */
    public function unserializeEffects($serializedEffects)
    {
        $effects = json_decode($serializedEffects, true);

        return $effects;
    }

    /**
     * Return serialized effects
     *
     * @param array $unserializedEffects Unserialized array string to serialize
     *
     * @return string
     */
    public function serializeEffects(array $unserializedEffects)
    {
        $effects = json_encode($unserializedEffects);

        return $effects;
    }
}
