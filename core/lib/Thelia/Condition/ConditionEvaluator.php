<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Condition;

use Thelia\Condition\Implementation\ConditionInterface;

/**
 * Validate Conditions.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class ConditionEvaluator
{
    /**
     * Check if an Event matches SerializableCondition.
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
     * Do variable comparison.
     *
     * @param mixed  $v1 Variable 1
     * @param string $o  Operator ex : Operators::DIFFERENT
     * @param mixed  $v2 Variable 2
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function variableOpComparison($v1, $o, $v2)
    {
        return match ($o) {
            // !=
            Operators::DIFFERENT => $v1 != $v2,
            // >
            Operators::SUPERIOR => $v1 > $v2,
            // >=
            Operators::SUPERIOR_OR_EQUAL => $v1 >= $v2,
            // <
            Operators::INFERIOR => $v1 < $v2,
            // <=
            Operators::INFERIOR_OR_EQUAL => $v1 <= $v2,
            // ==
            Operators::EQUAL => $v1 == $v2,
            // in
            Operators::IN => \in_array($v1, $v2),
            // not in
            Operators::OUT => !\in_array($v1, $v2),
            default => throw new \Exception('Unrecognized operator '.$o),
        };
    }
}
