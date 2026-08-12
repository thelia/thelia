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

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Keeps Thelia's own services reachable from the test container.
 *
 * Symfony exposes a private service to the test container only as long as
 * something else in the compiled container still references it: a private
 * service nothing uses is removed, and one used exactly once is inlined into
 * its single consumer. Either way it disappears from the container the test
 * suite talks to.
 *
 * The suite fetches more than twenty private services, so whether a test can
 * reach the service it exercises depends on what else happens to be installed:
 * FileProcessorService, for one, only survives because a back office template
 * bundle injects it into a controller. Install a shop without that bundle and
 * the very same commit turns the test red, which is how the suite came to pass
 * in CI and fail on a plain checkout.
 *
 * Making Thelia's services public in the test environment removes that
 * dependency. Nothing changes for dev and prod, where the removal and inlining
 * passes keep working as before.
 */
class TestPublicServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!str_starts_with($id, 'Thelia\\')) {
                continue;
            }

            if ($definition->isPublic() || $definition->isAbstract() || $definition->hasErrors()) {
                continue;
            }

            $definition->setPublic(true);
        }
    }
}
