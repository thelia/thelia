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
 * Class ConstraintEqual.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ConstraintEqual extends BaseConstraint
{
    public function __construct($expression)
    {
        $this->expression = str_replace('=', '', $expression);
    }
}
