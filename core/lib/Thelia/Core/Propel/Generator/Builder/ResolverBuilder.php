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

namespace Thelia\Core\Propel\Generator\Builder;

use Symfony\Component\Filesystem\Filesystem;

class ResolverBuilder extends \Propel\Generator\Builder\ResolverBuilder
{
    public function getClassFilePath()
    {
        return rtrim((new Filesystem())->makePathRelative(
            TheliaMain_BUILD_DATABASE_PATH
            .parent::getClassFilePath(),
            THELIA_ROOT
        ), '/');
    }
}
