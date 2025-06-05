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
    /**
     * Gets the full path to the file for the current class.
     */
    public function getClassFilePath(): string
    {
        /** @var $this AbstractOMBuilder */
        $fs = new Filesystem();

        if ($this->getPackage() === 'Thelia.Model') {
            $path = $fs->makePathRelative(
                THELIA_LIB.'..'.DS.parent::getClassFilePath(),
                THELIA_ROOT
            );
        } else {
            $modulePath = file_exists(THELIA_MODULE_DIR.parent::getClassFilePath())
                ? THELIA_MODULE_DIR.parent::getClassFilePath()
                : THELIA_LOCAL_MODULE_DIR.parent::getClassFilePath();

            $path = $fs->makePathRelative(
                $modulePath,
                THELIA_ROOT
            );
        }

        return rtrim($path, '/');
    }
}
