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

namespace BackOfficeDefaultTwigBundle\Service\Admin;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\AdminLog;

/**
 * Inject-friendly wrapper around the legacy static `AdminLog::append(...)` audit log.
 */
readonly class AdminLogger
{
    public function __construct(
        private RequestStack $requestStack,
        private SecurityContext $securityContext,
    ) {
    }

    public function log(
        string $resource,
        string $access,
        string $message,
        ?int $resourceId = null,
    ): void {
        AdminLog::append(
            $resource,
            $access,
            $message,
            $this->requestStack->getMainRequest(),
            $this->securityContext->getAdminUser(),
            true,
            $resourceId,
        );
    }
}
