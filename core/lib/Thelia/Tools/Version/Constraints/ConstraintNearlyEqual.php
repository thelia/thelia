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
 * Class ConstraintNearlyEqual.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ConstraintNearlyEqual extends BaseConstraint
{
    public function __construct($expression)
    {
        $this->expression = str_replace('~', '', $expression);
    }

    public function normalize($version, $strict = false)
    {
        if (!$strict) {
            $version = $this->normalizePrecision($version, false);
        }

        return $version;
    }
}
