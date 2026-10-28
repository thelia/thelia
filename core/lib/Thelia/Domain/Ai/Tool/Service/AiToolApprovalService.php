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
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiActorType;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Tool\AiApprovedChange;
use Thelia\Domain\Ai\Tool\AiToolApprovalServiceInterface;
use Thelia\Domain\Ai\Tool\AiToolApprovalStatus;
use Thelia\Domain\Ai\Tool\AiToolApprovalView;
use Thelia\Domain\Ai\Tool\AiToolContext;
use Thelia\Domain\Ai\Tool\AiToolEffect;
use Thelia\Domain\Ai\Tool\AiWriteToolInterface;
use Thelia\Domain\Ai\Tool\Exception\AiStaleChangeException;
use Thelia\Domain\Ai\Tool\Exception\AiToolApprovalException;
use Thelia\Domain\Ai\Tool\Exception\AiToolApprovalFailure;
use Thelia\Domain\Ai\Tool\Exception\AiToolFailedException;
use Thelia\Domain\Ai\Tool\Internal\AiToolDeclaration;
use Thelia\Model\Admin;
use Thelia\Model\AdminLog;
use Thelia\Model\AiToolApproval;

/**
 * The decisions on the proposals of the write tools.
 *
 * Who decides comes from the security context of the request (an administrator in the session of the back
 * office), never from a parameter; a command, a request without administrator, or a tool call in progress
 * cannot decide. The sequence of approve() is the one of the contract: read the row, check the declaration
 * and the right of the approver today, rebuild the proposal if he amended it, take the decision with one
 * conditional UPDATE, apply in a transaction of its own, then write how it ended.
 *
 * No lock is held and no savepoint is used while the tool applies: Propel counts nested transactions, and a
 * rollback inside a listener would make the outer one impossible to commit. A change left `applying` by a
 * process that died is closed by hand with markFailed(); nothing replays it.
 *
 * @internal
 */
final readonly class AiToolApprovalService implements AiToolApprovalServiceInterface
{
    private const int SCAN_PAGE = 200;

    private const int SCAN_LIMIT = 1000;

    private const string APPLY_FAILED_MESSAGE = 'The tool failed while applying the change.';

    public function __construct(
        private AiToolApprovalRepository $approvals,
        private AiToolDeclarations $declarations,
        private AiToolInstances $instances,
        private AiToolAccessChecker $accessChecker,
        private AiToolArgumentValidator $validator,
        private AiToolExecutionGuard $guard,
        private SecurityContext $securityContext,
        private RequestStack $requests,
        private LoggerInterface $logger,
    ) {
    }

    public function pendingFor(int $adminId, int $limit = 50): array
    {
        return $this->visibleTo($adminId, $limit, false);
    }

    /**
     * The changes left `applying` that this administrator may close with markFailed().
     *
     * @return list<AiToolApprovalView>
     */
    public function applyingFor(int $adminId, int $limit = 50): array
    {
        return $this->visibleTo($adminId, $limit, true);
    }

    public function viewFor(int $adminId, int $approvalId): ?AiToolApprovalView
    {
        $row = $this->approvals->find($approvalId);

        if (!$row instanceof AiToolApproval || !$this->accessChecker->adminHoldsRight($adminId, $row->getAclResource(), $row->getAclModule(), $row->getAclAccess())) {
            return null;
        }

        return $this->approvals->toView($row);
    }

    public function approve(int $approvalId, ?array $amendedArguments = null): AiToolApprovalView
    {
        $approver = $this->approver();
        [$row, $declaration] = $this->decidable($approvalId, $approver);
        $tool = $this->instances->get($declaration->name());
        \assert($tool instanceof AiWriteToolInterface);

        $amendment = null === $amendedArguments || self::normalized($amendedArguments) === self::normalized($this->decode($row->getArguments())) ? null : $this->amend($tool, $row, $approver, $amendedArguments);
        $now = new \DateTimeImmutable();

        if (!$this->approvals->claim($approvalId, $approver->getId(), $approver->getLogin(), $amendment, $now)) {
            throw new AiToolApprovalException(AiToolApprovalFailure::NotPending, 'Another decision was taken on this proposal first.');
        }

        $claimed = $this->approvals->find($approvalId);
        \assert($claimed instanceof AiToolApproval);

        $error = $this->apply($tool, $claimed, $approver);
        $this->approvals->finish($approvalId, null === $error ? AiToolApprovalStatus::Applied : AiToolApprovalStatus::Failed, $error, new \DateTimeImmutable());

        if (null === $error) {
            $this->audit($claimed, $approver);
        }

        return $this->view($approvalId);
    }

    public function reject(int $approvalId, ?string $note = null): AiToolApprovalView
    {
        $approver = $this->approver();
        $this->decidable($approvalId, $approver);

        if (!$this->approvals->reject($approvalId, $approver->getId(), $approver->getLogin(), $note, new \DateTimeImmutable())) {
            throw new AiToolApprovalException(AiToolApprovalFailure::NotPending, 'Another decision was taken on this proposal first.');
        }

        return $this->view($approvalId);
    }

    public function markFailed(int $approvalId, string $note): AiToolApprovalView
    {
        $approver = $this->approver();
        $row = $this->approvals->find($approvalId) ?? throw new AiToolApprovalException(AiToolApprovalFailure::NotFound, 'This proposal does not exist.');

        if (AiToolApprovalStatus::Applying->value !== $row->getStatus()) {
            throw new AiToolApprovalException(AiToolApprovalFailure::NotPending, 'Only a change left in the state "applying" is closed this way.');
        }

        if (!$this->accessChecker->adminHoldsRight($approver, $row->getAclResource(), $row->getAclModule(), $row->getAclAccess())) {
            throw new AiToolApprovalException(AiToolApprovalFailure::Forbidden, 'You do not hold the right this change needs.');
        }

        if (!$this->approvals->closeApplying($approvalId, $note, new \DateTimeImmutable())) {
            throw new AiToolApprovalException(AiToolApprovalFailure::NotPending, 'This change is no longer in the state "applying".');
        }

        return $this->view($approvalId);
    }

    /**
     * The administrator of the session of the back office: refused in a command, without one, and while a tool runs.
     */
    private function approver(): Admin
    {
        if ($this->guard->isRunning()) {
            throw new AiToolApprovalException(AiToolApprovalFailure::Reentrant, 'A tool call is running: it cannot decide on an approval.');
        }

        if (null === $this->requests->getMainRequest()) {
            throw new AiToolApprovalException(AiToolApprovalFailure::NoAdminSession, 'An approval is decided in the back office, not in a command.');
        }

        $admin = $this->securityContext->getAdminUser();

        if (!$admin instanceof Admin) {
            throw new AiToolApprovalException(AiToolApprovalFailure::NoAdminSession, 'An approval is decided by an administrator who is logged in.');
        }

        return $admin;
    }

    /**
     * Steps 1 to 3 of a decision: the row is pending and not expired, the tool still declares the right stored on
     * the row, the approver holds it today.
     *
     * @return array{AiToolApproval, AiToolDeclaration}
     */
    private function decidable(int $approvalId, Admin $approver): array
    {
        $row = $this->approvals->find($approvalId) ?? throw new AiToolApprovalException(AiToolApprovalFailure::NotFound, 'This proposal does not exist.');

        if (AiToolApprovalStatus::Pending->value !== $row->getStatus()) {
            throw new AiToolApprovalException(AiToolApprovalFailure::NotPending, 'This proposal is no longer pending.');
        }

        $now = new \DateTimeImmutable();

        if ($this->approvals->rejectIfProposerIsGone($approvalId, $now)) {
            throw new AiToolApprovalException(AiToolApprovalFailure::NotPending, 'The administrator who made this proposal no longer exists: it was rejected.');
        }

        $expiresAt = $row->getExpiresAt();

        if (!$expiresAt instanceof \DateTimeInterface || $expiresAt <= $now) {
            $this->approvals->expire($approvalId, $now);

            throw new AiToolApprovalException(AiToolApprovalFailure::Expired, 'This proposal has expired.');
        }

        $declaration = $this->declarations->find($row->getToolName());
        $attribute = $declaration?->attribute;

        if (null === $declaration || AiToolEffect::Write !== $declaration->effect || $attribute?->resource !== $row->getAclResource() || $attribute?->module !== $row->getAclModule() || $attribute?->access !== $row->getAclAccess()) {
            throw new AiToolApprovalException(AiToolApprovalFailure::ToolGone, 'The tool that made this proposal is gone or now asks for another right.');
        }

        if (!$this->accessChecker->adminHoldsRight($approver, $row->getAclResource(), $row->getAclModule(), $row->getAclAccess())) {
            throw new AiToolApprovalException(AiToolApprovalFailure::Forbidden, 'You do not hold the right this change needs.');
        }

        return [$row, $declaration];
    }

    /**
     * Rebuilds the proposal from the amended arguments, in the context of the approver.
     *
     * @param array<string, mixed> $amendedArguments
     *
     * @return array{arguments: string, summary: string, before: string, after: string}
     */
    private function amend(AiWriteToolInterface $tool, AiToolApproval $row, Admin $approver, array $amendedArguments): array
    {
        $details = $this->validator->validate($tool->inputSchema(), $amendedArguments);

        if ([] !== $details) {
            throw new InvalidAiArgumentException('The amended arguments do not fit the schema of the tool: '.implode('; ', $details));
        }

        $context = AiToolContext::forBackOffice(AiActor::admin($approver->getId()), $row->getUsageId(), $row->getCorrelationId(), $approver->getLocale());
        $proposal = $this->guard->run(static fn () => $tool->propose($amendedArguments, $context));

        if ($proposal->target !== $row->getTarget()) {
            throw new InvalidAiArgumentException('The amended arguments change what the proposal touches: reject it and ask for another one.');
        }

        $encode = static fn (array $data): string => json_encode([] === $data ? new \stdClass() : $data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return ['arguments' => $encode($amendedArguments), 'summary' => $proposal->summary, 'before' => $encode($proposal->before), 'after' => $encode($proposal->after)];
    }

    /**
     * @return string|null null when the change was applied, else why it was not (safe for the administrator)
     */
    private function apply(AiWriteToolInterface $tool, AiToolApproval $row, Admin $approver): ?string
    {
        $change = new AiApprovedChange(
            $row->getId(),
            $row->getToolName(),
            $row->getTarget(),
            $this->decode($row->getArguments()),
            $this->decode($row->getBeforeState()),
            $this->decode($row->getAfterState()),
            $approver->getId(),
            AiActorType::from($row->getProposedByType()),
        );

        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        \assert($connection instanceof ConnectionInterface);
        $connection->beginTransaction();

        try {
            $this->guard->run(static fn () => $tool->apply($change));
            $connection->commit();

            return null;
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            if ($exception instanceof AiStaleChangeException || $exception instanceof AiToolFailedException) {
                return $exception->getMessage();
            }

            $this->logger->error('Applying the approved change {approval} of the AI tool "{tool}" failed.', ['approval' => $row->getId(), 'tool' => $row->getToolName(), 'exception' => $exception]);

            return self::APPLY_FAILED_MESSAGE;
        }
    }

    private function audit(AiToolApproval $row, Admin $approver): void
    {
        $request = $this->requests->getMainRequest();

        if (null === $request) {
            return;
        }

        AdminLog::append(
            $row->getAclResource() ?? (string) $row->getAclModule(),
            $row->getAclAccess(),
            mb_strimwidth(\sprintf('AI tool %s applied (approval %d): %s', $row->getToolName(), $row->getId(), $row->getSummary()), 0, 500),
            $request,
            $approver,
            false,
            $row->getId(),
        );
    }

    /**
     * @return list<AiToolApprovalView>
     */
    private function visibleTo(int $adminId, int $limit, bool $applying): array
    {
        $views = [];
        $now = new \DateTimeImmutable();

        // The right of each kind of proposal is tested once, then only the proposals of the rights he holds are read:
        // a flood of proposals he may not decide never hides the ones he may.
        $granted = array_values(array_filter(
            $this->approvals->rightsAsked($applying ? AiToolApprovalStatus::Applying : AiToolApprovalStatus::Pending, $applying ? null : $now),
            fn (array $right): bool => $this->accessChecker->adminHoldsRight($adminId, $right['resource'], $right['module'], $right['access']),
        ));

        for ($offset = 0; $offset < self::SCAN_LIMIT && \count($views) < $limit; $offset += self::SCAN_PAGE) {
            $batch = $applying ? $this->approvals->applyingBatch($offset, self::SCAN_PAGE, $granted) : $this->approvals->pendingBatch($offset, self::SCAN_PAGE, $now, $granted);

            foreach ($batch as $row) {
                $views[] = $this->approvals->toView($row);

                if (\count($views) >= $limit) {
                    break;
                }
            }

            if (\count($batch) < self::SCAN_PAGE) {
                break;
            }
        }

        return $views;
    }

    private function view(int $approvalId): AiToolApprovalView
    {
        $row = $this->approvals->find($approvalId);
        \assert($row instanceof AiToolApproval);

        return $this->approvals->toView($row);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * The keys of an object in a fixed order, so that two equal objects compare equal with `===`: a loose `==` would
     * hold `"1e3"` for `"1000"` and silently drop the amendment of the approver.
     *
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private static function normalized(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = self::normalized($value);
            }
        }

        if (!array_is_list($data)) {
            ksort($data);
        }

        return $data;
    }

    private function decode(?string $json): array
    {
        $decoded = null === $json ? null : json_decode($json, true);

        return \is_array($decoded) ? $decoded : [];
    }
}
