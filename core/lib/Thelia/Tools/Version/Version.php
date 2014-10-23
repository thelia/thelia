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

    static public function test($version, $constraints, $strict = false)
    {
        $constraints = self::parse($constraints, $strict);

        /** @var ConstraintInterface $constraint */
        foreach ($constraints as $constraint) {
            if (!$constraint->test($version, $strict)) {
                return false;
            }
        }

        return true;
    }

    private static function parse($constraints)
    {
        $constraintsList = [];

        foreach (explode(" ", $constraints) as $expression) {

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