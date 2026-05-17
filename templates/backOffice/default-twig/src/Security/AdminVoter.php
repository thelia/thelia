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

namespace BackOfficeDefaultTwigBundle\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Thelia\Core\Security\SecurityContext;

/**
 * Translate Symfony's `is_granted(attribute, subject)` calls into Thelia ACL checks
 * by delegating to {@see SecurityContext::isGranted()}.
 *
 *     {% if is_granted('UPDATE', 'admin.product') %}…{% endif %}
 *     {% if is_granted('UPDATE', { resource: 'admin.module', module: 'HookAdminHome' }) %}…{% endif %}
 *     {% if is_granted('ADMIN') %}…{% endif %}
 */
final class AdminVoter extends Voter
{
    public const ROLE_ADMIN = 'ADMIN';

    /** @var list<string> */
    public const ACCESS_LEVELS = ['VIEW', 'CREATE', 'UPDATE', 'DELETE'];

    public function __construct(
        private readonly SecurityContext $securityContext,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (self::ROLE_ADMIN === $attribute && null === $subject) {
            return true;
        }

        if (!\in_array($attribute, self::ACCESS_LEVELS, true)) {
            return false;
        }

        return \is_string($subject) || \is_array($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (self::ROLE_ADMIN === $attribute) {
            return $this->securityContext->hasAdminUser();
        }

        [$resource, $modules] = $this->resolveSubject($subject);

        return $this->securityContext->isGranted(
            [self::ROLE_ADMIN],
            [$resource],
            $modules,
            [$attribute],
        );
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function resolveSubject(mixed $subject): array
    {
        if (\is_string($subject)) {
            return [$subject, []];
        }

        if (\is_array($subject)) {
            $resource = (string) ($subject['resource'] ?? '');
            $module = $subject['module'] ?? null;
            $modules = match (true) {
                null === $module => [],
                \is_array($module) => array_values(array_map('strval', $module)),
                default => [(string) $module],
            };

            return [$resource, $modules];
        }

        return ['', []];
    }
}
