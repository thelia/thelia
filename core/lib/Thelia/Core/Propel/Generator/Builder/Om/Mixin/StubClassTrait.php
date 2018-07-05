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

namespace Thelia\Core\Propel\Generator\Builder\Om\Mixin;

use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Override a Propel model class builder.
 * Add building behavior for stub model classes (the classes extending the implementation classes and containing
 * application-specific code, e.g. Model\Foo).
 * Generate the classes in the module model directory or the Thelia model directory.
 */
trait StubClassTrait
{
    public function getClassFilePath()
    {
        /** @var $this AbstractOMBuilder */

        $fs = new Filesystem();

        if ($this->getPackage() === 'Thelia.Model') {
            return $fs->makePathRelative(
                THELIA_LIB . '../' . parent::getClassFilePath(),
                THELIA_ROOT
            );
        } else {
            return $fs->makePathRelative(
                THELIA_MODULE_DIR . parent::getClassFilePath(),
                THELIA_ROOT
            );
        }
    }
}
