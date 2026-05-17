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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\SecurityContext;

/**
 * Returns `null` when the current admin is granted, a 403 Response otherwise — callers can early-return:
 *
 *     if ($denied = $this->access->check(AdminResources::BRAND, [], 'UPDATE')) {
 *         return $denied;
 *     }
 *
 * Denials are appended to the admin audit log.
 */
readonly class AdminAccessChecker
{
    private const ADMIN_ROLE = 'ADMIN';

    public function __construct(
        private SecurityContext $securityContext,
        private AdminLogger $adminLogger,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<string>|string $resources
     * @param list<string>|string $modules
     * @param list<string>|string $accesses
     */
    public function check(array|string $resources, array|string $modules, array|string $accesses): ?Response
    {
        $resources = (array) $resources;
        $modules = (array) $modules;
        $accesses = (array) $accesses;

        if ($this->securityContext->isGranted([self::ADMIN_ROLE], $resources, $modules, $accesses)) {
            return null;
        }

        $this->adminLogger->log(
            implode(',', $resources),
            implode(',', $accesses),
            \sprintf(
                'User is not granted for resources [%s] with accesses [%s]',
                implode(',', $resources),
                implode(',', $accesses),
            ),
        );

        return new Response(
            $this->translator->trans("Sorry, you're not allowed to perform this action"),
            Response::HTTP_FORBIDDEN,
        );
    }
}
