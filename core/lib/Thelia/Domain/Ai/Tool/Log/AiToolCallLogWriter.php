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

namespace Thelia\Domain\Ai\Tool\Log;

use Thelia\Domain\Ai\AiActorType;
use Thelia\Domain\Ai\Service\AiActorHasher;
use Thelia\Domain\Ai\Tool\Service\AiToolAccessChecker;
use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;
use Thelia\Model\AiToolCallLog;
use Thelia\Model\Map\AdminTableMap;

/**
 * Writes the rows of `ai_tool_call_log`: one per call, refusals included.
 *
 * An administrator is named in clear (id and login: who changed what is the point of the log); a customer, a
 * visitor and a job are known by their hash only. The arguments are kept 30 days, then the maintenance purge
 * empties them.
 *
 * @internal
 */
final readonly class AiToolCallLogWriter
{
    public const int ARGUMENT_RETENTION_DAYS = 30;

    public const int ARGUMENT_MAXIMUM_BYTES = 4096;

    public const string TRUNCATION_MARK = '…[truncated]';

    private const int ERROR_LENGTH = 255;

    public function __construct(private AiActorHasher $hasher, private AiToolAccessChecker $accessChecker)
    {
    }

    /**
     * @return int the identifier of the row
     */
    public function write(AiToolCallLogEntry $entry, ?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();
        $admin = $this->administrator($entry);
        $token = null === $entry->tokenId ? null : $this->accessChecker->tokenRow($entry->tokenId);

        $row = (new AiToolCallLog())
            ->setToolName($entry->toolName)
            ->setModule($entry->module)
            ->setEffect($entry->effect)
            ->setChannel($entry->channel->value)
            ->setStatus($entry->status->value)
            ->setError(null === $entry->error ? null : mb_strimwidth($entry->error, 0, self::ERROR_LENGTH))
            ->setUsageId($entry->usageId)
            ->setCorrelationId($entry->correlationId)
            ->setActorType($entry->actor->type->value)
            ->setActorHash($this->hasher->hash($entry->actor))
            ->setAdminId($admin?->getId())
            ->setAdminLogin($admin?->getLogin())
            ->setTokenId($token?->getId())
            ->setTokenPrefix($token?->getTokenPrefix())
            ->setApprovalId($entry->approvalId)
            ->setArguments($this->encode($entry->arguments))
            ->setArgumentsExpiresAt($now->modify(\sprintf('+%d days', self::ARGUMENT_RETENTION_DAYS)))
            ->setResultBytes($entry->resultBytes)
            ->setLatencyMs($entry->latencyMilliseconds);
        $row->save();

        return $row->getId();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function encode(array $arguments): string
    {
        $json = json_encode([] === $arguments ? new \stdClass() : $arguments, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PARTIAL_OUTPUT_ON_ERROR);

        if (false === $json) {
            return '{"_unencodable":true}';
        }

        if (\strlen($json) <= self::ARGUMENT_MAXIMUM_BYTES) {
            return $json;
        }

        return mb_strcut($json, 0, self::ARGUMENT_MAXIMUM_BYTES - \strlen(self::TRUNCATION_MARK)).self::TRUNCATION_MARK;
    }

    private function administrator(AiToolCallLogEntry $entry): ?Admin
    {
        if (AiActorType::Admin !== $entry->actor->type || 1 !== preg_match('/^[1-9]\d{0,9}$/', $entry->actor->identifier)) {
            return null;
        }

        AdminTableMap::removeInstanceFromPool((int) $entry->actor->identifier);

        return AdminQuery::create()->findPk((int) $entry->actor->identifier);
    }
}
