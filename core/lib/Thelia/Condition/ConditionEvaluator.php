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

namespace Thelia\Condition;

use Thelia\Condition\Implementation\ConditionInterface;

/**
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
        foreach ($conditions as $condition) {
            if (!$condition->isMatching()) {
                return false;
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
        switch ($o) {
            case Operators::DIFFERENT:
                // !=
                return ($v1 != $v2);
            case Operators::SUPERIOR:
                // >
                return ($v1 > $v2);
            case Operators::SUPERIOR_OR_EQUAL:
                // >=
                return ($v1 >= $v2);
            case Operators::INFERIOR:
                // <
                return ($v1 < $v2);
            case Operators::INFERIOR_OR_EQUAL:
                // <=
                return ($v1 <= $v2);
            case Operators::EQUAL:
                // ==
                return ($v1 == $v2);
            case Operators::IN:
                // in
                return (\in_array($v1, $v2));
            case Operators::OUT:
                // not in
                return (!\in_array($v1, $v2));
            default:
                throw new \Exception('Unrecognized operator ' . $o);
        }
    }
}
