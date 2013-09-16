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
use Thelia\Constraint\Rule\Operators;
use Thelia\Coupon\CouponRuleCollection;


/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Validate Constraints
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ConstraintValidator
{

    /**
     * Check if a Customer meets SerializableRule
     *
     * @param CouponRuleCollection $rules Rules to check against the Customer
     *
     * @return bool
     */
    public function isMatching(CouponRuleCollection $rules)
    {
        $isMatching = true;
        /** @var CouponRuleInterface $rule */
        foreach ($rules->getRules() as $rule) {
            if (!$rule->isMatching()) {
                $isMatching = false;
            }
        }

        return $isMatching;

    }

    /**
     * Do variable comparison
     *
     * @param mixed $v1 Variable 1
     * @param string $o  Operator
     *
     * @param mixed $v2 Variable 2
     * @throws \Exception
     * @return bool
     */
    public function variableOpComparison($v1, $o, $v2) {
        if ($o == Operators::DIFFERENT) {
            return ($v1 != $v2);
        } // could put this elsewhere...
//        $operators = str_split($o);
//        foreach($o as $operator) {
            switch ($o) { // return will exit switch, foreach loop, function
                case Operators::SUPERIOR : // >
                    if ($v1 > $v2) {
                        return true;
                    } else {
                        continue;
                    } break;
                case Operators::SUPERIOR_OR_EQUAL : // >=
                    if ($v1 >= $v2) {
                        return true;
                    } else {
                        continue;
                    } break;
                case Operators::INFERIOR : // <
                    if ($v1 < $v2) {
                        return true;
                    } else {
                        continue;
                    } break;
                case Operators::INFERIOR_OR_EQUAL : // <=
                    if ($v1 <= $v2) {
                        return true;
                    } else {
                        continue;
                    } break;
                case Operators::EQUAL : // ==
                    if ($v1 == $v2) {
                        return true;
                    } else {
                        continue;
                    } break;
                case Operators::IN:
                    if (in_array($v1, $v2)) { // in
                        return true;
                    } else {
                        continue;
                    } break;
                case Operators::OUT:
                    if (!in_array($v1, $v2)) { // not in
                        return true;
                    } else {
                        continue;
                    } break;
                default: throw new \Exception('Unrecognized operator ' . $o);
            }
//        }
        return false;
    }
}