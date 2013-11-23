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

namespace Thelia\Condition;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Condition\Operators;
use Thelia\Condition\ConditionCollection;


/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Validate Conditions
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ConditionEvaluator
{
    /**
     * Check if an Event matches SerializableCondition
     *
     * @param ConditionCollection $conditions Conditions to check against the Event
     *
     * @return bool
     */
    public function isMatching(ConditionCollection $conditions)
    {
        $isMatching = true;
        /** @var ConditionInterface $condition */
        foreach ($conditions->getConditions() as $condition) {
            if (!$condition->isMatching()) {
                $isMatching = false;
            }
        }

        return $isMatching;

    }

    /**
     * Do variable comparison
     *
     * @param mixed  $v1 Variable 1
     * @param string $o  Operator ex : Operators::DIFFERENT
     * @param mixed  $v2 Variable 2
     *
     * @throws \Exception
     * @return bool
     */
    public function variableOpComparison($v1, $o, $v2)
    {
        if ($o == Operators::DIFFERENT) {
            return ($v1 != $v2);
        }

        switch ($o) {
            case Operators::SUPERIOR :
                // >
                if ($v1 > $v2) {
                    return true;
                } else {
                    continue;
                }
                break;
            case Operators::SUPERIOR_OR_EQUAL :
                // >=
                if ($v1 >= $v2) {
                    return true;
                } else {
                    continue;
                }
                break;
            case Operators::INFERIOR :
                // <
                if ($v1 < $v2) {
                    return true;
                } else {
                    continue;
                }
                break;
            case Operators::INFERIOR_OR_EQUAL :
                // <=
                if ($v1 <= $v2) {
                    return true;
                } else {
                    continue;
                }
                break;
            case Operators::EQUAL :
                // ==
                if ($v1 == $v2) {
                    return true;
                } else {
                    continue;
                }
                break;
            case Operators::IN:
                // in
                if (in_array($v1, $v2)) {
                    return true;
                } else {
                    continue;
                }
                break;
            case Operators::OUT:
                // not in
                if (!in_array($v1, $v2)) {
                    return true;
                } else {
                    continue;
                }
                break;
            default:
                throw new \Exception('Unrecognized operator ' . $o);
        }

        return false;
    }
}