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
use Thelia\Constraint\Rule\AvailableForTotalAmountManager;
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
class ConstraintFactory
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
                    $couponRule = $this->build(
                        $rule->ruleServiceId,
                        (array) $rule->operators,
                        (array) $rule->values
                    );
                    $collection->add(clone $couponRule);
                }
            }
        }

        return $collection;
    }


    /**
     * Build a Coupon Rule from form
     *
     * @param string $ruleServiceId Rule class name
     * @param array  $operators     Rule Operator (<, >, = )
     * @param array  $values        Values setting this Rule
     *
     * @throws \InvalidArgumentException
     * @return CouponRuleInterface Ready to use Rule or false
     */
    public function build($ruleServiceId, array $operators, array $values)
    {
        if (!$this->container->has($ruleServiceId)) {
            return false;
        }

        /** @var CouponRuleInterface $rule */
        $rule = $this->container->get($ruleServiceId);
        $rule->setValidatorsFromForm($operators, $values);

        return $rule;
    }
}