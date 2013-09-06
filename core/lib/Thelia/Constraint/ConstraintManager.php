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

namespace Thelia\Constraint;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;
use Thelia\Constraint\Rule\CouponRuleInterface;
use Thelia\Constraint\Rule\SerializableRule;
use Thelia\Coupon\CouponAdapterInterface;
use Thelia\Coupon\CouponRuleCollection;


/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Manage how Constraint could interact
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ConstraintManager
{
    /** @var ContainerInterface Service Container */
    protected $container = null;

    /** @var  CouponAdapterInterface Provide necessary value from Thelia*/
    protected $adapter;

    /** @var array CouponRuleCollection to process*/
    protected $rules = null;

    /**
     * Constructor
     *
     * @param ContainerInterface $container Service container
     */
    function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->adapter = $container->get('thelia.adapter');
    }

    /**
     * Check if the current Coupon is matching its conditions (Rules)
     * Thelia variables are given by the CouponAdapterInterface
     *
     * @param CouponRuleCollection $collection A collection of rules
     *
     * @return bool
     */
    public function isMatching(CouponRuleCollection $collection)
    {
        $isMatching = true;

        /** @var CouponRuleInterface $rule */
        foreach ($collection->getRules() as $rule) {
            if (!$rule->isMatching($this->adapter)) {
                $isMatching = false;
            }
        }

        return $isMatching;
    }

    /**
     * Serialize a collection of rules
     *
     * @param CouponRuleCollection $collection A collection of rules
     *
     * @return string A ready to be stored Rule collection
     */
    public function serializeCouponRuleCollection(CouponRuleCollection $collection)
    {
        $serializableRules = array();
        $rules = $collection->getRules();
        if ($rules !== null) {
            /** @var $rule CouponRuleInterface */
            foreach ($rules as $rule) {
                $serializableRules[] = $rule->getSerializableRule();
            }
        }

        return base64_encode(json_encode($serializableRules));
    }

    /**
     * Unserialize a collection of rules
     *
     * @param string $serializedRules Serialized Rules
     *
     * @return CouponRuleCollection Rules ready to be processed
     */
    public function unserializeCouponRuleCollection($serializedRules)
    {
        $unserializedRules = json_decode(base64_decode($serializedRules));

        $collection = new CouponRuleCollection();

        if (!empty($serializedRules) && !empty($unserializedRules)) {
            /** @var SerializableRule $rule */
            foreach ($unserializedRules as $rule) {
                if ($this->container->has($rule->ruleServiceId)) {
                    /** @var CouponRuleInterface $couponRule */
                    $couponRule = $this->container->get($rule->ruleServiceId);
                    $couponRule->populateFromForm(
                        (array) $rule->operators,
                        (array) $rule->values
                    );
                    $collection->add(clone $couponRule);
                }
            }
        }

        return $collection;
    }
}