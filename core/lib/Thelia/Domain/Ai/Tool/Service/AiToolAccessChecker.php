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

namespace Thelia\Domain\Ai\Tool\Service;

use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiActorType;
use Thelia\Domain\Ai\Tool\Internal\AiToolDeclaration;
use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;
use Thelia\Model\AiToolToken;
use Thelia\Model\AiToolTokenQuery;
use Thelia\Model\Map\AdminTableMap;
use Thelia\Model\Map\AiToolTokenTableMap;

/**
 * The rights of an administrator on a tool: his ACL read from the database now (never the one of a session),
 * and for a call through MCP, the scopes of the token as well.
 *
 * An empty resource is skipped by SecurityContext::isUserGranted() and a call without resource nor module is
 * granted to every administrator: this class never asks with either, the declaration rules refuse them.
 *
 * @internal
 */
final readonly class AiToolAccessChecker
{
    public function __construct(private SecurityContext $securityContext)
    {
    }

    /**
     * Whether the actor holds the right the tool declares, now.
     */
    public function holdsRight(AiActor $actor, AiToolDeclaration $declaration, ?int $tokenId = null): bool
    {
        if (AiActorType::Admin !== $actor->type || 1 !== preg_match('/^[1-9]\d{0,9}$/', $actor->identifier)) {
            return false;
        }

        $adminId = (int) $actor->identifier;
        $attribute = $declaration->attribute;

        if (!$this->adminHoldsRight($adminId, $attribute->resource, $attribute->module, $attribute->access)) {
            return false;
        }

        return null === $tokenId || $this->tokenCovers($tokenId, $adminId, $attribute->resource, $attribute->module, $attribute->access);
    }

    /**
     * Whether the administrator holds the right on a resource or a module, read from the database now.
     */
    public function adminHoldsRight(int|Admin $admin, ?string $resource, ?string $module, string $access): bool
    {
        if (('' === $resource || null === $resource) && ('' === $module || null === $module)) {
            return false;
        }

        if (!$admin instanceof Admin) {
            // The pool hands out the instance read earlier in the process, with its profile of that time.
            AdminTableMap::removeInstanceFromPool($admin);
            $admin = AdminQuery::create()->findPk($admin);
        }

        if (!$admin instanceof Admin) {
            return false;
        }

        return $this->securityContext->isUserGranted(
            $admin->getRoles(),
            null === $resource || '' === $resource ? [] : [$resource],
            null === $module || '' === $module ? [] : [$module],
            [$access],
            $admin,
        );
    }

    /**
     * The token must be stored, belong to this administrator, be neither revoked nor expired, and either give
     * all the rights of its administrator or name the right of the tool among its scopes.
     */
    public function tokenCovers(int $tokenId, int $adminId, ?string $resource, ?string $module, string $access): bool
    {
        $token = $this->usableToken($tokenId);

        if (!$token instanceof AiToolToken || $token->getAdminId() !== $adminId) {
            return false;
        }

        if ($token->getAllRights()) {
            return true;
        }

        $scopes = json_decode((string) $token->getScopes(), true);

        if (!\is_array($scopes)) {
            return false;
        }

        foreach ($scopes as $scope) {
            if (!\is_array($scope) || ($scope['access'] ?? null) !== $access) {
                continue;
            }

            if ((null !== $resource && ($scope['resource'] ?? null) === $resource) || (null !== $module && ($scope['module'] ?? null) === $module)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return AiToolToken|null the token when it is stored, not revoked and not expired
     */
    public function usableToken(int $tokenId): ?AiToolToken
    {
        $token = $this->tokenRow($tokenId);

        if (!$token instanceof AiToolToken || null !== $token->getRevokedAt()) {
            return null;
        }

        $expiresAt = $token->getExpiresAt();

        return $expiresAt instanceof \DateTimeInterface && $expiresAt > new \DateTimeImmutable() ? $token : null;
    }

    /**
     * The stored token, revoked or not, read from the database now.
     */
    public function tokenRow(int $tokenId): ?AiToolToken
    {
        AiToolTokenTableMap::removeInstanceFromPool($tokenId);

        return AiToolTokenQuery::create()->findPk($tokenId);
    }
}
