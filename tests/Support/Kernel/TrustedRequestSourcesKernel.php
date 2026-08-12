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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\TheliaKernel;

/**
 * Exposes the trusted host and proxy wiring the kernel applies while booting,
 * so it can be exercised without an installed Thelia behind it.
 */
final class TrustedRequestSourcesKernel extends TheliaKernel
{
    public static function applyTo(ContainerInterface $container): void
    {
        self::configureTrustedRequestSources($container);
    }
}
