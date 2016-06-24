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
 * Add building behavior for implementation model classes (the classes extended by the stub classes and containing
 * generated Propel code, e.g. Model\Base\Foo)
 * Generate the classes in the global model directory.
 */
trait ImplementationClassTrait
{
    public function getClassFilePath()
    {
        /** @var $this AbstractOMBuilder */

        $fs = new Filesystem();

        return $fs->makePathRelative(
            THELIA_CACHE_DIR
            . (defined('THELIA_PROPEL_BUILDER_ENVIRONMENT') ? ('/' . THELIA_PROPEL_BUILDER_ENVIRONMENT) : '')
            . '/propel/model/'
            . parent::getClassFilePath(),
            THELIA_ROOT
        );
    }
}
