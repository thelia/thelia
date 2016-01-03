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
 * Class ContraintInterface
 * @package Thelia\Tools\Version\Constraints
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
interface ConstraintInterface
{
    /**
     * Normalize a version number in a version that will be used in `version_compare`
     *
     * @param string $version the version expression
     * @return string  the normalized expression
     */
    public function normalize($version, $strict = false);

    /**
     * Test if the version number is valid
     *
     * @param string $version the version number
     * @param bool $strict if false precision will be normalized. eg: 2.1.0 > 2.1 will become 2.1.0 > 2.1.0 (default false)
     * @return bool  true if the version is equal, otherwise false
     */
    public function test($version, $strict = false);
}
