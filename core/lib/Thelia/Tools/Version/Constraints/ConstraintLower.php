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

namespace Thelia\Tools\Version\Constraints;

/**
 * Class ConstraintLower
 * @package Thelia\Tools\Version\Constraints
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ConstraintLower extends BaseConstraint
{
    public function __construct($expression, $strict = false)
    {
        $this->operator = $strict ? "<" : "<=";
        $this->expression = substr(
            $expression,
            strlen($this->operator)
        );
    }
}
