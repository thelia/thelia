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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Tool\AiCreatedToolToken;
use Thelia\Domain\Ai\Tool\Exception\AiToolTokenException;
use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;
use Thelia\Model\AiToolToken;
use Thelia\Model\AiToolTokenQuery;
use Thelia\Model\Map\AdminTableMap;

/**
 * The MCP tokens: created by an administrator for himself, shown once, revoked at will.
 *
 * Only the SHA-256 and the first characters of a token are stored. A token is bound to an administrator, takes
 * his rights as they are at each request, and is limited to the scopes it was given (or to nothing more than
 * his rights when it was created with `allRights`, a choice the caller makes on purpose).
 *
 * Public for the back office, which creates and revokes the tokens of the administrator through it.
 */
final readonly class AiToolTokenService
{
    public const int DEFAULT_DAYS = 90;

    public const int MAX_LABEL_LENGTH = 64;

    public const int MAX_SCOPES = 50;

    /** A token read again within this many seconds does not rewrite its last use. */
    private const int LAST_USED_GRANULARITY = 60;

    public function __construct(
        private AiToolTokenHasher $hasher,
        private AiToolAccessChecker $access,
        private AiSettings $settings,
        private AiToolApprovalRepository $approvals,
    ) {
    }

    /**
     * Creates a token for an administrator, who must hold CREATE on the resource of the MCP tokens.
     *
     * @param list<array<string, mixed>> $scopes    each `['resource' => 'admin.product', 'access' => 'VIEW']` or `['module' => 'CommerceAgents', 'access' => 'VIEW']`; required unless $allRights
     * @param bool                       $allRights gives the token every right of its administrator: only on purpose
     * @param \DateTimeImmutable|null    $expiresAt 90 days from now when null; never later than `ai_tool_token_max_days` from now
     *
     * @throws AiToolTokenException
     */
    public function create(int $adminId, string $label, array $scopes, bool $allRights = false, ?\DateTimeImmutable $expiresAt = null): AiCreatedToolToken
    {
        if (!$this->access->adminHoldsRight($adminId, AdminResources::AI_MCP, null, AccessManager::CREATE)) {
            throw AiToolTokenException::forbidden(AdminResources::AI_MCP);
        }

        $label = trim($label);

        if ('' === $label || mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            throw AiToolTokenException::invalid(\sprintf('The label of a token holds 1 to %d characters.', self::MAX_LABEL_LENGTH));
        }

        if ($allRights && [] !== $scopes) {
            throw AiToolTokenException::invalid('A token with all the rights of its administrator has no scopes.');
        }

        $scopes = $allRights ? [] : $this->normalizeScopes($scopes);

        $now = new \DateTimeImmutable();
        $latest = $now->modify(\sprintf('+%d days', $this->settings->toolTokenMaxDays()));
        $expiresAt ??= $now->modify(\sprintf('+%d days', self::DEFAULT_DAYS));

        if ($expiresAt <= $now) {
            throw AiToolTokenException::invalid('A token expires in the future.');
        }

        $expiresAt = min($expiresAt, $latest);

        $plain = $this->hasher->generate();
        $row = (new AiToolToken())
            ->setAdminId($adminId)
            ->setLabel($label)
            ->setTokenHash($this->hasher->fingerprint($plain))
            ->setTokenPrefix($this->hasher->display($plain))
            ->setAllRights($allRights ? 1 : 0)
            ->setScopes($allRights ? null : json_encode($scopes, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES))
            ->setExpiresAt($expiresAt);
        $row->save();

        return new AiCreatedToolToken($row->getId(), $plain, $row->getTokenPrefix(), $expiresAt);
    }

    /**
     * Revokes a token: refused from the next request, and the proposals it made that still wait are rejected
     * (note `token_revoked`). An administrator revokes his own tokens; those of another take UPDATE on the AI settings.
     *
     * @throws AiToolTokenException
     */
    public function revoke(int $tokenId, int $byAdminId): void
    {
        $row = $this->access->tokenRow($tokenId);

        if (!$row instanceof AiToolToken) {
            throw AiToolTokenException::notFound();
        }

        if ($row->getAdminId() !== $byAdminId && !$this->access->adminHoldsRight($byAdminId, AdminResources::AI, null, AccessManager::UPDATE)) {
            throw AiToolTokenException::forbidden(AdminResources::AI);
        }

        $now = new \DateTimeImmutable();
        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        \assert($connection instanceof ConnectionInterface);
        $connection->beginTransaction();

        try {
            if (null === $row->getRevokedAt()) {
                $row->setRevokedAt($now)->save();
            }

            $this->approvals->rejectPendingOfToken($tokenId, $now);
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * The token a presented secret designates, when it is stored, neither revoked nor expired, and its
     * administrator still exists.
     */
    public function authenticate(#[\SensitiveParameter] string $presented): ?AiToolToken
    {
        if (!$this->hasher->isWellFormed($presented)) {
            return null;
        }

        $fingerprint = $this->hasher->fingerprint($presented);
        $row = AiToolTokenQuery::create()->filterByTokenHash($fingerprint)->findOne();

        if (!$row instanceof AiToolToken || !$this->hasher->matches($fingerprint, $row->getTokenHash())) {
            return null;
        }

        $token = $this->access->usableToken($row->getId());

        if (!$token instanceof AiToolToken || !$this->administratorOf($token) instanceof Admin) {
            return null;
        }

        return $token;
    }

    /**
     * The administrator the token acts for, read from the database now, or null once he is gone.
     */
    public function administratorOf(AiToolToken $token): ?Admin
    {
        // The pool hands out the instance read earlier in the process, with the profile of that time.
        AdminTableMap::removeInstanceFromPool($token->getAdminId());

        return AdminQuery::create()->findPk($token->getAdminId());
    }

    /**
     * Writes the moment of the last use, at most once a minute.
     */
    public function markUsed(AiToolToken $token): void
    {
        $last = $token->getLastUsedAt();
        $now = new \DateTimeImmutable();

        if ($last instanceof \DateTimeInterface && $now->getTimestamp() - $last->getTimestamp() < self::LAST_USED_GRANULARITY) {
            return;
        }

        AiToolTokenQuery::create()->filterById($token->getId())->update(['LastUsedAt' => $now]);
    }

    /**
     * @param list<array<string, mixed>> $scopes
     *
     * @return list<array{resource?: string, module?: string, access: string}>
     */
    private function normalizeScopes(array $scopes): array
    {
        if ([] === $scopes || \count($scopes) > self::MAX_SCOPES) {
            throw AiToolTokenException::invalid(\sprintf('A token has 1 to %d scopes, unless it takes all the rights of its administrator.', self::MAX_SCOPES));
        }

        $normalized = [];

        foreach ($scopes as $scope) {
            $access = \is_array($scope) ? ($scope['access'] ?? null) : null;
            $resource = \is_array($scope) ? ($scope['resource'] ?? null) : null;
            $module = \is_array($scope) ? ($scope['module'] ?? null) : null;

            if (!\is_string($access) || !\in_array($access, [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE], true)) {
                throw AiToolTokenException::invalid('A scope names an access: VIEW, CREATE, UPDATE or DELETE.');
            }

            if (\is_string($resource) && null === $module && \in_array($resource, self::adminResources(), true)) {
                $normalized[$resource."\0".$access] = ['resource' => $resource, 'access' => $access];

                continue;
            }

            if (\is_string($module) && null === $resource && 1 === preg_match('/^[A-Za-z][A-Za-z0-9]{0,54}$/D', $module)) {
                $normalized[$module."\0".$access] = ['module' => $module, 'access' => $access];

                continue;
            }

            throw AiToolTokenException::invalid('A scope names either a known resource of the back office or a module, not both.');
        }

        return array_values($normalized);
    }

    /**
     * @return list<string>
     */
    private static function adminResources(): array
    {
        $values = [];

        foreach ((new \ReflectionClass(AdminResources::class))->getConstants() as $value) {
            if (\is_string($value) && str_starts_with($value, 'admin.')) {
                $values[] = $value;
            }
        }

        return $values;
    }
}
