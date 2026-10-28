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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Domain\Ai\AiActorType;
use Thelia\Domain\Ai\Tool\AiToolApprovalStatus;
use Thelia\Domain\Ai\Tool\AiToolApprovalView;
use Thelia\Domain\Ai\Tool\AiToolChannel;
use Thelia\Model\AiToolApproval;
use Thelia\Model\AiToolApprovalQuery;
use Thelia\Model\Map\AiToolApprovalTableMap;

/**
 * The rows of `ai_tool_approval`. Every change of status is one UPDATE that names the status it starts from
 * and says how many rows it touched: two decisions at once cannot both win, and nothing holds a lock or a
 * transaction while a tool applies its change.
 *
 * @internal
 */
final readonly class AiToolApprovalRepository
{
    private const int TEXT_LENGTH = 255;

    public const string NOTE_ADMIN_DELETED = 'admin_deleted';

    public const string NOTE_TOKEN_REVOKED = 'token_revoked';

    public function find(int $approvalId): ?AiToolApproval
    {
        AiToolApprovalTableMap::removeInstanceFromPool($approvalId);

        return AiToolApprovalQuery::create()->findPk($approvalId);
    }

    /**
     * Pending proposals, newest first, expired ones included: the caller filters what it may show.
     *
     * @return list<AiToolApproval>
     */
    public function pendingBatch(int $offset, int $limit, \DateTimeImmutable $now): array
    {
        return $this->batch(AiToolApprovalStatus::Pending, $offset, $limit, $now);
    }

    /**
     * Changes left `applying`, oldest first.
     *
     * @return list<AiToolApproval>
     */
    public function applyingBatch(int $offset, int $limit): array
    {
        return $this->batch(AiToolApprovalStatus::Applying, $offset, $limit, null);
    }

    /**
     * pending to applying: the decision of the approver, with the amended proposal when there is one.
     *
     * @param array{arguments: string, summary: string, before: string, after: string}|null $amendment
     *
     * @return bool false when the row was no longer pending or has expired: another decision won, or the time ran out
     */
    public function claim(int $approvalId, int $adminId, string $adminLogin, ?array $amendment, \DateTimeImmutable $now): bool
    {
        $set = '`status` = :applying, `decided_by_admin_id` = :admin, `decided_by_admin_login` = :login, `decided_at` = :now, `updated_at` = :now';
        $parameters = [':applying' => AiToolApprovalStatus::Applying->value, ':admin' => $adminId, ':login' => $adminLogin, ':now' => $this->time($now), ':id' => $approvalId, ':pending' => AiToolApprovalStatus::Pending->value];

        if (null !== $amendment) {
            $set .= ', `amended` = 1, `arguments` = :arguments, `summary` = :summary, `before_state` = :before, `after_state` = :after';
            $parameters += [':arguments' => $amendment['arguments'], ':summary' => $amendment['summary'], ':before' => $amendment['before'], ':after' => $amendment['after']];
        }

        return 1 === $this->update('UPDATE `ai_tool_approval` SET '.$set.' WHERE `id` = :id AND `status` = :pending AND `expires_at` > :now', $parameters);
    }

    /**
     * applying to applied or failed: how the change ended.
     */
    public function finish(int $approvalId, AiToolApprovalStatus $outcome, ?string $error, \DateTimeImmutable $now): bool
    {
        return 1 === $this->update(
            'UPDATE `ai_tool_approval` SET `status` = :outcome, `error` = :error, `updated_at` = :now WHERE `id` = :id AND `status` = :applying',
            [':outcome' => $outcome->value, ':error' => null === $error ? null : mb_strimwidth($error, 0, self::TEXT_LENGTH), ':now' => $this->time($now), ':id' => $approvalId, ':applying' => AiToolApprovalStatus::Applying->value],
        );
    }

    /**
     * pending to rejected.
     */
    public function reject(int $approvalId, int $adminId, string $adminLogin, ?string $note, \DateTimeImmutable $now): bool
    {
        return 1 === $this->update(
            'UPDATE `ai_tool_approval` SET `status` = :rejected, `decided_by_admin_id` = :admin, `decided_by_admin_login` = :login, `decided_at` = :now, `decision_note` = :note, `updated_at` = :now WHERE `id` = :id AND `status` = :pending AND `expires_at` > :now',
            [':rejected' => AiToolApprovalStatus::Rejected->value, ':admin' => $adminId, ':login' => $adminLogin, ':now' => $this->time($now), ':note' => null === $note ? null : mb_strimwidth($note, 0, self::TEXT_LENGTH), ':id' => $approvalId, ':pending' => AiToolApprovalStatus::Pending->value],
        );
    }

    /**
     * pending to expired.
     */
    public function expire(int $approvalId, \DateTimeImmutable $now): bool
    {
        return 1 === $this->update(
            'UPDATE `ai_tool_approval` SET `status` = :expired, `updated_at` = :now WHERE `id` = :id AND `status` = :pending',
            [':expired' => AiToolApprovalStatus::Expired->value, ':now' => $this->time($now), ':id' => $approvalId, ':pending' => AiToolApprovalStatus::Pending->value],
        );
    }

    /**
     * applying to failed, closed by an administrator who checked the shop.
     */
    public function closeApplying(int $approvalId, string $note, \DateTimeImmutable $now): bool
    {
        return 1 === $this->update(
            'UPDATE `ai_tool_approval` SET `status` = :failed, `error` = :note, `updated_at` = :now WHERE `id` = :id AND `status` = :applying',
            [':failed' => AiToolApprovalStatus::Failed->value, ':note' => mb_strimwidth($note, 0, self::TEXT_LENGTH), ':now' => $this->time($now), ':id' => $approvalId, ':applying' => AiToolApprovalStatus::Applying->value],
        );
    }

    /**
     * The proposals an administrator made, still waiting, when he is deleted: nobody answers for them any more.
     * Runs in the transaction of the deletion, before the foreign keys go to NULL.
     *
     * @return int the proposals rejected
     */
    public function rejectPendingProposedBy(int $adminId, \DateTimeImmutable $now): int
    {
        return $this->update(
            'UPDATE `ai_tool_approval` SET `status` = :rejected, `decision_note` = :note, `decided_at` = :now, `updated_at` = :now WHERE `proposed_by_admin_id` = :admin AND `status` = :pending',
            [':rejected' => AiToolApprovalStatus::Rejected->value, ':note' => self::NOTE_ADMIN_DELETED, ':now' => $this->time($now), ':admin' => $adminId, ':pending' => AiToolApprovalStatus::Pending->value],
        );
    }

    /**
     * The proposals made through a token that is revoked, still waiting.
     *
     * @return int the proposals rejected
     */
    public function rejectPendingOfToken(int $tokenId, \DateTimeImmutable $now): int
    {
        return $this->update(
            'UPDATE `ai_tool_approval` SET `status` = :rejected, `decision_note` = :note, `decided_at` = :now, `updated_at` = :now WHERE `token_id` = :token AND `status` = :pending',
            [':rejected' => AiToolApprovalStatus::Rejected->value, ':note' => self::NOTE_TOKEN_REVOKED, ':now' => $this->time($now), ':token' => $tokenId, ':pending' => AiToolApprovalStatus::Pending->value],
        );
    }

    public function toView(AiToolApproval $row): AiToolApprovalView
    {
        return new AiToolApprovalView(
            $row->getId(),
            $row->getToolName(),
            $row->getModule(),
            $row->getAclResource(),
            $row->getAclModule(),
            $row->getAclAccess(),
            $row->getTarget(),
            $row->getSummary(),
            $this->decode($row->getOriginalArguments()),
            $this->decode($row->getArguments()),
            (bool) $row->getAmended(),
            $this->decode($row->getBeforeState()),
            $this->decode($row->getAfterState()),
            AiToolApprovalStatus::from($row->getStatus()),
            AiToolChannel::from($row->getChannel()),
            $row->getUsageId(),
            $row->getCorrelationId(),
            AiActorType::from($row->getProposedByType()),
            $row->getProposedByAdminId(),
            $row->getProposedByAdminLogin(),
            $row->getTokenPrefix(),
            $this->date($row->getCreatedAt()) ?? new \DateTimeImmutable(),
            $this->date($row->getExpiresAt()) ?? new \DateTimeImmutable(),
            $row->getDecidedByAdminId(),
            $row->getDecidedByAdminLogin(),
            $this->date($row->getDecidedAt()),
            $row->getDecisionNote(),
            $row->getError(),
        );
    }

    /**
     * @return list<AiToolApproval>
     */
    private function batch(AiToolApprovalStatus $status, int $offset, int $limit, ?\DateTimeImmutable $notExpiredAt): array
    {
        $query = AiToolApprovalQuery::create()->filterByStatus($status->value);

        if (null !== $notExpiredAt) {
            $query->filterByExpiresAt($notExpiredAt, Criteria::GREATER_THAN)->orderById(Criteria::DESC);
        } else {
            $query->orderById(Criteria::ASC);
        }

        return array_values($query->offset($offset)->limit($limit)->find()->getData());
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(?string $json): array
    {
        $decoded = null === $json ? null : json_decode($json, true);

        return \is_array($decoded) ? $decoded : [];
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        return $value instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($value) : null;
    }

    private function time(\DateTimeImmutable $moment): string
    {
        return $moment->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string, int|string|null> $parameters
     */
    private function update(string $sql, array $parameters): int
    {
        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        \assert($connection instanceof ConnectionInterface);

        $statement = $connection->prepare($sql);
        $statement->execute($parameters);
        $affected = $statement->rowCount();
        $statement->closeCursor();

        return $affected;
    }
}
