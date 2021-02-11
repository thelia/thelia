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

namespace Thelia\Core;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * extends Symfony\Component\DependencyInjection\ContainerBuilder for changing some behavior
 *
 * Class TheliaContainerBuilder
 * @package Thelia\Core
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class TheliaContainerBuilder extends ContainerBuilder
{
    public function compile(bool $resolveEnvPlaceholders = false)
    {
    }

    public function customCompile()
    {
        parent::compile();
    }
}
