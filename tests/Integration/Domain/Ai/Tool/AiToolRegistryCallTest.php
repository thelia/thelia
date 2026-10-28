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

use AiProbeModule\ProbeToolState;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiMessageRole;
use Thelia\Domain\Ai\AiToolCall;
use Thelia\Domain\Ai\Tool\AiToolCallStatus;
use Thelia\Domain\Ai\Tool\AiToolContext;
use Thelia\Domain\Ai\Tool\AiToolResult;
use Thelia\Domain\Ai\Tool\Service\AiToolRateLimiter;
use Thelia\Model\AiToolApprovalQuery;
use Thelia\Model\AiToolCallLogQuery;
use Thelia\Tests\Support\Ai\AiToolTestCase;

/**
 * What a call answers, status by status, and what it leaves in the journal.
 */
final class AiToolRegistryCallTest extends AiToolTestCase
{
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminId = $this->createFixtureFactory()->admin(['login' => 'marie'])->getId();
    }

    public function testASuccessfulReadAnswersTheObjectOfTheTool(): void
    {
        $result = $this->call('aiprobemodule_read', ['text' => 'mug', 'limit' => 2]);

        self::assertSame(AiToolCallStatus::Succeeded, $result->status);
        self::assertSame(['echo' => 'mug', 'locale' => 'fr_FR'], $result->content);
        self::assertSame(1, ProbeToolState::$executeCalls);
    }

    public function testAnEmptyResultIsAnEmptyObjectInTheMessage(): void
    {
        ProbeToolState::$readBehavior = 'empty';

        $result = $this->call('aiprobemodule_read', ['text' => 'mug']);

        self::assertSame(AiToolCallStatus::Succeeded, $result->status);
        self::assertSame('{}', $result->toMessage('toolu_01')->content);
    }

    public function testInvalidArgumentsNameThePlaceAndNeverTheValue(): void
    {
        $result = $this->call('aiprobemodule_read', ['text' => 'mug', 'limit' => 99999]);

        self::assertSame(AiToolCallStatus::InvalidArguments, $result->status);
        self::assertSame(['error' => 'invalid_arguments', 'details' => ['/limit: maximum']], $result->content);
        self::assertStringNotContainsString('99999', json_encode($result->content, \JSON_THROW_ON_ERROR));
        self::assertSame(0, ProbeToolState::$executeCalls);
    }

    public function testAnUnknownArgumentIsRefused(): void
    {
        $result = $this->call('aiprobemodule_read', ['text' => 'mug', 'admin' => true]);

        self::assertSame(AiToolCallStatus::InvalidArguments, $result->status);
        self::assertSame(0, ProbeToolState::$executeCalls);
    }

    public function testAMissingRequiredArgumentIsRefused(): void
    {
        $result = $this->call('aiprobemodule_read', []);

        self::assertSame(['error' => 'invalid_arguments', 'details' => ['/text: required']], $result->content);
    }

    public function testAToolThatThrowsAnswersItsOwnMessage(): void
    {
        ProbeToolState::$readBehavior = 'failed';

        $result = $this->call('aiprobemodule_read', ['text' => 'mug']);

        self::assertSame(AiToolCallStatus::Failed, $result->status);
        self::assertSame(['error' => 'failed', 'message' => 'The catalogue is closed.'], $result->content);
    }

    public function testAnyOtherExceptionIsGenericForTheModelAndTracedInTheApplicationLog(): void
    {
        ProbeToolState::$readBehavior = 'crash';

        $result = $this->call('aiprobemodule_read', ['text' => 'mug']);

        self::assertSame(['error' => 'failed', 'message' => 'The tool failed.'], $result->content);
        self::assertStringNotContainsString('secret', json_encode($result->content, \JSON_THROW_ON_ERROR));
        self::assertCount(1, $this->logger->records);
        self::assertInstanceOf(\RuntimeException::class, $this->logger->records[0][2]['exception']);
        self::assertStringNotContainsString('secret', (string) AiToolCallLogQuery::create()->findPk($result->logId)?->getError());
    }

    public function testAListIsNotAnObjectAndFails(): void
    {
        ProbeToolState::$readBehavior = 'list';

        $result = $this->call('aiprobemodule_read', ['text' => 'mug']);

        self::assertSame(AiToolCallStatus::Failed, $result->status);
        self::assertSame('result_not_object', AiToolCallLogQuery::create()->findPk($result->logId)?->getError());
    }

    public function testAResultThatCannotBeEncodedFails(): void
    {
        ProbeToolState::$readBehavior = 'nan';

        self::assertSame(AiToolCallStatus::Failed, $this->call('aiprobemodule_read', ['text' => 'mug'])->status);
    }

    public function testAResultOverTheCeilingOfTheToolIsRefusedWithAHintForTheModel(): void
    {
        $result = $this->call('aiprobemodule_big', []);

        self::assertSame(AiToolCallStatus::ResultTooLarge, $result->status);
        self::assertSame(['error' => 'result_too_large', 'message' => 'Narrow the query or ask for fewer items.'], $result->content);
    }

    public function testAWriteToolOnlyProposesAndNothingIsApplied(): void
    {
        $result = $this->call('aiprobemodule_update', ['id' => 7, 'value' => 'new']);

        self::assertSame(AiToolCallStatus::PendingApproval, $result->status);
        self::assertSame(1, ProbeToolState::$proposeCalls);
        self::assertSame(0, ProbeToolState::$applyCalls);
        self::assertNotNull($result->approvalId);

        $row = AiToolApprovalQuery::create()->findPk($result->approvalId);
        self::assertSame('pending', $row?->getStatus());
        self::assertSame('aiprobemodule_update', $row?->getToolName());
        self::assertSame('aiprobemodule', $row?->getModule());
        self::assertSame('admin.product', $row?->getAclResource());
        self::assertNull($row?->getAclModule());
        self::assertSame('UPDATE', $row?->getAclAccess());
        self::assertSame('probe:7', $row?->getTarget());
        self::assertSame('{"id":7,"value":"new"}', $row?->getOriginalArguments());
        self::assertSame($row?->getOriginalArguments(), $row?->getArguments());
        self::assertSame('{"value":"old"}', $row?->getBeforeState());
        self::assertSame('{"value":"new"}', $row?->getAfterState());
        self::assertFalse((bool) $row?->getAmended());
        self::assertSame('gateway', $row?->getChannel());
        self::assertSame('aiprobe.tools_admin', $row?->getUsageId());
        self::assertSame($this->hasher->hash(AiActor::admin($this->adminId)), $row?->getProposedByHash());
        self::assertSame($this->adminId, $row?->getProposedByAdminId());
        self::assertSame('marie', $row?->getProposedByAdminLogin());
        $ttl = $row?->getExpiresAt()?->getTimestamp() - time();
        self::assertEqualsWithDelta(24 * 3600, $ttl, 5);

        self::assertSame([
            'status' => 'pending_approval',
            'approval_id' => $result->approvalId,
            'summary' => 'Set the value of #7 to new',
            'expires_at' => $row?->getExpiresAt()?->format(\DATE_ATOM),
        ], $result->content);
    }

    public function testTheTimeToLiveOfAProposalIsASetting(): void
    {
        \Thelia\Model\ConfigQuery::write('ai_tool_approval_ttl_hours', '2');

        $row = AiToolApprovalQuery::create()->findPk($this->call('aiprobemodule_update', ['id' => 7, 'value' => 'new'])->approvalId);

        self::assertEqualsWithDelta(2 * 3600, $row?->getExpiresAt()?->getTimestamp() - time(), 5);
    }

    public function testAWriteWithInvalidArgumentsProposesNothing(): void
    {
        $result = $this->call('aiprobemodule_update', ['id' => 'seven', 'value' => 'new']);

        self::assertSame(AiToolCallStatus::InvalidArguments, $result->status);
        self::assertSame(0, ProbeToolState::$proposeCalls);
        self::assertSame(0, AiToolApprovalQuery::create()->count());
    }

    public function testAnAnswerHoldsOneMessageByCallAndOnlyEightRun(): void
    {
        $offer = $this->registry->offer($this->context(), ['aiprobemodule_read']);
        $calls = [];

        for ($index = 1; $index <= 10; ++$index) {
            $calls[] = new AiToolCall('toolu_'.$index, 'aiprobemodule_read', ['text' => 'mug']);
        }

        $messages = $this->registry->answer($calls, $offer->context);

        self::assertCount(10, $messages);
        self::assertSame(8, ProbeToolState::$executeCalls);

        foreach ($messages as $index => $message) {
            self::assertSame(AiMessageRole::Tool, $message->role);
            self::assertSame('toolu_'.($index + 1), $message->toolCallId);
        }

        self::assertSame('{"echo":"mug","locale":"fr_FR"}', $messages[7]->content);
        self::assertSame('{"error":"limited","message":"Too many tool calls for this task, try again later."}', $messages[8]->content);
        self::assertSame('{"error":"limited","message":"Too many tool calls for this task, try again later."}', $messages[9]->content);
        self::assertSame(1, AiToolCallLogQuery::create()->filterByStatus('limited')->count());
        self::assertSame('answer_limit: 2 calls', AiToolCallLogQuery::create()->filterByStatus('limited')->findOne()?->getError());
    }

    public function testAnAnswerToANameTheModelMadeUpStillGetsItsMessage(): void
    {
        $offer = $this->registry->offer($this->context(), ['aiprobemodule_read']);

        $messages = $this->registry->answer([new AiToolCall('toolu_1', 'made_up_tool', []), new AiToolCall('toolu_2', 'aiprobemodule_read', ['text' => 'a'])], $offer->context);

        self::assertSame('{"error":"unknown_tool","message":"Unknown tool: made_up_tool"}', $messages[0]->content);
        self::assertSame('{"echo":"a","locale":"fr_FR"}', $messages[1]->content);
    }

    public function testATaskIsLimitedToFiftyCallsInTheWindow(): void
    {
        $offer = $this->registry->offer($this->context('aiprobe.task.limited'), ['aiprobemodule_read']);
        $call = new AiToolCall('toolu_01', 'aiprobemodule_read', ['text' => 'mug']);

        for ($index = 1; $index <= AiToolRateLimiter::CALLS_PER_TASK; ++$index) {
            self::assertSame(AiToolCallStatus::Succeeded, $this->registry->call($call, $offer->context)->status, 'call '.$index);
        }

        $limited = $this->registry->call($call, $offer->context);

        self::assertSame(AiToolCallStatus::Limited, $limited->status);
        self::assertSame('{"error":"limited","message":"Too many tool calls for this task, try again later."}', $limited->toMessage('x')->content);
        self::assertSame(AiToolRateLimiter::CALLS_PER_TASK, ProbeToolState::$executeCalls);

        $otherTask = $this->registry->offer($this->context('aiprobe.task.other'), ['aiprobemodule_read']);
        self::assertSame(AiToolCallStatus::Succeeded, $this->registry->call($call, $otherTask->context)->status);
    }

    public function testWithoutACorrelationIdTheLimitHoldsForTheActorAndTheUsage(): void
    {
        $offer = $this->registry->offer(new AiToolContext(AiActor::admin($this->adminId), 'aiprobe.tools_admin'), ['aiprobemodule_read']);
        $call = new AiToolCall('toolu_01', 'aiprobemodule_read', ['text' => 'mug']);

        for ($index = 1; $index <= AiToolRateLimiter::CALLS_PER_TASK; ++$index) {
            $this->registry->call($call, $offer->context);
        }

        self::assertSame(AiToolCallStatus::Limited, $this->registry->call($call, $offer->context)->status);
    }

    public function testTheLogRowOfAnAdministratorNamesHimInClear(): void
    {
        $result = $this->call('aiprobemodule_read', ['text' => 'mug', 'limit' => 2]);

        $row = AiToolCallLogQuery::create()->findPk($result->logId);

        self::assertSame('aiprobemodule_read', $row?->getToolName());
        self::assertSame('aiprobemodule', $row?->getModule());
        self::assertSame('read', $row?->getEffect());
        self::assertSame('gateway', $row?->getChannel());
        self::assertSame('succeeded', $row?->getStatus());
        self::assertNull($row?->getError());
        self::assertSame('aiprobe.tools_admin', $row?->getUsageId());
        self::assertSame('aiprobe.conversation.88', $row?->getCorrelationId());
        self::assertSame('admin', $row?->getActorType());
        self::assertSame($this->adminId, $row?->getAdminId());
        self::assertSame('marie', $row?->getAdminLogin());
        self::assertSame(hash_hmac('sha256', 'admin:'.$this->adminId, self::SECRET), $row?->getActorHash());
        self::assertSame('{"text":"mug","limit":2}', $row?->getArguments());
        self::assertSame(\strlen('{"echo":"mug","locale":"fr_FR"}'), $row?->getResultBytes());
        self::assertNotNull($row?->getLatencyMs());
        self::assertEqualsWithDelta(30 * 86400, $row?->getArgumentsExpiresAt()?->getTimestamp() - time(), 5);
    }

    public function testTheLogRowOfACustomerAndOfAVisitorHoldsTheirHashOnly(): void
    {
        foreach ([[AiActor::customer(12), 'aiprobe.tools_customer', 'customer:12'], [AiActor::visitor('session-abc'), 'aiprobe.tools_public', 'visitor:session-abc']] as [$actor, $usage, $identity]) {
            $offer = $this->registry->offer(new AiToolContext($actor, $usage), ['aiprobemodule_catalog']);
            $result = $this->registry->call(new AiToolCall('toolu_01', 'aiprobemodule_catalog', []), $offer->context);

            $row = AiToolCallLogQuery::create()->findPk($result->logId);

            self::assertSame($actor->type->value, $row?->getActorType());
            self::assertSame(hash_hmac('sha256', $identity, self::SECRET), $row?->getActorHash());
            self::assertNull($row?->getAdminId());
            self::assertNull($row?->getAdminLogin());
            self::assertStringNotContainsString($actor->identifier, (string) json_encode($row?->toArray()));
        }
    }

    public function testAnAdministratorWhoDoesNotExistIsLoggedWithoutBreakingTheForeignKey(): void
    {
        $offer = $this->registry->offer(new AiToolContext(AiActor::customer(1), 'aiprobe.tools_public'), ['aiprobemodule_catalog']);
        $context = $offer->context;

        $result = $this->registry->call(new AiToolCall('toolu_01', 'aiprobemodule_catalog', []), $context);

        self::assertSame(AiToolCallStatus::Succeeded, $result->status);

        $ghost = new AiToolContext(AiActor::admin(2000000000), 'aiprobe.tools_admin');
        $unknown = $this->registry->call(new AiToolCall('toolu_01', 'aiprobemodule_read', ['text' => 'a']), $ghost);

        self::assertSame(AiToolCallStatus::UnknownTool, $unknown->status);
        self::assertNull(AiToolCallLogQuery::create()->findPk($unknown->logId)?->getAdminId());
    }

    public function testTheArgumentsOfALongCallAreCutAndMarked(): void
    {
        $result = $this->call('aiprobemodule_read', ['text' => 'mug', 'filler' => str_repeat('é', 5000)]);

        $arguments = (string) AiToolCallLogQuery::create()->findPk($result->logId)?->getArguments();

        self::assertSame(AiToolCallStatus::InvalidArguments, $result->status);
        self::assertLessThanOrEqual(4096, \strlen($arguments));
        self::assertStringEndsWith('…[truncated]', $arguments);
        self::assertTrue(mb_check_encoding($arguments, 'UTF-8'));
    }

    private function context(string $correlation = 'aiprobe.conversation.88'): AiToolContext
    {
        return new AiToolContext(AiActor::admin($this->adminId), 'aiprobe.tools_admin', $correlation, 'fr_FR');
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function call(string $tool, array $arguments): AiToolResult
    {
        $offer = $this->registry->offer($this->context(), [$tool]);

        return $this->registry->call(new AiToolCall('toolu_01', $tool, $arguments), $offer->context);
    }
}
