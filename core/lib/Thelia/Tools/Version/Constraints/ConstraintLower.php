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
namespace Thelia\Tools\Version\Constraints;

/**
 * Class ConstraintLower.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ConstraintLower extends BaseConstraint
{
    public function __construct($expression, $strict = false)
    {
        $this->operator = $strict ? '<' : '<=';
        $this->expression = substr(
            (string) $expression,
            \strlen($this->operator)
        );
    }
}
