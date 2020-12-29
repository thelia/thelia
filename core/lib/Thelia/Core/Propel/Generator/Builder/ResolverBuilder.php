<?php

namespace Thelia\Core\Propel\Generator\Builder;

use Symfony\Component\Filesystem\Filesystem;

class ResolverBuilder extends \Propel\Generator\Builder\ResolverBuilder
{
    public function getClassFilePath()
    {
        return rtrim((new Filesystem())->makePathRelative(
            TheliaMain_BUILD_DATABASE_PATH
            . parent::getClassFilePath(),
            THELIA_ROOT
        ), '/');
    }
}
