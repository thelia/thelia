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
 * Class ContraintInterface.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
interface ConstraintInterface
{
    /**
     * Normalize a version number in a version that will be used in `version_compare`.
     *
     * @param string $version the version expression
     *
     * @return string the normalized expression
     */
    public function normalize($version, $strict = false);

    /**
     * Test if the version number is valid.
     *
     * @param string $version the version number
     * @param bool   $strict  if false precision will be normalized. eg: 2.1.0 > 2.1 will become 2.1.0 > 2.1.0 (default false)
     *
     * @return bool true if the version is equal, otherwise false
     */
    public function test($version, $strict = false);
}
