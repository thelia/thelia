<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tools\Version\Constraints;

/**
 * Class ConstraintGreater
 * @package Thelia\Tools\Version\Constraints
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ConstraintGreater extends BaseConstraint
{
    public function __construct($expression, $strict = false)
    {
        $this->operator = $strict ? ">" : ">=";
        $this->expression = substr(
            $expression,
            \strlen($this->operator)
        );
    }
}
