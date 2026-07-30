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

namespace Thelia\Api\Service\API;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Thelia\Core\Security\User\UserInterface;

/**
 * Backs the `is_granted()` expression function when a resource security
 * expression is evaluated in-process (Twig `resources()`), outside any
 * Symfony firewall. The Symfony AuthorizationChecker only sees the token
 * of the current firewall, which is anonymous on front pages, so ROLE_*
 * checks are resolved against the Thelia session user instead. Anything
 * else falls back to the decorated Symfony checker.
 */
final readonly class SessionUserAuthorizationChecker implements AuthorizationCheckerInterface
{
    public function __construct(
        private mixed $user,
        private ?AuthorizationCheckerInterface $decorated,
    ) {
    }

    public function isGranted(mixed $attribute, mixed $subject = null): bool
    {
        if (\is_string($attribute) && str_starts_with($attribute, 'ROLE_')) {
            return $this->user instanceof UserInterface
                && \in_array($attribute, $this->user->getRoles(), true);
        }

        return $this->decorated?->isGranted($attribute, $subject) ?? false;
    }
}
