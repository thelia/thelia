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


namespace Thelia\Tools\Version;

use Thelia\Tools\Version\Constraints\ConstraintEqual;
use Thelia\Tools\Version\Constraints\ConstraintGreater;
use Thelia\Tools\Version\Constraints\ConstraintInterface;
use Thelia\Tools\Version\Constraints\ConstraintLower;
use Thelia\Tools\Version\Constraints\ConstraintNearlyEqual;

/**
 * Class Version
 * @package Thelia\Tools
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class Version
{
    /**
     * Test if a version matched the version contraints.
     *
     * constraints can be simple or complex (multiple constraints separated by space) :
     *
     * - "~2.1" : version 2.1.*
     * - "~2.1 <=2.1.4" : version 2.1.* but lower or equal to 2.1.4
     * - ">=2.1" : version 2.1.*, 2.2, ...
     * - ">2.1.1 <=2.1.5" : version greater than 2.1.1 but lower or equal than 2.1.5
     * - ...
     *
     * @param string $version           the version to test
     * @param string $constraints       the versions constraints
     * @param bool   $strict            if true 2.1 is different of 2.1.0, if false version are normalized so 2.1
     *                                  will be expended to 2.1.0
     * @param string $defaultComparison the default comparison if nothing provided
     * @return bool                     true if version matches the constraints
     */
    public static function test($version, $constraints, $strict = false, $defaultComparison = "=")
    {
        $constraints = self::parse($constraints, $defaultComparison);

        /** @var ConstraintInterface $constraint */
        foreach ($constraints as $constraint) {
            if (!$constraint->test($version, $strict)) {
                return false;
            }
        }

        return true;
    }

    private static function parse($constraints, $defaultComparison = "=")
    {
        $constraintsList = [];

        foreach (explode(" ", $constraints) as $expression) {
            if (1 === preg_match('/^[0-9]/', $expression)) {
                $expression = $defaultComparison . $expression;
            }

            if (strpos($expression, '>=') !== false) {
                $constraint = new ConstraintGreater($expression);
            } elseif (strpos($expression, '>') !== false) {
                $constraint = new ConstraintGreater($expression, true);
            } elseif (strpos($expression, '<=') !== false) {
                $constraint = new ConstraintLower($expression);
            } elseif (strpos($expression, '<') !== false) {
                $constraint = new ConstraintLower($expression, true);
            } elseif (strpos($expression, '~') !== false) {
                $constraint = new ConstraintNearlyEqual($expression);
            } else {
                $constraint = new ConstraintEqual($expression);
            }

            $constraintsList[] = $constraint;
        }

        return $constraintsList;
    }
}
