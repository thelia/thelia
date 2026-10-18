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

namespace Thelia\Tests\Support\Kernel;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Core\TheliaKernel;

/**
 * Exposes the step of the container build that reads the configuration files
 * of the activated modules, with the debug mode on so that a failure is thrown
 * instead of being logged and skipped.
 */
final class ModulesConfigurationKernel extends TheliaKernel
{
    /**
     * @param iterable<\Thelia\Model\Module> $modules
     */
    public function loadFor(ContainerBuilder $container, iterable $modules): void
    {
        $this->debug = true;

        $this->loadModulesConfiguration($container, $modules);
    }
}
