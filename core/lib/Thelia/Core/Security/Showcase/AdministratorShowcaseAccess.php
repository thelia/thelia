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

namespace Thelia\Core\Security\Showcase;

use OpenStudio\UxShowcaseBundle\Access\ShowcaseAccessInterface;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;

/**
 * Opens the component showcase to the administrators of the back office, on top of the debug mode the
 * showcase is open in by default. It is the one to name in `ux_showcase.access.service`: the showcase
 * sits on a path no firewall of Thelia covers, so the check has to be made here.
 *
 * The class implements an interface of an optional package, so it stays out of the services the core
 * registers by scanning its directories: the services of the core register it only when that
 * package is installed.
 */
final readonly class AdministratorShowcaseAccess implements ShowcaseAccessInterface
{
    public function __construct(
        private SecurityContext $securityContext,
        private bool $debug,
    ) {
    }

    public function isGranted(Request $request): bool
    {
        return $this->debug || $this->securityContext->hasAdminUser();
    }
}
