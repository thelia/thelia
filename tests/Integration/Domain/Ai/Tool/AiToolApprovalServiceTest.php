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

namespace Thelia\Tests\Integration\Domain\Ai\Tool;

use AiProbeModule\ProbeReadTool;
use AiProbeModule\ProbeToolState;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiActorType;
use Thelia\Domain\Ai\AiToolCall;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Tool\AiToolApprovalStatus;
use Thelia\Domain\Ai\Tool\AiToolChannel;
use Thelia\Domain\Ai\Tool\AiToolContext;
use Thelia\Domain\Ai\Tool\Exception\AiToolApprovalException;
use Thelia\Domain\Ai\Tool\Exception\AiToolApprovalFailure;
use Thelia\Model\Admin;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\AiToolApprovalQuery;
use Thelia\Model\ProfileResourceQuery;
use Thelia\Tests\Support\Ai\AiToolTestCase;

/**
 * Deciding on a proposal: who may, in which order the checks run, and that a change is applied once at most.
 */
final class AiToolApprovalServiceTest extends AiToolTestCase
{
    private Admin $proposer;

    private Admin $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->proposer = $factory->admin(['login' => 'marie']);
        $this->approver = $factory->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW, AccessManager::UPDATE]], ['login' => 'paul']);
    }

    public function testApprovingAppliesTheChangeOnceAndRecordsWhoDecided(): void
    {
        $id = $this->propose();
        $this->logInAsAdmin($this->approver);

        $view = $this->approvals->approve($id);

        self::assertSame(AiToolApprovalStatus::Applied, $view->status);
        self::assertSame(1, ProbeToolState::$applyCalls);
        self::assertSame($this->approver->getId(), $view->decidedByAdminId);
        self::assertSame('paul', $view->decidedByAdminLogin);
        self::assertNotNull($view->decidedAt);
        self::assertNull($view->error);

        $change = ProbeToolState::$lastChange;
        self::assertSame($id, $change?->approvalId);
        self::assertSame('aiprobemodule_update', $change?->toolName);
        self::assertSame('probe:7', $change?->target);
        self::assertSame(['id' => 7, 'value' => 'new'], $change?->arguments);
        self::assertSame(['value' => 'old'], $change?->before);
        self::assertSame(['value' => 'new'], $change?->after);
        self::assertSame($this->approver->getId(), $change?->approverAdminId);
        self::assertSame(AiActorType::Admin, $change?->proposedBy);
    }

    public function testTheApprovalIsWrittenToTheAdminLog(): void
    {
        $id = $this->propose();
        $this->logInAsAdmin($this->approver);

        $this->approvals->approve($id);

        $log = AdminLogQuery::create()->filterByAdminLogin('paul')->filterByResource(AdminResources::PRODUCT)->filterByAction('UPDATE')->findOne();
        self::assertNotNull($log);
        self::assertStringContainsString('aiprobemodule_update', $log->getMessage());
        self::assertSame($id, $log->getResourceId());
    }

    public function testASecondApprovalOfTheSameProposalNeverAppliesTwice(): void
    {
        $id = $this->propose();
        $this->logInAsAdmin($this->approver);
        $this->approvals->approve($id);

        $this->assertRefused(AiToolApprovalFailure::NotPending, fn () => $this->approvals->approve($id));
        self::assertSame(1, ProbeToolState::$applyCalls);
    }

    public function testAConcurrentDecisionThatWinsBetweenTheCheckAndTheClaimStopsTheApplication(): void
    {
        $id = $this->propose();
        $this->logInAsAdmin($this->approver);
        // The other administrator rejects while this one amends: propose() runs between the read of the row and the claim.
        ProbeToolState::$onPropose = fn () => $this->approvalRows->reject($id, $this->proposer->getId(), 'marie', 'Too low', new \DateTimeImmutable());

        $this->assertRefused(AiToolApprovalFailure::NotPending, fn () => $this->approvals->approve($id, ['id' => 7, 'value' => 'other']));
        self::assertSame(0, ProbeToolState::$applyCalls);
        self::assertSame('rejected', AiToolApprovalQuery::create()->findPk($id)?->getStatus());
    }

    public function testAFailingApplicationEndsAsFailedWithASafeMessage(): void
    {
        ProbeToolState::$applyBehavior = 'stale';
        $id = $this->propose();
        $this->logInAsAdmin($this->approver);

        $view = $this->approvals->approve($id);

        self::assertSame(AiToolApprovalStatus::Failed, $view->status);
        self::assertSame('The value changed since the proposal.', $view->error);
        self::assertSame(0, AdminLogQuery::create()->filterByAdminLogin('paul')->filterByAction('UPDATE')->count());
    }

    public function testAnUnexpectedFailureNeverLeaksItsMessage(): void
    {
        ProbeToolState::$applyBehavior = 'crash';
        $id = $this->propose();
        $this->logInAsAdmin($this->approver);

        $view = $this->approvals->approve($id);

        self::assertSame(AiToolApprovalStatus::Failed, $view->status);
        self::assertSame('The tool failed while applying the change.', $view->error);
        self::assertCount(1, $this->logger->records);
    }

    public function testNothingIsDecidedInACommand(): void
    {
        $id = $this->propose();

        $this->assertRefused(AiToolApprovalFailure::NoAdminSession, fn () => $this->approvals->approve($id));
        self::assertSame(0, ProbeToolState::$applyCalls);
    }

    public function testNothingIsDecidedWithoutAnAdministratorInTheSession(): void
    {
        $id = $this->propose();
        $this->requests->push($this->plainRequest());

        $this->assertRefused(AiToolApprovalFailure::NoAdminSession, fn () => $this->approvals->reject($id));
    }

    public function testNoDecisionIsTakenWhileAToolRuns(): void
    {
        $id = $this->propose();
        $this->logInAsAdmin($this->approver);
        $outcome = null;
        ProbeToolState::$onExecute = function () use ($id, &$outcome): void {
            try {
                $this->approvals->approve($id);
            } catch (AiToolApprovalException $refusal) {
                $outcome = $refusal->reason;
            }
        };
        $offer = $this->registry->offer(new AiToolContext(AiActor::admin($this->proposer->getId()), 'aiprobe.tools_admin'), ['aiprobemodule_read']);

        $this->registry->call(new AiToolCall('toolu_1', 'aiprobemodule_read', ['text' => 'a']), $offer->context);

        self::assertSame(AiToolApprovalFailure::Reentrant, $outcome);
        self::assertSame(0, ProbeToolState::$applyCalls);
        self::assertSame('pending', AiToolApprovalQuery::create()->findPk($id)?->getStatus());
        self::assertFalse($this->guard->isRunning());
    }

    public function testAToolApplyingAChangeCannotDecideOnAnotherProposal(): void
    {
        $id = $this->propose();
        $other = $this->propose(8, 'other');
        $this->logInAsAdmin($this->approver);
        $outcome = null;
        ProbeToolState::$onApply = function () use ($other, &$outcome): void {
            try {
                $this->approvals->approve($other);
            } catch (AiToolApprovalException $refusal) {
                $outcome = $refusal->reason;
            }
        };

        $this->approvals->approve($id);

        self::assertSame(AiToolApprovalFailure::Reentrant, $outcome);
        self::assertSame(1, ProbeToolState::$applyCalls);
        self::assertSame('pending', AiToolApprovalQuery::create()->findPk($other)?->getStatus());
        self::assertFalse($this->guard->isRunning());
    }

    public function testAToolRebuildingAnAmendedProposalCannotDecideOnAnotherProposal(): void
    {
        $id = $this->propose();
        $other = $this->propose(8, 'other');
        $this->logInAsAdmin($this->approver);
        $outcome = null;
        ProbeToolState::$onPropose = function () use ($other, &$outcome): void {
            try {
                $this->approvals->reject($other);
            } catch (AiToolApprovalException $refusal) {
                $outcome = $refusal->reason;
            }
        };

        $this->approvals->approve($id, ['id' => 7, 'value' => 'amended']);

        self::assertSame(AiToolApprovalFailure::Reentrant, $outcome);
        self::assertSame('pending', AiToolApprovalQuery::create()->findPk($other)?->getStatus());
        self::assertFalse($this->guard->isRunning());
    }

    public function testAnApproverWhoLostTheRightIsRefused(): void
    {
        $id = $this->propose();
        $this->logInAsAdmin($this->approver);
        ProfileResourceQuery::create()->filterByProfileId($this->approver->getProfileId())->delete();

        $this->assertRefused(AiToolApprovalFailure::Forbidden, fn () => $this->approvals->approve($id));
        self::assertSame(0, ProbeToolState::$applyCalls);
    }

    public function testAnApproverWithoutTheRightOfTheToolIsRefusedToReject(): void
    {
        $id = $this->propose();
        $viewer = $this->createFixtureFactory()->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]);
        $this->logInAsAdmin($viewer);

        $this->assertRefused(AiToolApprovalFailure::Forbidden, fn () => $this->approvals->reject($id, 'no'));
    }

    public function testAToolThatNowAsksForAnotherRightIsGone(): void
    {
        $id = $this->propose();
        AiToolApprovalQuery::create()->filterById($id)->update(['AclAccess' => 'DELETE']);
        $this->logInAsAdmin($this->approver);

        $this->assertRefused(AiToolApprovalFailure::ToolGone, fn () => $this->approvals->approve($id));
        self::assertSame(0, ProbeToolState::$applyCalls);
    }

    public function testAToolThatIsNoLongerDeclaredIsGone(): void
    {
        $id = $this->propose();
        $this->buildServices(['aiprobemodule_read' => ProbeReadTool::class]);
        $this->logInAsAdmin($this->approver);

        $this->assertRefused(AiToolApprovalFailure::ToolGone, fn () => $this->approvals->approve($id));
    }

    public function testAToolThatIsNoLongerAWriteToolIsGone(): void
    {
        $id = $this->propose();
        AiToolApprovalQuery::create()->filterById($id)->update(['ToolName' => 'aiprobemodule_read']);
        $this->logInAsAdmin($this->approver);

        $this->assertRefused(AiToolApprovalFailure::ToolGone, fn () => $this->approvals->reject($id));
    }

    public function testAnExpiredProposalIsMarkedExpiredAndRefused(): void
    {
        $id = $this->propose();
        AiToolApprovalQuery::create()->filterById($id)->update(['ExpiresAt' => new \DateTimeImmutable('-1 minute')]);
        $this->logInAsAdmin($this->approver);

        try {
            $this->approvals->approve($id);
            self::fail('An expired proposal was approved.');
        } catch (AiToolApprovalException $refusal) {
            self::assertSame(AiToolApprovalFailure::Expired, $refusal->reason);
        }

        self::assertSame('expired', AiToolApprovalQuery::create()->findPk($id)?->getStatus());
        self::assertSame(0, ProbeToolState::$applyCalls);
    }

    public function testAnUnknownProposalIsNotFound(): void
    {
        $this->logInAsAdmin($this->approver);

        $this->assertRefused(AiToolApprovalFailure::NotFound, fn () => $this->approvals->approve(2000000000));
    }

    public function testRejectingNeverApplies(): void
    {
        $id = $this->propose();
        $this->logInAsAdmin($this->approver);

        $view = $this->approvals->reject($id, 'Too low');

        self::assertSame(AiToolApprovalStatus::Rejected, $view->status);
        self::assertSame('Too low', $view->decisionNote);
        self::assertSame('paul', $view->decidedByAdminLogin);
        self::assertSame(0, ProbeToolState::$applyCalls);

        $this->assertRefused(AiToolApprovalFailure::NotPending, fn () => $this->approvals->approve($id));
    }

    public function testAnAmendmentRebuildsTheProposalInTheContextOfTheApproverAndKeepsTheOriginal(): void
    {
        $id = $this->propose();
        $this->logInAsAdmin($this->approver);

        $view = $this->approvals->approve($id, ['id' => 7, 'value' => 'amended']);

        self::assertTrue($view->amended);
        self::assertSame(['id' => 7, 'value' => 'new'], $view->originalArguments);
        self::assertSame(['id' => 7, 'value' => 'amended'], $view->arguments);
        self::assertSame(['value' => 'amended'], $view->afterState);
        self::assertSame('Set the value of #7 to amended', $view->summary);
        self::assertSame(['id' => 7, 'value' => 'amended'], ProbeToolState::$lastChange?->arguments);

        $context = ProbeToolState::$lastProposeContext;
        self::assertSame(AiToolChannel::BackOffice, $context?->channel);
        self::assertSame((string) $this->approver->getId(), $context?->actor->identifier);
        self::assertSame(AiActorType::Admin, $context?->actor->type);
    }

    public function testAnAmendmentThatOnlyLooksEqualToTheOriginalIsStillAnAmendment(): void
    {
        $id = $this->propose(7, '1000');
        $this->logInAsAdmin($this->approver);

        $view = $this->approvals->approve($id, ['value' => '1e3', 'id' => 7]);

        self::assertTrue($view->amended);
        self::assertSame('1e3', ProbeToolState::$lastChange?->arguments['value'] ?? null);
    }

    public function testTheSameArgumentsInAnotherKeyOrderAreNotAnAmendment(): void
    {
        $id = $this->propose();
        $this->logInAsAdmin($this->approver);

        $view = $this->approvals->approve($id, ['value' => 'new', 'id' => 7]);

        self::assertFalse($view->amended);
        self::assertSame(0, ProbeToolState::$proposeCalls);
    }

    public function testAProposalThatExpiresBetweenTheCheckAndTheClaimIsNotClaimed(): void
    {
        $id = $this->propose();
        AiToolApprovalQuery::create()->filterById($id)->update(['ExpiresAt' => new \DateTimeImmutable('-1 minute')]);

        self::assertFalse($this->approvalRows->claim($id, $this->approver->getId(), 'paul', null, new \DateTimeImmutable()));
        self::assertFalse($this->approvalRows->reject($id, $this->approver->getId(), 'paul', null, new \DateTimeImmutable()));
        self::assertSame('pending', AiToolApprovalQuery::create()->findPk($id)?->getStatus());
    }

    public function testAnAmendmentThatBreaksTheSchemaIsRefusedBeforeAnythingIsDecided(): void
    {
        $id = $this->propose();
        $this->logInAsAdmin($this->approver);

        try {
            $this->approvals->approve($id, ['id' => 7, 'value' => str_repeat('x', 50)]);
            self::fail('The amendment was accepted.');
        } catch (InvalidAiArgumentException $invalid) {
            self::assertStringContainsString('/value: maxLength', $invalid->getMessage());
        }

        self::assertSame('pending', AiToolApprovalQuery::create()->findPk($id)?->getStatus());
        self::assertSame(0, ProbeToolState::$applyCalls);
    }

    public function testAnAmendmentThatChangesTheTargetIsRefused(): void
    {
        $id = $this->propose();
        $this->logInAsAdmin($this->approver);

        $this->expectException(InvalidAiArgumentException::class);

        $this->approvals->approve($id, ['id' => 8, 'value' => 'x']);
    }

    public function testPendingForShowsOnlyWhatTheAdministratorMayDecide(): void
    {
        $first = $this->propose();
        $second = $this->propose(8, 'another');
        $viewer = $this->createFixtureFactory()->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]);
        $this->logInAsAdmin($this->approver);
        $this->approvals->reject($first);

        self::assertSame([$second], array_map(static fn ($view): int => $view->id, $this->approvals->pendingFor($this->approver->getId())));
        self::assertSame([], $this->approvals->pendingFor($viewer->getId()));
        self::assertCount(1, $this->approvals->pendingFor($this->proposer->getId()));
    }

    public function testViewForGivesAProposalOfAnyStateOnlyToWhoHoldsItsRight(): void
    {
        $id = $this->propose();
        $viewer = $this->createFixtureFactory()->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]);
        $this->logInAsAdmin($this->approver);
        $this->approvals->reject($id);

        self::assertSame(AiToolApprovalStatus::Rejected, $this->approvals->viewFor($this->approver->getId(), $id)?->status);
        self::assertNull($this->approvals->viewFor($viewer->getId(), $id));
        self::assertNull($this->approvals->viewFor($this->approver->getId(), $id + 1000));
    }

    public function testAFloodOfProposalsTheAdministratorMayNotDecideNeverHidesTheOnesHeMay(): void
    {
        $mine = $this->propose();

        for ($index = 0; $index < 1005; ++$index) {
            (new \Thelia\Model\AiToolApproval())
                ->setToolName('aiprobemodule_update')
                ->setModule('aiprobemodule')
                ->setAclResource(AdminResources::ORDER)
                ->setAclAccess('UPDATE')
                ->setTarget('x:'.$index)
                ->setSummary('x')
                ->setOriginalArguments('{}')
                ->setArguments('{}')
                ->setBeforeState('{}')
                ->setAfterState('{}')
                ->setStatus('pending')
                ->setChannel('gateway')
                ->setProposedByType('system')
                ->setProposedByHash(str_repeat('b', 64))
                ->setExpiresAt(new \DateTimeImmutable('+1 day'))
                ->save();
        }

        self::assertSame([$mine], array_map(static fn ($view): int => $view->id, $this->approvals->pendingFor($this->approver->getId())));
    }

    public function testPendingForHidesTheExpiredAndHonoursTheLimit(): void
    {
        $ids = [$this->propose(1, 'a'), $this->propose(2, 'b'), $this->propose(3, 'c')];
        AiToolApprovalQuery::create()->filterById($ids[2])->update(['ExpiresAt' => new \DateTimeImmutable('-1 minute')]);

        self::assertSame([$ids[1], $ids[0]], array_map(static fn ($view): int => $view->id, $this->approvals->pendingFor($this->proposer->getId())));
        self::assertCount(1, $this->approvals->pendingFor($this->proposer->getId(), 1));
    }

    public function testAChangeLeftApplyingIsClosedByHandAndNeverReplayed(): void
    {
        $id = $this->propose();
        $this->approvalRows->claim($id, $this->approver->getId(), 'paul', null, new \DateTimeImmutable());
        $this->logInAsAdmin($this->approver);

        self::assertSame([$id], array_map(static fn ($view): int => $view->id, $this->approvals->applyingFor($this->approver->getId())));

        $view = $this->approvals->markFailed($id, 'Checked the shop: nothing was changed.');

        self::assertSame(AiToolApprovalStatus::Failed, $view->status);
        self::assertSame('Checked the shop: nothing was changed.', $view->error);
        self::assertSame(0, ProbeToolState::$applyCalls);
    }

    public function testOnlyAChangeLeftApplyingIsClosedByHand(): void
    {
        $id = $this->propose();
        $this->logInAsAdmin($this->approver);

        $this->assertRefused(AiToolApprovalFailure::NotPending, fn () => $this->approvals->markFailed($id, 'note'));
    }

    public function testDeletingAnAdministratorRejectsTheProposalsHeLeftPending(): void
    {
        $factory = $this->createFixtureFactory();
        $leaving = $factory->admin(['login' => 'leaving']);
        $pending = $this->propose(7, 'new', $leaving->getId());
        $applied = $this->propose(8, 'new', $leaving->getId());
        $others = $this->propose(9, 'new', $this->proposer->getId());
        $this->approvalRows->claim($applied, $this->approver->getId(), 'paul', null, new \DateTimeImmutable());

        $leaving->delete();

        $row = AiToolApprovalQuery::create()->findPk($pending);
        self::assertSame('rejected', $row?->getStatus());
        self::assertSame('admin_deleted', $row?->getDecisionNote());
        self::assertNull($row?->getProposedByAdminId());
        self::assertSame('leaving', $row?->getProposedByAdminLogin());
        self::assertSame('applying', AiToolApprovalQuery::create()->findPk($applied)?->getStatus());
        self::assertSame('pending', AiToolApprovalQuery::create()->findPk($others)?->getStatus());
    }

    public function testAProposalOfAnAdministratorDeletedInBulkIsRejectedWhenSomebodyDecides(): void
    {
        $leaving = $this->createFixtureFactory()->admin(['login' => 'bulk']);
        $id = $this->propose(7, 'new', $leaving->getId());
        $this->logInAsAdmin($this->approver);

        \Thelia\Model\AdminQuery::create()->filterById($leaving->getId())->delete();

        $this->assertRefused(AiToolApprovalFailure::NotPending, fn () => $this->approvals->approve($id));
        $row = AiToolApprovalQuery::create()->findPk($id);
        self::assertSame('rejected', $row?->getStatus());
        self::assertSame('admin_deleted', $row?->getDecisionNote());
        self::assertSame(0, ProbeToolState::$applyCalls);
    }

    public function testRevokingATokenRejectsWhatWasProposedThroughIt(): void
    {
        $token = (new \Thelia\Model\AiToolToken())->setAdminId($this->proposer->getId())->setLabel('t')->setTokenHash(hash('sha256', 'x'))->setTokenPrefix('thelia_mcp_abcd')->setAllRights(true)->setExpiresAt(new \DateTimeImmutable('+1 day'));
        $token->save();
        $through = $this->propose();
        $direct = $this->propose(8, 'x');
        AiToolApprovalQuery::create()->filterById($through)->update(['TokenId' => $token->getId()]);

        $rejected = $this->approvalRows->rejectPendingOfToken($token->getId(), new \DateTimeImmutable());

        self::assertSame(1, $rejected);
        self::assertSame('token_revoked', AiToolApprovalQuery::create()->findPk($through)?->getDecisionNote());
        self::assertSame('pending', AiToolApprovalQuery::create()->findPk($direct)?->getStatus());
    }

    /**
     * The proposal as an internal agent makes it: a call of the registry for an administrator.
     */
    private function propose(int $target = 7, string $value = 'new', ?int $proposerId = null): int
    {
        $context = new AiToolContext(AiActor::admin($proposerId ?? $this->proposer->getId()), 'aiprobe.tools_admin', 'aiprobe.conversation.88', 'fr_FR');
        $offer = $this->registry->offer($context, ['aiprobemodule_update']);
        $result = $this->registry->call(new AiToolCall('toolu_01', 'aiprobemodule_update', ['id' => $target, 'value' => $value]), $offer->context);

        self::assertNotNull($result->approvalId, 'The proposal was not stored.');
        ProbeToolState::$proposeCalls = 0;

        return $result->approvalId;
    }

    private function assertRefused(AiToolApprovalFailure $reason, \Closure $decision): void
    {
        try {
            $decision();
        } catch (AiToolApprovalException $refusal) {
            self::assertSame($reason, $refusal->reason);

            return;
        }

        self::fail(\sprintf('The decision was not refused with "%s".', $reason->value));
    }
}
