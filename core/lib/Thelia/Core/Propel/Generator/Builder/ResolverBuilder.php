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

namespace Thelia\Core\Propel\Generator\Builder;

use Symfony\Component\Filesystem\Filesystem;

class ResolverBuilder extends \Propel\Generator\Builder\ResolverBuilder
{
    /**
     * Gets the full path to the file for the current class.
     */
    public function getClassFilePath(): string
    {
        return rtrim((new Filesystem())->makePathRelative(
            THELIA_CACHE_DIR.$_SERVER['APP_ENV'].DS.'propel'.DS.'database'.DS
            .parent::getClassFilePath(),
            THELIA_ROOT,
        ), '/');
    }
}
