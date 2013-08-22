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

use Thelia\Coupon\Type\CouponInterface;
use Thelia\Coupon\Type\RemoveXAmount;
use Thelia\Model\Base\CouponQuery;
use Thelia\Model\Coupon;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Generate a CouponInterface
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponFactory
{
    /**
     * Build a CouponInterface from its database data
     *
     * @param string $couponCode Coupon code ex: XMAS
     *
     * @return CouponInterface ready to be processed
     */
    public function buildCouponFromCode($couponCode)
    {

        $couponQuery = CouponQuery::create();
        $couponModel = $couponQuery->findByCode($couponCode);

        return $this->buildCouponInterfacFromModel($couponModel);
    }

    /**
     * Build a CouponInterface from its Model data contained in the DataBase
     *
     * @param Coupon $model Database data
     *
     * @return CouponInterface ready to use CouponInterface object instance
     */
    protected function buildCouponInterfacFromModel(Coupon $model)
    {
        $isCumulative = ($model->getIsCumulative() == 1 ? true : false);
        $isRemovingPostage = ($model->getIsRemovingPostage() == 1 ? true : false);
        $couponClass = $model->getType();

        /** @var CouponInterface $coupon*/
        $coupon = new $$couponClass(
            $model->getCode(),
            $model->getTitle(),
            $model->getShortDescription(),
            $model->getDescription(),
            $model->getAmount(),
            $isCumulative,
            $isRemovingPostage
        );

        $normalizer = new GetSetMethodNormalizer();
        $encoder = new JsonEncoder();

        $serializer = new Serializer(array($normalizer), array($encoder));

        $o = new \ArrayObject();
        $unserializedRuleTypes = $o->unserialize(
            $model->getSerializedRulesType()
        );
        $unserializedRuleContents = $o->unserialize(
            $model->getSerializedRulesContent()
        );

        $rules = array();
        foreach ($unserializedRuleTypes as $key => $unserializedRuleType) {
            $rules[] = $serializer->deserialize(
                $unserializedRuleContents[$key],
                $unserializedRuleType,
                'json'
            );
        }

        $coupon->setRules($rules);

        return $coupon;
    }
}